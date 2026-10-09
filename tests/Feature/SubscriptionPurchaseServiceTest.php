<?php

use App\Enums\PaymentStatus;
use App\Enums\ReferralRewardStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\StaffPayout;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OtpService;
use App\Services\Sms\SmsSender;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->purchases = app(SubscriptionPurchaseService::class);
    $this->wallet = app(WalletService::class);
    $this->admin = User::factory()->admin()->create();
});

/**
 * Referral on at 20% reward (10% for staff) / 10% discount, a 7-day refund
 * period, and a bKash number to pay to.
 */
function billingSettings(array $overrides = []): BillingSetting
{
    return BillingSetting::create([
        'referral_enabled' => true,
        'referrer_reward_percent' => 20,
        'staff_reward_percent' => 10,
        'referee_discount_percent' => 10,
        'refund_window_days' => 7,
        'bkash_number' => '01700000000',
        ...$overrides,
    ]);
}

/**
 * A new student who signed up with this referrer's code.
 */
function referredBy(User $referrer): User
{
    $student = User::factory()->student()->create();
    $student->forceFill(['referred_by_id' => $referrer->id])->save();

    return $student;
}

/**
 * @return array<string, mixed>
 */
function bkashPayment(string $transactionId = 'TRX123', array $overrides = []): array
{
    return [
        'payer_provider' => 'bkash',
        'payer_number' => '01811111111',
        'transaction_id' => $transactionId,
        ...$overrides,
    ];
}

it('charges the full price to someone who was not referred', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    expect($this->purchases->quote($student, $plan, useWallet: false))
        ->toBe(['list_price' => 200.0, 'discount' => 0.0, 'wallet' => 0.0, 'payable' => 200.0]);
});

it('takes the referral discount and then wallet credit off the price', function () {
    billingSettings();
    $referrer = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $student->forceFill(['referred_by_id' => $referrer->id])->save();
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 50]);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    expect($this->purchases->quote($student, $plan, useWallet: true))
        ->toBe(['list_price' => 200.0, 'discount' => 20.0, 'wallet' => 50.0, 'payable' => 130.0]);
});

it('gives no discount while the referral programme is off', function () {
    billingSettings(['referral_enabled' => false]);
    $referrer = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $student->forceFill(['referred_by_id' => $referrer->id])->save();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    expect($this->purchases->quote($student, $plan, useWallet: false)['discount'])->toBe(0.0);
});

it('records a pending payment and holds the wallet credit used', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 50]);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    $payment = $this->purchases->request($student, $plan, bkashPayment('TRX-A', ['use_wallet' => true, 'payer_number' => '+880 1811-111111']));

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount)->toBe(150.0)
        ->and($payment->wallet_amount)->toBe(50.0)
        ->and($payment->payer_number)->toBe('01811111111')
        ->and($payment->gateway_transaction_id)->toBe('TRX-A');
    expect($this->wallet->balanceFor($student))->toBe(0.0);
    expect($student->subscriptions()->count())->toBe(0);
});

it('refuses a second purchase while one is waiting for approval', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $this->purchases->request($student, $plan, bkashPayment('TRX-A'));

    expect(fn () => $this->purchases->request($student, $plan, bkashPayment('TRX-B')))
        ->toThrow(ValidationException::class, 'You already have a payment waiting for approval.');
    expect(Payment::count())->toBe(1);
});

it('refuses a transaction id that was already used', function () {
    billingSettings();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $this->purchases->request(User::factory()->student()->create(), $plan, bkashPayment('TRX-A'));

    expect(fn () => $this->purchases->request(User::factory()->student()->create(), $plan, bkashPayment('TRX-A')))
        ->toThrow(ValidationException::class, 'This transaction ID has already been used.');
});

it('refuses a plan meant for another role', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    $teacherPlan = SubscriptionPlan::factory()->pro()->create(['price' => 500]);

    expect(fn () => $this->purchases->request($student, $teacherPlan, bkashPayment()))
        ->toThrow(ValidationException::class, 'This plan is not available to you.');
});

it('refuses a payment method the admin has not set a number for', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    expect(fn () => $this->purchases->request($student, $plan, bkashPayment('TRX-A', ['payer_provider' => 'nagad'])))
        ->toThrow(ValidationException::class, 'Choose how you sent the payment.');
});

it('refuses a payer number that is not a mobile number', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    expect(fn () => $this->purchases->request($student, $plan, bkashPayment('TRX-A', ['payer_number' => '12345'])))
        ->toThrow(ValidationException::class, 'Enter the mobile number you sent the payment from.');
});

it('starts the subscription and the refund period when the first payment is approved', function () {
    $this->travelTo('2026-03-10 09:00:00');
    billingSettings();
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request($student, $plan, bkashPayment());

    $approved = $this->purchases->approve($payment, $this->admin);

    $subscription = $student->activeSubscription();
    expect($approved)->toBeTrue();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Success)
        ->and($payment->reviewed_by)->toBe($this->admin->id)
        ->and($payment->subscription_id)->toBe($subscription->id)
        ->and($payment->refundable_until->toDateString())->toBe('2026-03-17');
    expect($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->ends_at->toDateString())->toBe('2026-04-10');
});

it('gives the referrer their reward only once the refund period has passed', function () {
    billingSettings();
    $referrer = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    $this->purchases->approve($this->purchases->request(referredBy($referrer), $plan, bkashPayment()), $this->admin);

    expect($this->wallet->balanceFor($referrer))->toBe(0.0);

    $this->travelTo(now()->addDays(8));

    // 200 − 10% discount = 180 paid in cash; 20% of that is the reward.
    expect($this->wallet->balanceFor($referrer))->toBe(36.0);
});

it('makes the reward available at once when the admin allows no refunds', function () {
    billingSettings(['refund_window_days' => 0]);
    $referrer = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    $this->purchases->approve($this->purchases->request(referredBy($referrer), $plan, bkashPayment()), $this->admin);
    $this->travel(1)->seconds();

    expect($this->wallet->balanceFor($referrer))->toBe(36.0);
});

it('pays a staff referrer their own percentage, in cash rather than wallet credit', function () {
    billingSettings();
    $staff = User::factory()->staff()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    $payment = $this->purchases->request(referredBy($staff), $plan, bkashPayment());
    $this->purchases->approve($payment, $this->admin);
    $this->travelTo(now()->addDays(8));

    // 10% (the staff percentage) of the 180 paid in cash.
    expect($payment->referralReward->amount)->toBe(18.0)
        ->and($payment->referralReward->expires_at)->toBeNull();
    expect(StaffPayout::amountDueTo($staff))->toBe(18.0);
    expect($this->wallet->balanceFor($staff))->toBe(0.0);
});

it('gives no reward to a staff referrer who is not active when the payment is approved', function (string $state) {
    billingSettings();
    $staff = User::factory()->staff()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request(referredBy($staff), $plan, bkashPayment());
    $staff->update(['status' => $state]);

    $this->purchases->approve($payment, $this->admin);

    expect($payment->referralReward)->toBeNull();
})->with(['suspended', 'permanent_suspend', 'pending']);

it('does nothing when the same payment is approved a second time', function () {
    billingSettings();
    $referrer = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request(referredBy($referrer), $plan, bkashPayment());
    $this->purchases->approve($payment, $this->admin);

    $approvedAgain = $this->purchases->approve($payment, $this->admin);

    expect($approvedAgain)->toBeFalse();
    expect(Subscription::count())->toBe(1);
    expect(ReferralReward::count())->toBe(1);
});

it('rewards the referrer for the first purchase only', function () {
    billingSettings();
    $referrer = User::factory()->teacher()->create();
    $student = referredBy($referrer);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $this->purchases->approve($this->purchases->request($student, $plan, bkashPayment('TRX-1')), $this->admin);

    $second = $this->purchases->request($student, $plan, bkashPayment('TRX-2'));
    $this->purchases->approve($second, $this->admin);

    expect($second->refresh()->discount_amount)->toBe(0.0);
    expect(ReferralReward::sum('amount'))->toEqual(36);
});

it('gives no reward while the referral programme is off', function () {
    billingSettings(['referral_enabled' => false]);
    $referrer = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    $this->purchases->approve($this->purchases->request(referredBy($referrer), $plan, bkashPayment()), $this->admin);

    expect(ReferralReward::count())->toBe(0);
});

it('keeps a reward at the amount and date it was given when the settings change later', function () {
    $this->travelTo('2026-03-10 09:00:00');
    $settings = billingSettings();
    $referrer = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request(referredBy($referrer), $plan, bkashPayment());
    $this->purchases->approve($payment, $this->admin);

    $settings->update(['referrer_reward_percent' => 50, 'refund_window_days' => 30]);

    expect($payment->referralReward->amount)->toBe(36.0)
        ->and($payment->referralReward->matures_at->toDateString())->toBe('2026-03-17');
    expect($payment->refresh()->refundable_until->toDateString())->toBe('2026-03-17');
});

it('activates at once and rewards nobody when wallet credit covers the whole price', function () {
    billingSettings(['referee_discount_percent' => 0]);
    $referrer = User::factory()->teacher()->create();
    $student = referredBy($referrer);
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 250]);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    $payment = $this->purchases->request($student, $plan, ['use_wallet' => true]);

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->amount)->toBe(0.0);
    expect($student->activeSubscription()->plan_id)->toBe($plan->id);
    expect($this->wallet->balanceFor($student))->toBe(50.0);
    expect(ReferralReward::count())->toBe(0);
});

it('adds a renewal of the same plan to the end of the current period', function () {
    $this->travelTo('2026-03-10 09:00:00');
    billingSettings();
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $this->purchases->approve($this->purchases->request($student, $plan, bkashPayment('TRX-1')), $this->admin);

    $this->travelTo('2026-03-20 09:00:00');
    $this->purchases->approve($this->purchases->request($student, $plan, bkashPayment('TRX-2')), $this->admin);

    expect($student->activeSubscription()->ends_at->toDateString())->toBe('2026-05-10');
});

it('returns the wallet credit when a payment is rejected', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 50]);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request($student, $plan, bkashPayment('TRX-A', ['use_wallet' => true]));

    $rejected = $this->purchases->reject($payment, $this->admin, 'No such transaction');

    expect($rejected)->toBeTrue();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->review_note)->toBe('No such transaction');
    expect($this->wallet->balanceFor($student))->toBe(50.0);
    expect($student->subscriptions()->count())->toBe(0);
});

it('cannot reject a payment that was already approved', function () {
    billingSettings();
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request($student, $plan, bkashPayment());
    $this->purchases->approve($payment, $this->admin);

    expect($this->purchases->reject($payment, $this->admin))->toBeFalse();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Success);
});

it('ends the subscription, returns wallet credit and cancels the reward on a refund', function () {
    billingSettings();
    $referrer = User::factory()->teacher()->create();
    $student = referredBy($referrer);
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 30]);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request($student, $plan, bkashPayment('TRX-A', ['use_wallet' => true]));
    $this->purchases->approve($payment, $this->admin);

    $refunded = $this->purchases->refund($payment, $this->admin, 'Customer asked');

    expect($refunded)->toBeTrue();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_at)->not->toBeNull();
    expect($payment->subscription->status)->toBe(SubscriptionStatus::Cancelled);
    expect($student->activeSubscription())->toBeNull();
    expect($this->wallet->balanceFor($student))->toBe(30.0);
    expect($payment->referralReward->status())->toBe(ReferralRewardStatus::Cancelled);

    $this->travelTo(now()->addDays(8));

    expect($this->wallet->balanceFor($referrer))->toBe(0.0);
});

it('refuses a refund once the refund period has passed', function () {
    billingSettings();
    $referrer = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request(referredBy($referrer), $plan, bkashPayment());
    $this->purchases->approve($payment, $this->admin);

    $this->travelTo(now()->addDays(8));

    expect($this->purchases->refund($payment, $this->admin))->toBeFalse();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Success);
    expect($this->wallet->balanceFor($referrer))->toBe(36.0);
});

it('refuses every refund when the admin allows none', function () {
    billingSettings(['refund_window_days' => 0]);
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $payment = $this->purchases->request($student, $plan, bkashPayment());
    $this->purchases->approve($payment, $this->admin);

    expect($this->purchases->refund($payment, $this->admin))->toBeFalse();
});

it('rewards the referrer again when the purchase after a refunded one is approved', function () {
    billingSettings();
    $referrer = User::factory()->teacher()->create();
    $student = referredBy($referrer);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);
    $first = $this->purchases->request($student, $plan, bkashPayment('TRX-1'));
    $this->purchases->approve($first, $this->admin);
    $this->purchases->refund($first, $this->admin);

    $this->purchases->approve($this->purchases->request($student, $plan, bkashPayment('TRX-2')), $this->admin);
    $this->travelTo(now()->addDays(8));

    expect($this->wallet->balanceFor($referrer))->toBe(36.0);
});

it('needs a valid otp to spend wallet credit when the admin requires one', function () {
    billingSettings(['otp_required_for_credit' => true]);
    $sms = new class implements SmsSender
    {
        /** @var array<int, string> */
        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            $this->sent[] = $message;
        }
    };
    $this->app->instance(SmsSender::class, $sms);
    $student = User::factory()->student()->create(['phone' => '01811111111']);
    WalletTransaction::factory()->create(['user_id' => $student->id, 'amount' => 50]);
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    expect(fn () => $this->purchases->request($student, $plan, bkashPayment('TRX-A', ['use_wallet' => true, 'otp_code' => '000000'])))
        ->toThrow(ValidationException::class, 'The verification code is wrong or has expired.');
    expect(Payment::count())->toBe(0);

    app(OtpService::class)->send($student);
    preg_match('/\d{6}/', $sms->sent[0], $code);

    $payment = $this->purchases->request($student, $plan, bkashPayment('TRX-A', ['use_wallet' => true, 'otp_code' => $code[0]]));

    expect($payment->wallet_amount)->toBe(50.0);
    expect($student->refresh()->phone_verified_at)->not->toBeNull();
});

it('asks for no otp when no wallet credit is being spent', function () {
    billingSettings(['otp_required_for_credit' => true]);
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 200]);

    $payment = $this->purchases->request($student, $plan, bkashPayment());

    expect($payment->status)->toBe(PaymentStatus::Pending);
});
