<?php

use App\Filament\Pages\BillingSettings;
use App\Models\BillingSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['session.driver' => 'database']);

    /** A device signed in as `$user`, last used `$daysAgo` days ago; opens `$url` on it. */
    $this->openAfterDays = function (User $user, int $daysAgo, string $url) {
        DB::table('sessions')->insert([
            'id' => $id = str_repeat('a', 40),
            'user_id' => $user->id,
            'ip_address' => '103.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(json_encode(['login_web_'.sha1(SessionGuard::class) => $user->id])),
            'last_activity' => now()->subDays($daysAgo)->getTimestamp(),
        ]);

        return $this->withCookie(config('session.cookie'), $id)->get($url);
    };
});

it('keeps a device signed in for life when the admin sets 0, far past the old two hours', function (string $role, string $panel) {
    BillingSetting::create(['login_stay_days' => 0]);

    ($this->openAfterDays)(User::factory()->{$role}()->create(), 1000, "/{$panel}")->assertOk();
})->with([['teacher', 'teacher'], ['staff', 'staff'], ['admin', 'admin']]);

it('keeps a device signed in within the days the admin set', function (string $role, string $panel) {
    BillingSetting::create(['login_stay_days' => 30]);

    ($this->openAfterDays)(User::factory()->{$role}()->create(), 29, "/{$panel}")->assertOk();
})->with([['teacher', 'teacher'], ['staff', 'staff'], ['admin', 'admin']]);

it('signs a device out once it has gone unused for longer than the days the admin set', function (string $role, string $panel) {
    BillingSetting::create(['login_stay_days' => 30]);

    ($this->openAfterDays)(User::factory()->{$role}()->create(), 31, "/{$panel}")->assertRedirect("/{$panel}/login");
})->with([['teacher', 'teacher'], ['staff', 'staff'], ['admin', 'admin']]);

it('sends the session cookie with the admin\'s length', function () {
    BillingSetting::create(['login_stay_days' => 30]);

    $cookie = $this->get('/')->getCookie(config('session.cookie'), decrypt: false);

    expect($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp())
        ->and($cookie->getExpiresTime())->toBeLessThan(now()->addDays(31)->getTimestamp());
});

it('clears out old guest sessions but keeps signed-in ones', function () {
    $user = User::factory()->student()->create();
    $row = fn (string $id, ?int $userId, int $daysAgo): array => [
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => null,
        'user_agent' => null,
        'payload' => '',
        'last_activity' => now()->subDays($daysAgo)->getTimestamp(),
    ];
    DB::table('sessions')->insert([$row('old-guest', null, 2), $row('new-guest', null, 0), $row('old-user', $user->id, 500)]);
    config(['session.lifetime' => BillingSetting::current()->loginDurationInMinutes()]);

    Session::driver('database')->getHandler()->gc(config('session.lifetime') * 60);

    expect(DB::table('sessions')->orderBy('id')->pluck('id')->all())->toBe(['new-guest', 'old-user']);
});

it('lets the admin set how long people stay signed in', function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(BillingSettings::class)
        ->fillForm(['login_stay_days' => 45])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(BillingSetting::current()->login_stay_days)->toBe(45);
});
