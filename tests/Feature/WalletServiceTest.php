<?php

use App\Enums\WalletTransactionType;
use App\Models\BillingSetting;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;

beforeEach(function () {
    $this->wallet = app(WalletService::class);
});

it('is the credits minus what was spent', function () {
    $student = User::factory()->student()->create();
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 100]);
    WalletTransaction::factory()->spent(30)->create(['user_id' => $student->id]);

    expect($this->wallet->balanceFor($student))->toBe(70.0);
});

it('does not count another user\'s credit', function () {
    $student = User::factory()->student()->create();
    WalletTransaction::factory()->create(['amount' => 100]);

    expect($this->wallet->balanceFor($student))->toBe(0.0);
});

it('counts a referral reward as credit once it has matured', function () {
    $teacher = User::factory()->teacher()->create();
    ReferralReward::factory()->create(['referrer_id' => $teacher->id, 'amount' => 36]);

    expect($this->wallet->balanceFor($teacher))->toBe(36.0);
});

it('does not count a referral reward that is still inside the refund period', function () {
    $teacher = User::factory()->teacher()->create();
    ReferralReward::factory()->maturing()->create(['referrer_id' => $teacher->id, 'amount' => 36]);

    expect($this->wallet->balanceFor($teacher))->toBe(0.0);

    $this->travelTo(now()->addDays(8));

    expect($this->wallet->balanceFor($teacher))->toBe(36.0);
});

it('never counts a cancelled referral reward', function () {
    $teacher = User::factory()->teacher()->create();
    ReferralReward::factory()->cancelled()->create(['referrer_id' => $teacher->id, 'amount' => 36]);

    expect($this->wallet->balanceFor($teacher))->toBe(0.0);
});

it('gives a staff member no wallet credit for a reward that is paid in cash', function () {
    $staff = User::factory()->staff()->create();
    ReferralReward::factory()->create(['referrer_id' => $staff->id, 'amount' => 36]);

    expect($this->wallet->balanceFor($staff))->toBe(0.0);
});

it('drops credit once its expiry date has passed', function () {
    $student = User::factory()->student()->create();
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 100, 'expires_at' => now()->addMonth()]);
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 40]);

    $this->travelTo(now()->addMonths(2));

    expect($this->wallet->balanceFor($student))->toBe(40.0);
});

it('does not go negative when credit that was partly spent later expires', function () {
    $student = User::factory()->student()->create();
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 100, 'expires_at' => now()->addMonth()]);
    WalletTransaction::factory()->spent(60)->create(['user_id' => $student->id]);

    $this->travelTo(now()->addMonths(2));

    expect($this->wallet->balanceFor($student))->toBe(0.0);
});

it('spends the credit that came first', function () {
    $student = User::factory()->student()->create();
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 100, 'expires_at' => now()->addMonth(), 'created_at' => now()->subDays(2)]);
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 100, 'created_at' => now()->subDay()]);
    WalletTransaction::factory()->spent(100)->create(['user_id' => $student->id]);

    $this->travelTo(now()->addMonths(2));

    expect($this->wallet->balanceFor($student))->toBe(100.0);
});

it('gives returned credit the expiry the admin has set', function () {
    $this->travelTo('2026-01-15 10:00:00');
    BillingSetting::create(['credit_expiry_months' => 6]);
    $student = User::factory()->student()->create();

    $transaction = $this->wallet->credit($student, 50, WalletTransactionType::PurchaseReturn);

    expect($transaction->expires_at->toDateString())->toBe('2026-07-15');
});

it('gives credit that never expires when the admin set no expiry', function () {
    $student = User::factory()->student()->create();

    $transaction = $this->wallet->credit($student, 50, WalletTransactionType::PurchaseReturn);

    expect($transaction->expires_at)->toBeNull();
});
