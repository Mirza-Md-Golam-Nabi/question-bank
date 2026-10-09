<?php

use App\Filament\Student\Pages\MyProfile as StudentMyProfile;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReferralService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->referrals = app(ReferralService::class);
});

function signInWithGoogleAs(string $role, string $email): void
{
    Socialite::fake('google', SocialiteUser::fake(['id' => "google-{$email}", 'email' => $email, 'name' => 'New User']));

    test()->get("/auth/google/redirect/{$role}");
    test()->get('/auth/google/callback');
}

it('links a new student to the teacher whose referral link they came through', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();

    $this->get(route('referral.visit', ['code' => $this->referrals->codeFor($teacher)]))
        ->assertRedirect(route('home'));
    signInWithGoogleAs('student', 'new-student@example.com');

    expect(User::where('email', 'new-student@example.com')->first()->referred_by_id)->toBe($teacher->id);
});

it('links a new teacher to the teacher who referred them', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();

    $this->get(route('referral.visit', ['code' => $this->referrals->codeFor($teacher)]));
    signInWithGoogleAs('teacher', 'new-teacher@example.com');

    expect(User::where('email', 'new-teacher@example.com')->first()->referred_by_id)->toBe($teacher->id);
});

it('creates the account without a referrer when the link has an unknown code', function () {
    BillingSetting::create(['referral_enabled' => true]);

    $this->get(route('referral.visit', ['code' => 'NOSUCHCODE']))->assertRedirect(route('home'));
    signInWithGoogleAs('student', 'new-student@example.com');

    expect(User::where('email', 'new-student@example.com')->first()->referred_by_id)->toBeNull();
});

it('ignores a referral link while the programme is off', function () {
    $teacher = User::factory()->teacher()->create();

    $this->get(route('referral.visit', ['code' => $this->referrals->codeFor($teacher)]));
    signInWithGoogleAs('student', 'new-student@example.com');

    expect(User::where('email', 'new-student@example.com')->first()->referred_by_id)->toBeNull();
});

it('does not refer a new staff account', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();

    $this->get(route('referral.visit', ['code' => $this->referrals->codeFor($teacher)]));
    signInWithGoogleAs('staff', 'new-staff@example.com');

    expect(User::where('email', 'new-staff@example.com')->first()->referred_by_id)->toBeNull();
});

it('does not re-refer an existing account that signs in after visiting a link', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->googleAuthenticated()->create(['email' => 'old-student@example.com']);

    $this->get(route('referral.visit', ['code' => $this->referrals->codeFor($teacher)]));
    Socialite::fake('google', SocialiteUser::fake(['id' => $student->google_id, 'email' => $student->email]));
    $this->get('/auth/google/redirect/student');
    $this->get('/auth/google/callback');

    expect($student->refresh()->referred_by_id)->toBeNull();
});

it('gives every user their own code and keeps it', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $code = $this->referrals->codeFor($teacher);

    expect($code)->toHaveLength(8)
        ->and($this->referrals->codeFor($teacher))->toBe($code)
        ->and($this->referrals->codeFor($student))->not->toBe($code);
});

it('accepts a code typed in any letter case', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $attached = $this->referrals->attach($student, ' '.strtolower($this->referrals->codeFor($teacher)).' ');

    expect($attached)->toBeTrue();
    expect($student->refresh()->referred_by_id)->toBe($teacher->id);
});

it('refuses a user\'s own code', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $student = User::factory()->student()->create();

    expect($this->referrals->attach($student, $this->referrals->codeFor($student)))->toBeFalse();
    expect($student->refresh()->referred_by_id)->toBeNull();
});

it('refuses a code once the user has bought a subscription', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    Payment::factory()->create(['user_id' => $student->id]);

    expect($this->referrals->attach($student, $this->referrals->codeFor($teacher)))->toBeFalse();
});

it('does not replace the referrer a user already has', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $first = User::factory()->teacher()->create();
    $second = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $this->referrals->attach($student, $this->referrals->codeFor($first));

    expect($this->referrals->attach($student, $this->referrals->codeFor($second)))->toBeFalse();
    expect($student->refresh()->referred_by_id)->toBe($first->id);
});

it('flags a payment sent from a number the referrer uses', function () {
    $referrer = User::factory()->teacher()->create(['phone' => '01811111111']);
    $student = User::factory()->student()->create();
    $student->forceFill(['referred_by_id' => $referrer->id])->save();

    $fromReferrersNumber = Payment::factory()->pending()->create(['user_id' => $student->id, 'payer_number' => '01811111111']);
    $fromAnotherNumber = Payment::factory()->pending()->create(['user_id' => $student->id, 'payer_number' => '01922222222']);

    expect($this->referrals->sharesPayerNumberWithReferrer($fromReferrersNumber))->toBeTrue()
        ->and($this->referrals->sharesPayerNumberWithReferrer($fromAnotherNumber))->toBeFalse();
});

it('saves a student\'s phone number in one normalized form from the profile page', function () {
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)
        ->fillForm(['phone' => '+880 1712-345678'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($student->refresh()->phone)->toBe('01712345678');
});

it('rejects a phone number that is not a mobile number', function () {
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)
        ->fillForm(['phone' => '12345'])
        ->call('save')
        ->assertHasFormErrors(['phone']);

    expect($student->refresh()->phone)->toBeNull();
});

it('lets a student enter a referral code on the profile page', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)
        ->fillForm(['referral_code' => $this->referrals->codeFor($teacher)])
        ->call('save')
        ->assertHasNoErrors();

    expect($student->refresh()->referred_by_id)->toBe($teacher->id);
});

it('shows an error for a referral code that does not exist', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)
        ->fillForm(['referral_code' => 'NOSUCHCODE'])
        ->call('save')
        ->assertHasErrors(['data.referral_code']);
});

it('shows a student their own code and wallet on the profile page', function () {
    BillingSetting::create(['referral_enabled' => true, 'referrer_reward_percent' => 20, 'referee_discount_percent' => 10]);
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)
        ->assertSee($this->referrals->codeFor($student))
        ->assertSee('20%')
        ->assertSee('10%');
});

it('hands the copy buttons the real code and link rather than uncompiled template code', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)
        ->assertDontSeeHtml('@js(')
        ->assertSeeHtml("x-on:click=\"copy('code')\"")
        ->assertSeeHtml("x-on:click=\"copy('link')\"")
        ->assertSeeHtml('values: JSON.parse(');
});

it('hides the referral section while the programme is off', function () {
    $student = User::factory()->student()->create();
    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMyProfile::class)->assertDontSee('Refer and earn');
});
