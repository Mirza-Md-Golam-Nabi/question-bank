<?php

use App\Enums\ReferralRewardStatus;
use App\Filament\Staff\Pages\MyEarnings;
use App\Filament\Staff\Pages\MyProfile as StaffMyProfile;
use App\Models\BillingSetting;
use App\Models\Question;
use App\Models\ReferralReward;
use App\Models\StaffEarning;
use App\Models\StaffPayout;
use App\Models\User;
use App\Services\ReferralService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->referrals = app(ReferralService::class);
});

it('lets an active staff member refer a new student', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $staff = User::factory()->staff()->create();
    $student = User::factory()->student()->create();

    expect($this->referrals->attach($student, $this->referrals->codeFor($staff)))->toBeTrue();
    expect($student->refresh()->referred_by_id)->toBe($staff->id);
});

it('does not accept the code of a staff member who is not active', function (string $state) {
    BillingSetting::create(['referral_enabled' => true]);
    $staff = User::factory()->staff()->{$state}()->create();
    $student = User::factory()->student()->create();

    expect($this->referrals->attach($student, $this->referrals->codeFor($staff)))->toBeFalse();
})->with(['pending', 'suspended', 'permanentlySuspended']);

it('never lets a staff member be the one who is referred', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $teacher = User::factory()->teacher()->create();
    $staff = User::factory()->staff()->create();

    expect($this->referrals->attach($staff, $this->referrals->codeFor($teacher)))->toBeFalse();
});

it('shows an active staff member their code and percentage, without a wallet', function () {
    BillingSetting::create(['referral_enabled' => true, 'referrer_reward_percent' => 20, 'staff_reward_percent' => 12]);
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    livewire(StaffMyProfile::class)
        ->assertSee($this->referrals->codeFor($staff))
        ->assertSee('12%')
        ->assertSee('paid with your payout')
        ->assertDontSee('My wallet');
});

it('hides the code from a suspended staff member', function () {
    BillingSetting::create(['referral_enabled' => true]);
    $this->actingAs(User::factory()->staff()->suspended()->create());
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    livewire(StaffMyProfile::class)->assertDontSee('Refer and earn');
});

it('pays out matured referral rewards together with question earnings', function () {
    $staff = User::factory()->staff()->create();
    StaffEarning::factory()->create(['staff_id' => $staff->id, 'question_id' => Question::factory(), 'amount' => 10]);
    $matured = ReferralReward::factory()->create(['referrer_id' => $staff->id, 'amount' => 18]);
    $maturing = ReferralReward::factory()->maturing()->create(['referrer_id' => $staff->id, 'amount' => 25]);
    $cancelled = ReferralReward::factory()->cancelled()->create(['referrer_id' => $staff->id, 'amount' => 40]);

    $payout = StaffPayout::createFor($staff, User::factory()->admin()->create());

    expect((float) $payout->total_amount)->toBe(28.0);
    expect($matured->refresh()->payout_id)->toBe($payout->id)
        ->and($matured->status())->toBe(ReferralRewardStatus::Paid);
    expect($maturing->refresh()->payout_id)->toBeNull();
    expect($cancelled->refresh()->payout_id)->toBeNull();
});

it('does not pay the same referral reward in a second payout', function () {
    $staff = User::factory()->staff()->create();
    $admin = User::factory()->admin()->create();
    ReferralReward::factory()->create(['referrer_id' => $staff->id, 'amount' => 18]);
    StaffPayout::createFor($staff, $admin);

    $second = StaffPayout::createFor($staff, $admin);

    expect((float) $second->total_amount)->toBe(0.0);
    expect(StaffPayout::amountDueTo($staff))->toBe(0.0);
});

it('shows a staff member their referral earnings on the earnings page', function () {
    $staff = User::factory()->staff()->create();
    ReferralReward::factory()->create(['referrer_id' => $staff->id, 'amount' => 18]);
    ReferralReward::factory()->maturing()->create(['referrer_id' => $staff->id, 'amount' => 25]);
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    livewire(MyEarnings::class)
        ->assertSee('Referral earnings')
        ->assertSee('18.00')
        ->assertSee('25.00');
});
