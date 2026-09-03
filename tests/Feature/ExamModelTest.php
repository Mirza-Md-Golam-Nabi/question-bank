<?php

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
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

it('blocks publishing once the monthly free limit is reached', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 1]);
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    Exam::factory()->create(['created_by' => $teacher->id, 'exam_type' => ExamType::TeacherExam]);

    expect($this->service->hasReachedMonthlyLimit($teacher, ExamType::TeacherExam))->toBeTrue();

    $this->actingAs($teacher);
    $newExam = Exam::factory()->make(['created_by' => $teacher->id, 'exam_type' => ExamType::TeacherExam]);
    $newExam->created_by = $teacher->id;
    $newExam->save();

    expect($teacher->can('publish', $newExam))->toBeFalse();
});

it('allows publishing when under the monthly limit', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 5]);
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    $exam = Exam::factory()->create(['created_by' => $teacher->id, 'exam_type' => ExamType::TeacherExam]);

    expect($teacher->can('publish', $exam))->toBeTrue();
});

it('counts auto and manual self-practice exams together toward the same monthly limit', function () {
    $student = User::factory()->student()->create();
    $plan = SubscriptionPlan::factory()->forStudents()->create(['monthly_exam_limit' => 2]);
    Subscription::factory()->create(['user_id' => $student->id, 'plan_id' => $plan->id]);

    Exam::factory()->selfPractice()->create(['created_by' => $student->id, 'generation_mode' => 'auto']);
    Exam::factory()->selfPractice()->create(['created_by' => $student->id, 'generation_mode' => 'manual']);

    expect($this->service->hasReachedMonthlyLimit($student, ExamType::SelfPractice))->toBeTrue();
});
