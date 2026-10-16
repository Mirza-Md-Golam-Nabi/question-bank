<?php

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\PlanConditionType;
use App\Enums\PlanFeature;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->service = new SubscriptionLimitService;
});

it('publishing a teacher exam generates a share token', function () {
    $exam = Exam::factory()->create();

    $exam->publish();

    expect($exam->status)->toBe(ExamStatus::Published);
    expect($exam->share_token)->not->toBeNull();
    expect(strlen($exam->share_token))->toBeGreaterThanOrEqual(32);
});

it('publishing a self-practice exam never generates a share token', function () {
    $exam = Exam::factory()->selfPractice()->create();

    $exam->publish();

    expect($exam->share_token)->toBeNull();
});

it('recalculates total marks from attached questions, respecting a marks override', function () {
    $exam = Exam::factory()->create();
    $q1 = Question::factory()->for(Chapter::factory())->approved()->create(['marks' => 2]);
    $q2 = Question::factory()->for(Chapter::factory())->approved()->create(['marks' => 3]);

    $exam->questions()->attach([
        $q1->id => ['order_index' => 1, 'marks_override' => null],
        $q2->id => ['order_index' => 2, 'marks_override' => 5],
    ]);

    $exam->recalculateTotalMarks();

    expect((float) $exam->refresh()->total_marks)->toBe(7.0);
});

it('lets a teacher publish only while a plan of theirs includes online exams', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->create(['created_by' => $teacher->id, 'exam_type' => ExamType::TeacherExam]);

    // No plan at all.
    expect($teacher->can('publish', $exam))->toBeFalse();

    $printOnly = SubscriptionPlan::factory()->withConditions([
        SubscriptionPlanFactory::condition(PlanConditionType::Features, ['features' => [PlanFeature::Print->value]]),
    ])->create();
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $printOnly->id]);

    expect($teacher->fresh()->can('publish', $exam))->toBeFalse();

    subscribeToEverything($teacher);

    expect($teacher->fresh()->can('publish', $exam))->toBeTrue();
});
