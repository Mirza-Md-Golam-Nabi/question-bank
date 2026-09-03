<?php

use App\Enums\StaffEarningStatus;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\QuestionRate;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('creates a staff earning with the snapshotted rate the moment a staff question is approved', function () {
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 15]);

    $staff = User::factory()->staff()->create();
    StaffProfile::factory()->for($staff, 'user')->create(['total_earned' => 0, 'total_questions_approved' => 0]);

    $question = Question::factory()->for(Chapter::factory())->create(['created_by' => $staff->id]);

    $question->approve($this->admin);

    $earning = $question->staffEarnings()->first();

    expect($earning)->not->toBeNull();
    expect((float) $earning->amount)->toBe(15.0);
    expect($earning->status)->toBe(StaffEarningStatus::PendingPayout);
    expect((float) $staff->staffProfile->refresh()->total_earned)->toBe(15.0);
    expect($staff->staffProfile->total_questions_approved)->toBe(1);
});

it('never creates an earning for a teacher-owned question', function () {
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 15]);
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->for(Chapter::factory())->create(['created_by' => $teacher->id]);

    $question->approve($this->admin);

    expect($question->staffEarnings()->count())->toBe(0);
});

it('creates no earning for a rejected question', function () {
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 15]);
    $staff = User::factory()->staff()->create();
    $question = Question::factory()->for(Chapter::factory())->create(['created_by' => $staff->id]);

    $question->reject($this->admin, 'Not good enough');

    expect($question->staffEarnings()->count())->toBe(0);
});

it('snapshots the rate at approval time and ignores later rate changes', function () {
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 10, 'effective_from' => now()->subDays(5)]);

    $staff = User::factory()->staff()->create();
    $question = Question::factory()->for(Chapter::factory())->create(['created_by' => $staff->id]);
    $question->approve($this->admin);

    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 50, 'effective_from' => now()]);

    $earning = $question->staffEarnings()->first();
    expect((float) $earning->amount)->toBe(10.0);
});

it('prefers a subject-specific rate over the default rate', function () {
    $classSubject = ClassSubject::factory()->create();
    QuestionRate::factory()->create(['subject_id' => null, 'rate_amount' => 10]);
    QuestionRate::factory()->create(['subject_id' => $classSubject->subject_id, 'rate_amount' => 25]);

    $staff = User::factory()->staff()->create();
    $chapter = Chapter::factory()->create(['class_subject_id' => $classSubject->id]);
    $question = Question::factory()->for($chapter)->create(['created_by' => $staff->id]);

    $question->approve($this->admin);

    expect((float) $question->staffEarnings()->first()->amount)->toBe(25.0);
});
