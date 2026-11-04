<?php

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\StudentReferral;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\SubscriptionPurchaseService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->grants = app(SubscriptionPurchaseService::class);
    $this->superAdmin = User::factory()->superAdmin()->create();

    BillingSetting::create([
        'referral_enabled' => true,
        'teacher_reward_percent' => 20,
        'referee_discount_percent' => 10,
        'refund_window_days' => 7,
    ]);
});

/**
 * A student under a Teacher's referral, the Teacher on a paid plan (so
 * they earn).
 */
function studentReferredByATeacher(): User
{
    $teacher = User::factory()->teacher()->create();
    subscribeToEverything($teacher);

    $student = User::factory()->student()->create();
    StudentReferral::factory()->create(['student_id' => $student->id, 'referrer_id' => $teacher->id]);

    return $student;
}

it('gives a plan as a gift: active at once with its conditions, no refund, no reward, and the user is told', function () {
    $student = studentReferredByATeacher();
    $plan = SubscriptionPlan::factory()->forStudents()->monthlyLimit(7)->create(['price' => 300, 'duration_unit' => 'months', 'duration_length' => 1]);

    $payment = $this->grants->grant($this->superAdmin, $student, $plan, ['gift' => true, 'amount' => 300, 'note' => 'Contest winner']);

    $subscription = $payment->subscription;

    expect($payment->status)->toBe(PaymentStatus::Success)
        ->and($payment->gateway)->toBe(PaymentGateway::Admin)
        ->and($payment->amount)->toBe(0.0)
        ->and($payment->discount_amount)->toBe(0.0)
        ->and($payment->isGift())->toBeTrue()
        ->and($payment->refundable_until)->toBeNull()
        ->and($payment->isRefundable())->toBeFalse()
        ->and($payment->reviewed_by)->toBe($this->superAdmin->id)
        ->and($payment->review_note)->toBe('Contest winner')
        ->and($subscription->is_gift)->toBeTrue()
        ->and($subscription->conditions)->toBe($plan->conditions)
        ->and($subscription->ends_at->isSameDay(now()->addMonth()))->toBeTrue()
        ->and(ReferralReward::count())->toBe(0)
        ->and($student->notifications()->count())->toBe(1);
});

it('tells the user in their own language, though the Admin panel runs in English', function () {
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['name' => 'Pro']);

    // As on the real site: Bangla for everyone, the Admin panel in English.
    config(['app.site_locale' => 'bn']);
    app()->setLocale('en');

    $this->grants->grant($this->superAdmin, $student, $plan, ['gift' => true, 'note' => 'x']);

    expect($student->notifications()->sole()->data['title'])->toBe(__('You have been given the :plan plan', ['plan' => 'Pro'], 'bn'));
});

it('gives a plan for money taken outside the system: no discount, the referrer earns on it, refundable', function () {
    $student = studentReferredByATeacher();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->create(['price' => 500]);

    $payment = $this->grants->grant($this->superAdmin, $student, $plan, [
        'amount' => 450,
        'payer_provider' => 'bkash',
        'transaction_id' => 'CASH-1',
        'note' => 'Paid at the office',
    ]);

    $reward = ReferralReward::sole();

    expect($payment->amount)->toBe(450.0)
        ->and($payment->list_price)->toBe(500.0)
        ->and($payment->discount_amount)->toBe(0.0)
        ->and($payment->isGift())->toBeFalse()
        ->and($payment->subscription->is_gift)->toBeFalse()
        ->and($payment->isRefundable())->toBeTrue()
        ->and($reward->payment_id)->toBe($payment->id)
        ->and($reward->amount)->toEqual(round(450 * $reward->percent / 100, 2));
});

it('lets the length be set, and adds a renewal to the end of the current period', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create();

    $first = $this->grants->grant($this->superAdmin, $teacher, $plan, ['gift' => true, 'days' => 45, 'note' => 'Trial extension']);
    $second = $this->grants->grant($this->superAdmin, $teacher, $plan, ['gift' => true, 'note' => 'One more month']);

    expect($first->subscription->ends_at->isSameDay(now()->addDays(45)))->toBeTrue()
        ->and($second->subscription->ends_at->isSameDay(now()->addDays(45)->addMonth()))->toBeTrue();
});

it('gives any plan of the role — off sale or the trial again — but not the free plan or another role\'s', function () {
    $teacher = User::factory()->teacher()->create();
    $trial = SubscriptionPlan::factory()->trial()->create();
    $offSale = SubscriptionPlan::factory()->pro()->create(['is_active' => false]);

    $this->grants->grant($this->superAdmin, $teacher, $trial, ['gift' => true, 'note' => 'a']);
    $this->grants->grant($this->superAdmin, $teacher, $trial, ['gift' => true, 'note' => 'b']);
    $this->grants->grant($this->superAdmin, $teacher, $offSale, ['gift' => true, 'note' => 'c']);

    expect($teacher->subscriptions()->count())->toBe(3);

    $free = SubscriptionPlan::factory()->free()->create();
    $studentPlan = SubscriptionPlan::factory()->pro()->forStudents()->create();

    expect(fn () => $this->grants->grant($this->superAdmin, $teacher, $free, ['gift' => true, 'note' => 'x']))->toThrow(ValidationException::class)
        ->and(fn () => $this->grants->grant($this->superAdmin, $teacher, $studentPlan, ['gift' => true, 'note' => 'x']))->toThrow(ValidationException::class);
});

it('lets only the Super Admin give a subscription', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create();

    expect(fn () => $this->grants->grant(User::factory()->admin()->create(), $teacher, $plan, ['gift' => true, 'note' => 'x']))
        ->toThrow(ValidationException::class)
        ->and(Subscription::count())->toBe(0);
});

it('needs an amount unless it is a gift, and a reason', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create();

    expect(fn () => $this->grants->grant($this->superAdmin, $teacher, $plan, ['amount' => 0, 'note' => 'x']))->toThrow(ValidationException::class)
        ->and(fn () => $this->grants->grant($this->superAdmin, $teacher, $plan, ['gift' => true, 'note' => ' ']))->toThrow(ValidationException::class)
        ->and(Payment::count())->toBe(0);
});

it('checks the class picked against the plan, and moves a student to it', function () {
    $student = User::factory()->student()->create();
    $class = AcademicClass::factory()->create();
    $plan = SubscriptionPlan::factory()->pro()->forStudents()->withConditions([
        ['type' => 'classes', 'data' => ['class_ids' => [$class->id], 'pick_count' => 1]],
    ])->create();

    expect(fn () => $this->grants->grant($this->superAdmin, $student, $plan, ['gift' => true, 'note' => 'x', 'class_ids' => AcademicClass::factory()->create()->id]))
        ->toThrow(ValidationException::class);

    $payment = $this->grants->grant($this->superAdmin, $student, $plan, ['gift' => true, 'note' => 'x', 'class_ids' => $class->id]);

    expect($payment->subscription->selections['class_ids'])->toBe([$class->id])
        ->and($student->fresh()->academic_class_id)->toBe($class->id);
});

it('does not make a gift\'s holder a paying customer, nor count it as their first purchase', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create();

    $this->grants->grant($this->superAdmin, $teacher, $plan, ['gift' => true, 'note' => 'x']);

    expect(app(ReferralService::class)->earnsCommission($teacher))->toBeFalse();

    $this->grants->grant($this->superAdmin, $teacher, $plan, ['amount' => 500, 'note' => 'paid']);

    expect(app(ReferralService::class)->earnsCommission($teacher))->toBeTrue();
});

it('lets the Super Admin give a subscription from the subscriptions list and a teacher\'s row', function () {
    $this->seed(RoleSeeder::class);
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create();

    $this->actingAs($this->superAdmin);

    livewire(ManageSubscriptions::class)
        ->callAction('grantSubscription', data: [
            'user_id' => $teacher->id,
            'plan_id' => $plan->id,
            'gift' => true,
            'note' => 'Welcome',
        ])
        ->assertHasNoActionErrors();

    livewire(ManageTeachers::class)
        ->callAction(TestAction::make('grantSubscription')->table($teacher), data: [
            'plan_id' => $plan->id,
            'amount' => 500,
            'note' => 'Paid in cash',
        ])
        ->assertHasNoActionErrors();

    expect($teacher->subscriptions()->count())->toBe(2)
        ->and($teacher->payments()->pluck('amount')->sort()->values()->all())->toBe([0.0, 500.0]);
});

it('does not show other Admins the way to give a subscription', function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());

    livewire(ManageSubscriptions::class)
        ->assertActionHidden('grantSubscription')
        ->assertActionDoesNotExist('create');
});
