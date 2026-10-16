<?php

use App\Enums\WithdrawalStatus;
use App\Filament\Resources\Withdrawals\Pages\ManageWithdrawals;
use App\Filament\Student\Pages\ReferralsAndCommission as StudentReferralsAndCommission;
use App\Filament\Teacher\Pages\ReferralsAndCommission;
use App\Models\BillingSetting;
use App\Models\StudentReferral;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    BillingSetting::create([
        'referral_enabled' => true,
        'teacher_reward_percent' => 25,
        'student_reward_percent' => 10,
        'referee_discount_percent' => 0,
        'refund_window_days' => 0,
        'student_reserve_amount' => 50,
        'student_min_withdrawal' => 20,
        'teacher_reserve_amount' => 100,
        'teacher_min_withdrawal' => 50,
        'bkash_number' => '01700000000',
    ]);

    $this->purchases = app(SubscriptionPurchaseService::class);
    $this->wallet = app(WalletService::class);
    $this->withdrawals = app(WithdrawalService::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 400]);

    /** A student under the teacher's referral buys the 400 plan; returns the payment. */
    $this->studentBuys = function (string $transactionId = 'TRX1') {
        $student = User::factory()->student()->create();
        StudentReferral::factory()->create(['student_id' => $student->id, 'referrer_id' => $this->teacher->id]);
        $payment = $this->purchases->request($student, $this->plan, [
            'payer_provider' => 'bkash', 'payer_number' => '01811111111', 'transaction_id' => $transactionId,
        ]);
        $this->purchases->approve($payment, $this->admin);
        $this->travel(1)->seconds();

        return $payment->refresh();
    };
});

describe('earning commission', function () {
    it('gives a teacher with a paid subscription their commission as cash in the wallet, which never expires', function () {
        subscribeToEverything($this->teacher);

        $payment = ($this->studentBuys)();

        expect($payment->referralReward->amount)->toBe(100.0)
            ->and($payment->referralReward->percent)->toEqual(25)
            ->and($payment->referralReward->expires_at)->toBeNull();

        $this->travel(3)->months();

        expect($this->wallet->balanceFor($this->teacher))->toBe(100.0);
    });

    it('gives no commission during the free trial or with no plan at all', function (bool $onTrial) {
        if ($onTrial) {
            Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => SubscriptionPlan::factory()->trial()]);
        }

        $payment = ($this->studentBuys)();

        expect($payment->referralReward)->toBeNull()
            ->and($payment->referrer_id)->toBe($this->teacher->id);
    })->with(['on the trial' => true, 'without a plan' => false]);

    it('shows the teacher that commission is paused while they have no paid subscription', function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(ReferralsAndCommission::class)->assertSee('Commission is paused');

        subscribeToEverything($this->teacher);

        livewire(ReferralsAndCommission::class)->assertDontSee('Commission is paused');
    });
});

describe('taking commission out', function () {
    beforeEach(function () {
        subscribeToEverything($this->teacher);
        // Two students buy: ৳200 commission in the wallet.
        ($this->studentBuys)('TRX1');
        ($this->studentBuys)('TRX2');
    });

    it('always keeps the reserve in the wallet', function () {
        expect($this->withdrawals->summaryFor($this->teacher))->toMatchArray([
            'balance' => 200.0,
            'reserve' => 100.0,
            'withdrawable' => 100.0,
            'minimum' => 50.0,
            'pending' => null,
        ]);

        expect(fn () => $this->withdrawals->request($this->teacher, 150, 'bkash', '01712345678'))
            ->toThrow(ValidationException::class, '100.00');
        expect(fn () => $this->withdrawals->request($this->teacher, 40, 'bkash', '01712345678'))
            ->toThrow(ValidationException::class, '50.00');
    });

    it('takes a request out of the wallet at once, and allows only one waiting at a time', function () {
        $withdrawal = $this->withdrawals->request($this->teacher, 80, 'bkash', '+880 1712-345678');

        expect($withdrawal->status)->toBe(WithdrawalStatus::Requested)
            ->and($withdrawal->account_number)->toBe('01712345678')
            ->and($this->wallet->balanceFor($this->teacher))->toBe(120.0);

        // Stored encrypted, never as the plain number.
        expect(DB::table('withdrawals')->value('account_number'))->not->toContain('01712345678');

        expect(fn () => $this->withdrawals->request($this->teacher, 20, 'bkash', '01712345678'))
            ->toThrow(ValidationException::class);
    });

    it('marks a request paid once, and puts a rejected one back in the wallet', function () {
        $paid = $this->withdrawals->request($this->teacher, 50, 'bkash', '01712345678');

        expect($this->withdrawals->markPaid($paid, $this->admin, 'BKX1'))->toBeTrue()
            ->and($this->withdrawals->markPaid($paid, $this->admin, 'BKX1'))->toBeFalse()
            ->and($this->withdrawals->reject($paid, $this->admin))->toBeFalse()
            ->and($paid->refresh()->transaction_id)->toBe('BKX1');

        $rejected = $this->withdrawals->request($this->teacher, 50, 'nagad', '01712345678');
        $this->withdrawals->reject($rejected, $this->admin, 'Wrong number');

        expect($rejected->refresh()->status)->toBe(WithdrawalStatus::Rejected)
            ->and($this->wallet->balanceFor($this->teacher))->toBe(150.0);
    });

    it('lets the reserve be spent on the teacher\'s own subscription', function () {
        $teacherPlan = SubscriptionPlan::factory()->pro()->create(['price' => 150]);

        $payment = $this->purchases->request($this->teacher, $teacherPlan, ['use_wallet' => true]);

        expect($payment->isSuccessful())->toBeTrue()
            ->and($this->wallet->balanceFor($this->teacher))->toBe(50.0)
            ->and($this->withdrawals->summaryFor($this->teacher)['withdrawable'])->toBe(0.0);
    });

    it('lets the teacher ask for a withdrawal from their page', function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(ReferralsAndCommission::class)
            ->assertSee('৳200.00')
            ->callAction('withdraw', data: ['amount' => 100, 'provider' => 'bkash', 'account_number' => '01712345678'])
            ->assertHasNoActionErrors()
            ->assertSee('waiting to be paid');

        expect(Withdrawal::sole()->amount)->toBe(100.0);
    });

    it('lets the admin pay or reject a request', function () {
        $first = $this->withdrawals->request($this->teacher, 50, 'bkash', '01712345678');
        $this->actingAs($this->admin);

        livewire(ManageWithdrawals::class)
            ->assertCanSeeTableRecords([$first])
            ->assertSee('01712345678')
            ->callAction(TestAction::make('markPaid')->table($first), data: ['transaction_id' => 'BKX9'])
            ->assertNotified();

        expect($first->refresh()->status)->toBe(WithdrawalStatus::Paid);

        $second = $this->withdrawals->request($this->teacher, 50, 'bkash', '01712345678');

        livewire(ManageWithdrawals::class)
            ->callAction(TestAction::make('reject')->table($second), data: ['note' => 'Not now'])
            ->assertNotified();

        expect($second->refresh()->status)->toBe(WithdrawalStatus::Rejected);
    });
});

describe('a student referrer', function () {
    beforeEach(function () {
        $this->friend = User::factory()->student()->create();

        $this->friendRefers = function (string $transactionId = 'TRX9') {
            $student = User::factory()->student()->create();
            StudentReferral::factory()->create(['student_id' => $student->id, 'referrer_id' => $this->friend->id]);
            $payment = $this->purchases->request($student, $this->plan, ['payer_provider' => 'bkash', 'payer_number' => '01811111111', 'transaction_id' => $transactionId]);
            $this->purchases->approve($payment, $this->admin);
            $this->travel(1)->seconds();

            return $payment->refresh();
        };
    });

    it('earns their own percentage, only with a paid subscription, never expiring', function () {
        expect(($this->friendRefers)('TRX8')->referralReward)->toBeNull();

        subscribeToEverything($this->friend);
        $payment = ($this->friendRefers)('TRX9');

        expect($payment->referralReward->amount)->toBe(40.0)
            ->and($payment->referralReward->expires_at)->toBeNull();
    });

    it('takes out what is above the student reserve, from their own page', function () {
        subscribeToEverything($this->friend);
        ($this->friendRefers)('TRX1');
        ($this->friendRefers)('TRX2');

        // ৳80 earned, ৳50 kept: ৳30 can be taken out.
        expect($this->withdrawals->summaryFor($this->friend))->toMatchArray(['balance' => 80.0, 'reserve' => 50.0, 'withdrawable' => 30.0, 'minimum' => 20.0]);

        $this->actingAs($this->friend);
        Filament::setCurrentPanel(Filament::getPanel('student'));

        livewire(StudentReferralsAndCommission::class)
            ->assertSee($this->friend->referral_code)
            ->callAction('withdraw', data: ['amount' => 30, 'provider' => 'nagad', 'account_number' => '01612345678'])
            ->assertHasNoActionErrors();

        expect(Withdrawal::sole()->user_id)->toBe($this->friend->id)
            ->and($this->wallet->balanceFor($this->friend))->toBe(50.0);
    });

    it('flags a withdrawal to a number a referred buyer paid from', function () {
        subscribeToEverything($this->friend);
        ($this->friendRefers)('TRX1');
        ($this->friendRefers)('TRX2');

        $flagged = $this->withdrawals->request($this->friend, 30, 'bkash', '01811111111');

        expect($flagged->number_used_by_referred_buyer)->toBeTrue();
    });
});

it('lets no staff member ask for a withdrawal — they are paid with their payout', function () {
    expect(fn () => $this->withdrawals->request(User::factory()->staff()->create(), 50, 'bkash', '01712345678'))
        ->toThrow(ValidationException::class);
});
