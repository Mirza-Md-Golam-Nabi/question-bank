<?php

use App\Filament\Student\Pages\MyProfile as StudentMyProfile;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\User;
use App\Services\DeviceSessionService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    // Devices are the rows of the database session store.
    config(['session.driver' => 'database']);
    BillingSetting::create(['student_device_limit' => 2, 'teacher_device_limit' => 3]);

    $this->devices = app(DeviceSessionService::class);
    $this->student = User::factory()->student()->googleAuthenticated()->create(['academic_class_id' => AcademicClass::factory()->create()->id]);

    /** A device the user is signed in on, last used `$minutesAgo` minutes ago. */
    $this->signedInOn = fn (User $user, string $id, int $minutesAgo, string $agent = 'Mozilla/5.0 (Linux; Android 14) Chrome/120') => DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '103.0.0.1',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ]);
    $this->sessionIds = fn (User $user): array => DB::table('sessions')->where('user_id', $user->id)->orderBy('id')->pluck('id')->all();
});

it('signs a student out of the devices used longest ago when they sign in on one too many', function () {
    ($this->signedInOn)($this->student, 'old', 300);
    ($this->signedInOn)($this->student, 'recent', 5);
    ($this->signedInOn)($this->student, 'now', 0);

    $this->devices->enforceLimit($this->student, 'now');

    expect(($this->sessionIds)($this->student))->toBe(['now', 'recent']);
});

it('lets a teacher keep more devices, and nobody else is touched', function () {
    $teacher = User::factory()->teacher()->create();
    foreach (['a' => 30, 'b' => 20, 'c' => 10, 'd' => 0] as $id => $minutes) {
        ($this->signedInOn)($teacher, $id, $minutes);
    }
    ($this->signedInOn)($this->student, 'student-device', 999);

    $this->devices->enforceLimit($teacher, 'd');

    expect(($this->sessionIds)($teacher))->toBe(['b', 'c', 'd'])
        ->and(($this->sessionIds)($this->student))->toBe(['student-device']);
});

it('has no limit when the admin sets 0', function () {
    BillingSetting::current()->update(['student_device_limit' => 0]);
    foreach (range(1, 4) as $i) {
        ($this->signedInOn)($this->student, "s{$i}", $i);
    }

    $this->devices->enforceLimit($this->student, 's1');

    expect(($this->sessionIds)($this->student))->toHaveCount(4);
});

it('applies the limit on a Google sign-in', function () {
    ($this->signedInOn)($this->student, 'old', 300);
    ($this->signedInOn)($this->student, 'recent', 5);
    Socialite::fake('google', SocialiteUser::fake(['id' => $this->student->google_id, 'email' => $this->student->email]));

    $this->get('/auth/google/redirect/student');
    $this->get('/auth/google/callback')->assertRedirect();

    // Only the most recent other device is left beside this one.
    expect(($this->sessionIds)($this->student))->not->toContain('old')
        ->and(($this->sessionIds)($this->student))->toContain('recent');
});

it('shows a student their devices and lets them sign one out, never another user\'s', function () {
    ($this->signedInOn)($this->student, 'phone', 60, 'Mozilla/5.0 (Linux; Android 14) Chrome/120');
    $other = User::factory()->student()->create();
    ($this->signedInOn)($other, 'not-mine', 60);
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)
        ->assertSee('Chrome on Android')
        ->call('signOutDevice', 'phone')
        ->assertNotified()
        ->call('signOutDevice', 'not-mine');

    expect(($this->sessionIds)($this->student))->toBe([])
        ->and(($this->sessionIds)($other))->toBe(['not-mine']);
});
