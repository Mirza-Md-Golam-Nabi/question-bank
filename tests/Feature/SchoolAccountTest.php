<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Schools\Pages\ManageSchools;
use App\Filament\School\Pages\PendingApproval;
use App\Filament\School\Pages\SchoolConnection;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\ReferralService;
use App\Support\SchoolConnectionSignature;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    /** A signed "connect" request from the school's software, as it sends one. */
    $this->connect = function (SchoolProfile $profile, array $body = ['installation_url' => 'https://school.example.test'], ?string $secret = null, ?int $at = null) {
        $json = json_encode($body);
        $timestamp = (string) ($at ?? now()->timestamp);

        return $this->call('POST', '/api/school/connect', [], [], [], [
            'HTTP_'.str_replace('-', '_', strtoupper(SchoolConnectionSignature::CODE_HEADER)) => $profile->connection_code,
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => SchoolConnectionSignature::sign($secret ?? $profile->connection_secret, $timestamp, $json),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $json);
    };
});

describe('signing up', function () {
    it('makes a new school account wait for an admin', function () {
        Socialite::fake('google', SocialiteUser::fake(['id' => 'google-school-1', 'email' => 'office@school.test', 'name' => 'Office']));

        $this->get('/auth/google/redirect/school');
        $this->get('/auth/google/callback')->assertRedirect(route('filament.school.pages.dashboard'));

        $school = User::query()->where('email', 'office@school.test')->sole();

        expect($school->role)->toBe(UserRole::School)
            ->and($school->status)->toBe(UserStatus::Pending)
            ->and($school->hasRole(UserRole::School->value))->toBeTrue();

        $this->actingAs($school)->get('/school')->assertRedirect(route('filament.school.pages.pending-approval'));
        $this->actingAs($school)->get('/school/school-connection')->assertRedirect(route('filament.school.pages.pending-approval'));
    });

    it('lets a waiting school tell the admin which school it is', function () {
        $school = User::factory()->school()->pending()->googleAuthenticated()->create();
        $this->actingAs($school);
        Filament::setCurrentPanel(Filament::getPanel('school'));

        $this->get(route('filament.school.pages.pending-approval'))->assertOk();

        livewire(PendingApproval::class)
            ->fillForm(['name' => 'Sunrise High School', 'eiin' => '123456', 'address' => 'Dhaka', 'phone' => '01711111111'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($school->schoolProfile)->toMatchArray(['name' => 'Sunrise High School', 'eiin' => '123456']);
    });

    it('takes no part in referrals', function () {
        expect(app(ReferralService::class)->canBeReferred(User::factory()->school()->make()))->toBeFalse()
            ->and(UserRole::School->holdsWallet())->toBeFalse();
    });
});

it('lets an admin approve a school', function () {
    $school = User::factory()->school()->pending()->googleAuthenticated()->create();
    SchoolProfile::factory()->create(['user_id' => $school->id, 'name' => 'Sunrise High School']);
    $this->actingAs(User::factory()->admin()->create());

    livewire(ManageSchools::class)
        ->assertCanSeeTableRecords([$school])
        ->assertSee('Sunrise High School')
        ->callAction(TestAction::make('approve')->table($school));

    expect($school->refresh()->status)->toBe(UserStatus::Active);

    $this->actingAs($school)->get('/school/school-connection')->assertOk()->assertSee($school->schoolProfile->connection_code);
});

describe('the connection page', function () {
    beforeEach(function () {
        $this->school = User::factory()->school()->googleAuthenticated()->create();
        $this->profile = SchoolProfile::for($this->school);
        $this->actingAs($this->school);
        Filament::setCurrentPanel(Filament::getPanel('school'));
    });

    it('gives each school its own code and secret', function () {
        $other = SchoolProfile::for(User::factory()->school()->create());

        expect($this->profile->connection_code)->toStartWith('QB-')
            ->and($this->profile->connection_code)->not->toBe($other->connection_code)
            ->and(strlen($this->profile->connection_secret))->toBe(64)
            ->and($this->profile->getRawOriginal('connection_secret'))->not->toBe($this->profile->connection_secret)
            ->and($this->profile->toArray())->not->toHaveKey('connection_secret');
    });

    it('makes a new secret, which disconnects the software', function () {
        $this->profile->markConnected('https://school.example.test');
        $oldSecret = $this->profile->connection_secret;

        livewire(SchoolConnection::class)->callAction('regenerateSecret');

        $this->profile->refresh();

        expect($this->profile->connection_secret)->not->toBe($oldSecret)
            ->and($this->profile->isConnected())->toBeFalse();
    });
});

describe('connecting the school software', function () {
    beforeEach(function () {
        $this->profile = SchoolProfile::for(User::factory()->school()->create());
    });

    it('connects with the right code and secret', function () {
        ($this->connect)($this->profile)
            ->assertOk()
            ->assertJsonStructure(['school_name', 'connected_at']);

        expect($this->profile->refresh()->installation_url)->toBe('https://school.example.test')
            ->and($this->profile->isConnected())->toBeTrue();
    });

    it('refuses a wrong secret, an unknown code and an old request', function () {
        ($this->connect)($this->profile, secret: 'not-the-secret')->assertUnauthorized();
        ($this->connect)($this->profile, at: now()->subMinutes(10)->timestamp)->assertUnauthorized();
        ($this->connect)((new SchoolProfile)->forceFill(['connection_code' => 'QB-NOPE', 'connection_secret' => 'x']))->assertUnauthorized();

        expect($this->profile->refresh()->isConnected())->toBeFalse();
    });

    it('refuses a school an admin has not approved, or has suspended', function () {
        $this->profile->user->update(['status' => UserStatus::Pending]);
        ($this->connect)($this->profile)->assertForbidden();

        $this->profile->user->update(['status' => UserStatus::Suspended]);
        ($this->connect)($this->profile)->assertForbidden();
    });

    it('needs the address the software answers at', function () {
        ($this->connect)($this->profile, ['installation_url' => 'not a url'])->assertUnprocessable();
    });
});
