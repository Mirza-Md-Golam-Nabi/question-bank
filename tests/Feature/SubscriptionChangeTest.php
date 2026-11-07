<?php

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PlanChangeRefundMethod;
use App\Enums\PlanConditionType;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Student\Pages\MySubscription as StudentMySubscription;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\StudentReferral;
use App\Models\Subscription;
use App\Models\SubscriptionMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionChangeService;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->changes = app(SubscriptionChangeService::class);
    $this->purchases = app(SubscriptionPurchaseService::class);
    $this->admin = User::factory()->admin()->create();
    $this->superAdmin = User::factory()->superAdmin()->create();

    BillingSetting::create([
        'referral_enabled' => true,
        'teacher_reward_percent' => 20,
        'refund_window_days' => 7,
        'plan_downgrade_fee_percent' => 10,
        'bkash_number' => '01700000000',
    ]);

    // The prices from the request: 1 month ৳29, 3 months ৳79, 1 year ৳290.
    $plan = fn (string $name, int $price, string $unit, int $length) => SubscriptionPlan::factory()->forStudents()->monthlyLimit(30)->create([
        'name' => $name, 'price' => $price, 'duration_unit' => $unit, 'duration_length' => $length,
    ]);
    $this->monthly = $plan('Monthly', 29, 'months', 1);
    $this->quarterly = $plan('Quarterly', 79, 'months', 3);
    $this->yearly = $plan('Yearly', 290, 'years', 1);
});

/**
 * The student's subscription to `$plan`, bought (and approved) now.
 */
function boughtSubscription(User $student, SubscriptionPlan $plan, string $transactionId = 'BUY1'): Subscription
{
    $payment = app(SubscriptionPurchaseService::class)->request($student, $plan, [
        'payer_provider' => 'bkash', 'payer_number' => '01811111111', 'transaction_id' => $transactionId,
    ]);
    app(SubscriptionPurchaseService::class)->approve($payment);

    return $payment->refresh()->subscription;
}

it('upgrades for the price difference, counting the new length from the start, on the same subscription and with no new commission', function () {
    $teacher = User::factory()->teacher()->create();
    subscribeToEverything($teacher);
    $student = User::factory()->student()->create();
    StudentReferral::factory()->create(['student_id' => $student->id, 'referrer_id' => $teacher->id]);

    $subscription = boughtSubscription($student, $this->quarterly);
    $startedAt = $subscription->starts_at;
    Exam::factory()->create(['subscription_id' => $subscription->id]);
    $this->travel(1)->months();

    expect($this->changes->quote($subscription, $this->yearly))
        ->toMatchArray(['credit' => 79.0, 'price' => 290.0, 'pay' => 211.0, 'refund' => 0.0]);

    $payment = $this->changes->request($student, $subscription, $this->yearly, [
        'payer_provider' => 'bkash', 'payer_number' => '01822222222', 'transaction_id' => 'UP1',
    ]);

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->type)->toBe(PaymentType::PlanChange)
        ->and($payment->amount)->toBe(211.0)
        ->and($payment->previous_plan_id)->toBe($this->quarterly->id)
        ->and($payment->referrer_id)->toBeNull()
        ->and($subscription->refresh()->plan_id)->toBe($this->quarterly->id);

    expect($this->purchases->approve($payment, $this->admin))->toBeTrue();

    $subscription->refresh();

    expect($subscription->plan_id)->toBe($this->yearly->id)
        ->and($subscription->conditions)->toBe($this->yearly->conditions)
        ->and($subscription->ends_at->equalTo($startedAt->copy()->addYear()))->toBeTrue()
        ->and($subscription->plan_price)->toBe(290.0)
        ->and($subscription->exams()->count())->toBe(1)
        ->and(Subscription::where('user_id', $student->id)->count())->toBe(1)
        ->and(ReferralReward::count())->toBe(1)
        ->and(ReferralReward::first()->payment->type)->toBe(PaymentType::Purchase)
        ->and($student->notifications()->where('data->title', 'like', '%Quarterly%Yearly%')->exists())->toBeTrue();
});

it('gives back the difference less the Admin fee on a downgrade — into the wallet or by bKash, as the Admin chooses', function () {
    $student = User::factory()->student()->create();
    $subscription = boughtSubscription($student, $this->yearly);

    expect($this->changes->quote($subscription, $this->quarterly))
        ->toMatchArray(['pay' => 0.0, 'refund' => 189.9]);

    // Where to send it is asked for.
    expect(fn () => $this->changes->request($student, $subscription, $this->quarterly, []))
        ->toThrow(ValidationException::class);

    $payment = $this->changes->request($student, $subscription, $this->quarterly, ['payer_provider' => 'bkash', 'payer_number' => '01833333333']);

    expect($payment->refund_amount)->toBe(189.9)
        ->and($payment->amount)->toBe(0.0)
        ->and($payment->payer_number)->toBe('01833333333');

    // How it was given back must be said.
    expect(fn () => $this->purchases->approve($payment, $this->admin))->toThrow(ValidationException::class);

    $this->purchases->approve($payment, $this->admin, PlanChangeRefundMethod::Wallet);

    $subscription->refresh();

    expect($payment->refresh()->refund_method)->toBe(PlanChangeRefundMethod::Wallet)
        ->and(app(WalletService::class)->balanceFor($student))->toBe(189.9)
        ->and($subscription->plan_id)->toBe($this->quarterly->id)
        ->and($subscription->plan_price)->toBe(79.0)
        ->and($subscription->ends_at->equalTo($subscription->starts_at->copy()->addMonths(3)))->toBeTrue();
});

it('sends a School\'s downgrade money by bKash only, since a School has no wallet', function () {
    $school = User::factory()->school()->create();
    $plan = fn (int $price, int $months) => SubscriptionPlan::factory()->create([
        'target_role' => 'school', 'price' => $price, 'duration_unit' => 'months', 'duration_length' => $months,
    ]);
    $big = $plan(1000, 12);
    $small = $plan(300, 3);
    $subscription = Subscription::factory()->create(['user_id' => $school->id, 'plan_id' => $big->id, 'plan_price' => 1000, 'starts_at' => now()]);

    $payment = $this->changes->request($school, $subscription, $small, ['payer_provider' => 'bkash', 'payer_number' => '01844444444']);

    expect(fn () => $this->purchases->approve($payment, $this->admin, PlanChangeRefundMethod::Wallet))->toThrow(ValidationException::class);

    $this->purchases->approve($payment, $this->admin, PlanChangeRefundMethod::MobileBanking);

    expect($subscription->refresh()->plan_id)->toBe($small->id)
        ->and($payment->refresh()->refund_method)->toBe(PlanChangeRefundMethod::MobileBanking);
});

it('refuses a plan shorter than the time already used, and the plan held', function () {
    $student = User::factory()->student()->create();
    $subscription = boughtSubscription($student, $this->yearly);
    $this->travel(4)->months();

    expect(fn () => $this->changes->request($student, $subscription, $this->quarterly, ['payer_provider' => 'bkash', 'payer_number' => '01833333333']))
        ->toThrow(ValidationException::class, 'shorter than the time already used');

    expect(fn () => $this->changes->request($student, $subscription, $this->yearly, []))
        ->toThrow(ValidationException::class);
});

it('keeps group members on the subscription, and refuses a plan without room for them', function () {
    $seats = fn (int $count) => [SubscriptionPlanFactory::condition(PlanConditionType::Seats, ['count' => $count])];
    $teacher = User::factory()->teacher()->create();
    $group = SubscriptionPlan::factory()->withConditions($seats(3))->create(['price' => 500, 'duration_unit' => 'years', 'duration_length' => 1]);
    $bigGroup = SubscriptionPlan::factory()->withConditions($seats(5))->create(['price' => 800, 'duration_unit' => 'years', 'duration_length' => 1]);
    $solo = SubscriptionPlan::factory()->create(['price' => 300, 'duration_unit' => 'years', 'duration_length' => 1]);
    $subscription = Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $group->id, 'plan_price' => 500, 'starts_at' => now()]);
    $member = SubscriptionMember::factory()->create(['subscription_id' => $subscription->id]);

    expect(fn () => $this->changes->request($teacher, $subscription, $solo, ['payer_provider' => 'bkash', 'payer_number' => '01833333333']))
        ->toThrow(ValidationException::class, 'take someone off first');

    $payment = $this->changes->request($teacher, $subscription, $bigGroup, ['payer_provider' => 'bkash', 'payer_number' => '01822222222', 'transaction_id' => 'GRP1']);
    $this->purchases->approve($payment, $this->admin);

    expect($member->refresh()->subscription_id)->toBe($subscription->id)
        ->and($subscription->refresh()->seats())->toBe(5);
});

it('counts a gift as nothing paid: an upgrade costs the whole new price and ends the gift', function () {
    $student = User::factory()->student()->create();
    $subscription = $this->purchases->grant($this->superAdmin, $student, $this->quarterly, ['gift' => true, 'note' => 'Prize'])->subscription;

    expect($subscription->plan_price)->toBe(0.0)
        ->and($this->changes->quote($subscription, $this->yearly)['pay'])->toBe(290.0)
        ->and($this->changes->quote($subscription, $this->monthly)['refund'])->toBe(0.0);

    $payment = $this->changes->request($student, $subscription, $this->yearly, ['payer_provider' => 'bkash', 'payer_number' => '01822222222', 'transaction_id' => 'GIFTUP']);
    $this->purchases->approve($payment, $this->admin);

    expect($subscription->refresh()->is_gift)->toBeFalse()
        ->and($subscription->plan_price)->toBe(290.0);
});

it('makes a change with no money either way at once', function () {
    $student = User::factory()->student()->create();
    $subscription = boughtSubscription($student, $this->quarterly);
    $samePrice = SubscriptionPlan::factory()->forStudents()->monthlyLimit(60)->create(['price' => 79, 'duration_unit' => 'months', 'duration_length' => 3]);

    $payment = $this->changes->request($student, $subscription, $samePrice, []);

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($subscription->refresh()->plan_id)->toBe($samePrice->id);
});

it('no longer refunds a subscription once its plan has been changed', function () {
    $student = User::factory()->student()->create();
    $subscription = boughtSubscription($student, $this->quarterly);
    $purchase = $subscription->payments()->first();

    expect($purchase->isRefundable())->toBeTrue();

    $change = $this->changes->request($student, $subscription, $this->yearly, ['payer_provider' => 'bkash', 'payer_number' => '01822222222', 'transaction_id' => 'UP2']);
    $this->purchases->approve($change, $this->admin);

    expect($purchase->refresh()->isRefundable())->toBeFalse()
        ->and($change->refresh()->isRefundable())->toBeFalse();
});

it('lets the Super Admin change the plan at once, waiving or taking any amount, with a reason the user sees', function () {
    $student = User::factory()->student()->create();
    $subscription = boughtSubscription($student, $this->quarterly);

    expect(fn () => $this->changes->changeAsSuperAdmin($this->admin, $subscription, $this->yearly, ['amount' => 0, 'note' => 'x']))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->changes->changeAsSuperAdmin($this->superAdmin, $subscription, $this->yearly, ['amount' => 0]))
        ->toThrow(ValidationException::class, 'Write why');

    $payment = $this->changes->changeAsSuperAdmin($this->superAdmin, $subscription, $this->yearly, ['amount' => 0, 'note' => 'Promised upgrade']);

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->reviewed_by)->toBe($this->superAdmin->id)
        ->and($subscription->refresh()->plan_id)->toBe($this->yearly->id)
        // Nothing more was paid, so it still counts as what was paid.
        ->and($subscription->plan_price)->toBe(79.0)
        ->and($student->notifications()->where('data->body', 'like', '%Promised upgrade%')->exists())->toBeTrue();
});

describe('classes and subjects', function () {
    beforeEach(function () {
        $this->classes = AcademicClass::factory()->count(2)->create();
        $this->pickClass = SubscriptionPlan::factory()->forStudents()->withConditions([
            SubscriptionPlanFactory::condition(PlanConditionType::Classes, ['class_ids' => [], 'pick_count' => 1]),
        ])->create(['price' => 100, 'duration_unit' => 'months', 'duration_length' => 1]);
        $this->student = User::factory()->student()->create();
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->student->id,
            'plan_id' => $this->pickClass->id,
            'selections' => ['class_ids' => [$this->classes[0]->id], 'subject_ids' => []],
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    });

    it('lets the owner correct them while nothing has been done with it', function () {
        $this->changes->changeSelections($this->student, $this->subscription, ['class_ids' => $this->classes[1]->id]);

        expect($this->subscription->refresh()->selections['class_ids'])->toBe([$this->classes[1]->id])
            ->and($this->student->refresh()->academic_class_id)->toBe($this->classes[1]->id)
            ->and($this->student->notifications()->count())->toBe(0);
    });

    it('leaves a used one to the Super Admin, who gives a reason the user sees', function () {
        Exam::factory()->create(['subscription_id' => $this->subscription->id]);

        expect($this->changes->canChangeSelections($this->student, $this->subscription))->toBeFalse()
            ->and(fn () => $this->changes->changeSelections($this->student, $this->subscription, ['class_ids' => $this->classes[1]->id]))
            ->toThrow(ValidationException::class, 'only the Super Admin')
            ->and($this->changes->canChangeSelections($this->admin, $this->subscription))->toBeFalse();

        $this->changes->changeSelections($this->superAdmin, $this->subscription, ['class_ids' => $this->classes[1]->id, 'note' => 'Picked the wrong class']);

        expect($this->subscription->refresh()->selections['class_ids'])->toBe([$this->classes[1]->id])
            ->and($this->student->notifications()->count())->toBe(1);
    });

    it('works from the Super Admin\'s subscriptions list', function () {
        $this->actingAs($this->superAdmin);

        livewire(ManageSubscriptions::class)
            ->callAction(TestAction::make('changeSelections')->table($this->subscription), data: [
                'class_ids' => $this->classes[1]->id,
                'note' => 'Wrong class',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified();

        expect($this->subscription->refresh()->selections['class_ids'])->toBe([$this->classes[1]->id]);
    });
});

it('lets a student ask for an upgrade from their subscription page, approved from the payments list', function () {
    $student = User::factory()->student()->create();
    $subscription = boughtSubscription($student, $this->quarterly);

    $this->actingAs($student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMySubscription::class)
        ->assertSee(__('Change my subscription'))
        ->callAction(TestAction::make('changePlan')->arguments(['subscription' => $subscription->id]), data: [
            'plan_id' => $this->yearly->id,
            'payer_provider' => 'bkash',
            'payer_number' => '01822222222',
            'transaction_id' => 'PAGE1',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $payment = Payment::where('type', PaymentType::PlanChange)->sole();

    expect($payment->amount)->toBe(211.0);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(ManagePayments::class)
        ->callAction(TestAction::make('approve')->table($payment))
        ->assertNotified();

    expect($subscription->refresh()->plan_id)->toBe($this->yearly->id);
});

it('lets the Super Admin change a plan from the subscriptions list, and not another Admin', function () {
    $student = User::factory()->student()->create();
    $subscription = boughtSubscription($student, $this->yearly);

    $this->actingAs($this->admin);
    livewire(ManageSubscriptions::class)->assertActionHidden(TestAction::make('changePlan')->table($subscription));

    $this->actingAs($this->superAdmin);
    livewire(ManageSubscriptions::class)
        ->callAction(TestAction::make('changePlan')->table($subscription), data: [
            'plan_id' => $this->quarterly->id,
            'refund_amount' => 150,
            'refund_method' => PlanChangeRefundMethod::Wallet->value,
            'note' => 'Asked by phone',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($subscription->refresh()->plan_id)->toBe($this->quarterly->id)
        ->and(app(WalletService::class)->balanceFor($student))->toBe(150.0);
});
