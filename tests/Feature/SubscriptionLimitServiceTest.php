<?php

use App\Enums\Difficulty;
use App\Enums\ExamType;
use App\Enums\PlanConditionType;
use App\Enums\PlanFeature;
use App\Models\BillingSetting;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->service = new SubscriptionLimitService;
    $this->classSubject = ClassSubject::factory()->create();

    $this->condition = fn (PlanConditionType $type, array $data): array => SubscriptionPlanFactory::condition($type, $data);

    /** An exam the user made, counted against the given subscription (none = the free plan). */
    $this->examBy = fn (User $user, ?Subscription $subscription = null, ExamType $type = ExamType::TeacherExam): Exam => Exam::factory()->create([
        'created_by' => $user->id,
        'exam_type' => $type,
        'subscription_id' => $subscription?->id,
    ]);
});

it('lets a user with no plan at all do nothing that needs one', function () {
    $teacher = User::factory()->teacher()->create();

    expect(fn () => $this->service->authorize($teacher, $this->classSubject))->toThrow(ValidationException::class);
    expect($this->service->allows($teacher, $this->classSubject))->toBeFalse();
    expect($this->service->monthlyAllowanceFor($teacher))->toBe(0);
});

it('grants what an active subscription allows, and nothing once it has expired', function () {
    $teacher = User::factory()->teacher()->create();
    $subscription = Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => SubscriptionPlan::factory()->pro()]);

    expect($this->service->authorize($teacher, $this->classSubject)->subscriptionId)->toBe($subscription->id);

    $subscription->update(['ends_at' => now()->subMinute()]);

    expect($this->service->allows($teacher, $this->classSubject))->toBeFalse();
});

it('treats a plan without a monthly limit as unlimited', function () {
    $teacher = User::factory()->teacher()->create();
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => SubscriptionPlan::factory()->pro()]);

    expect($this->service->monthlyAllowanceFor($teacher))->toBeNull();
});

it('falls back to the role\'s free plan for a user with no subscription', function () {
    SubscriptionPlan::factory()->free()->monthlyLimit(3)->create();
    SubscriptionPlan::factory()->free()->forStudents()->monthlyLimit(7)->create();
    $teacher = User::factory()->teacher()->create();

    $grant = $this->service->authorize($teacher, $this->classSubject);

    expect($grant->isFree())->toBeTrue()
        ->and($grant->monthlyExamLimit)->toBe(3);
});

it('falls back to the free plan once a paid subscription has run out', function () {
    SubscriptionPlan::factory()->free()->monthlyLimit(3)->create();
    $teacher = User::factory()->teacher()->create();
    Subscription::factory()->expired()->create(['user_id' => $teacher->id, 'plan_id' => SubscriptionPlan::factory()->pro()]);

    expect($this->service->monthlyAllowanceFor($teacher))->toBe(3);
});

it('adds the admin\'s bonus exams for a user who has given a phone number, but keeps unlimited unlimited', function () {
    BillingSetting::create(['phone_bonus_exams' => 2]);
    SubscriptionPlan::factory()->free()->monthlyLimit(3)->create();
    $withPhone = User::factory()->teacher()->create(['phone' => '01712345678']);
    $withoutPhone = User::factory()->teacher()->create();

    expect($this->service->monthlyAllowanceFor($withPhone))->toBe(5)
        ->and($this->service->monthlyAllowanceFor($withoutPhone))->toBe(3);

    $subscribed = User::factory()->teacher()->create(['phone' => '01812345678']);
    subscribeToEverything($subscribed);
    SubscriptionPlan::query()->where('is_default_free', true)->delete();

    expect($this->service->monthlyAllowanceFor($subscribed))->toBeNull();
});

it('reaches only the classes a plan lists', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->withConditions([
        ($this->condition)(PlanConditionType::Classes, ['class_ids' => [$this->classSubject->academic_class_id]]),
    ])->create();
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);
    $otherClass = ClassSubject::factory()->create(['subject_id' => $this->classSubject->subject_id]);

    expect($this->service->allows($teacher, $this->classSubject))->toBeTrue()
        ->and($this->service->allows($teacher, $otherClass))->toBeFalse();

    $accessible = $this->service->constrainToAccessible(ClassSubject::query(), $teacher)->pluck('id')->all();
    expect($accessible)->toBe([$this->classSubject->id]);
});

it('reaches the subject the buyer picked, and no other', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->withConditions([
        ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [], 'pick_count' => 1]),
    ])->create();
    Subscription::factory()->create([
        'user_id' => $teacher->id,
        'plan_id' => $plan->id,
        'selections' => ['class_ids' => [], 'subject_ids' => [$this->classSubject->subject_id]],
    ]);

    expect($this->service->allows($teacher, $this->classSubject))->toBeTrue()
        ->and($this->service->allows($teacher, ClassSubject::factory()->create()))->toBeFalse();
});

it('keeps a free student to the difficulties the free plan allows, and opens the rest with a plan covering the subject', function () {
    $student = User::factory()->student()->create();
    SubscriptionPlan::factory()->free()->forStudents()->withConditions([
        ($this->condition)(PlanConditionType::Difficulties, ['difficulties' => ['easy']]),
    ])->create();

    expect($this->service->allowedDifficultiesFor($student, $this->classSubject))->toBe([Difficulty::Easy]);
    expect(fn () => $this->service->authorize($student, $this->classSubject, difficulties: [Difficulty::Hard]))
        ->toThrow(ValidationException::class, 'Hard');

    subscribeToEverything($student);

    expect($this->service->allowedDifficultiesFor($student, $this->classSubject))->toBeNull();
    expect($this->service->authorize($student, $this->classSubject, difficulties: [Difficulty::Hard])->isFree())->toBeFalse();
});

it('refuses a feature the plan does not include', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->withConditions([
        ($this->condition)(PlanConditionType::Features, ['features' => [PlanFeature::Print->value]]),
    ])->create();
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    expect($this->service->allows($teacher, $this->classSubject, [PlanFeature::Print]))->toBeTrue()
        ->and($this->service->allows($teacher, $this->classSubject, [PlanFeature::OnlineExam]))->toBeFalse();
    expect(fn () => $this->service->authorize($teacher, $this->classSubject, [PlanFeature::OnlineExam]))
        ->toThrow(ValidationException::class);
});

it('allows a set number of exams a day, starting again the next day', function () {
    $this->freezeTime();
    $student = User::factory()->student()->create();
    SubscriptionPlan::factory()->free()->forStudents()->withConditions([
        ($this->condition)(PlanConditionType::DailyExamLimit, ['limit' => 1]),
    ])->create();

    ($this->examBy)($student, type: ExamType::SelfPractice);

    expect(fn () => $this->service->authorize($student, $this->classSubject))->toThrow(ValidationException::class);

    $this->travel(1)->days();

    expect($this->service->authorize($student, $this->classSubject)->isFree())->toBeTrue();
});

it('counts self-practice exams of every mode together, and starts the month again from zero', function () {
    $this->travelTo(now()->startOfMonth()->addDays(10));
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->forStudents()->monthlyLimit(2)->create();
    $subscription = Subscription::factory()->create(['user_id' => $student->id, 'plan_id' => $plan->id]);

    foreach (['auto', 'manual'] as $mode) {
        Exam::factory()->selfPractice()->create(['created_by' => $student->id, 'generation_mode' => $mode, 'subscription_id' => $subscription->id]);
    }

    expect(fn () => $this->service->authorize($student, $this->classSubject))->toThrow(ValidationException::class);

    $this->travel(1)->months();

    expect($this->service->authorize($student, $this->classSubject)->subscriptionId)->toBe($subscription->id);
});

it('counts each exam against the plan that allowed it, so a paid plan never uses up the free one', function () {
    $student = User::factory()->student()->create();
    SubscriptionPlan::factory()->free()->forStudents()->withConditions([
        ($this->condition)(PlanConditionType::DailyExamLimit, ['limit' => 1]),
    ])->create();
    $mathsOnly = SubscriptionPlan::factory()->forStudents()->withConditions([
        ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [$this->classSubject->subject_id]]),
    ])->create();
    $subscription = Subscription::factory()->create(['user_id' => $student->id, 'plan_id' => $mathsOnly->id]);
    $physics = ClassSubject::factory()->create();

    // Maths goes on the paid plan, however many.
    foreach (range(1, 3) as $i) {
        $grant = $this->service->authorize($student, $this->classSubject);
        expect($grant->subscriptionId)->toBe($subscription->id);
        ($this->examBy)($student, $subscription, ExamType::SelfPractice);
    }

    // Physics is only on the free plan, whose one exam today is still there.
    expect($this->service->authorize($student, $physics)->isFree())->toBeTrue();
});

it('names nothing a plan doesn\'t limit when describing it', function () {
    $plan = SubscriptionPlan::factory()->withConditions([
        ($this->condition)(PlanConditionType::Classes, ['class_ids' => [$this->classSubject->academic_class_id]]),
        ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [], 'pick_count' => 1]),
        ($this->condition)(PlanConditionType::DailyExamLimit, ['limit' => 1]),
    ])->create();

    expect($plan->planConditions()->descriptions())->toBe([
        'Classes: '.$this->classSubject->academicClass->name,
        'Any 1 subject of your choice',
        '1 exam a day',
    ]);
});
