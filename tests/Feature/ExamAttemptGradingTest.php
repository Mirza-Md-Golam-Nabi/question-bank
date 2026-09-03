<?php

use App\Enums\ExamAttemptStatus;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('auto-grades mcq answers and sums the correct ones into total_score', function () {
    $exam = Exam::factory()->create();
    $correctQuestion = Question::factory()->for(Chapter::factory())->approved()->create(['correct_answer' => 'a', 'marks' => 2]);
    $wrongQuestion = Question::factory()->for(Chapter::factory())->approved()->create(['correct_answer' => 'b', 'marks' => 3]);

    $exam->questions()->attach([
        $correctQuestion->id => ['order_index' => 1, 'marks_override' => null],
        $wrongQuestion->id => ['order_index' => 2, 'marks_override' => null],
    ]);

    $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
    $attempt->answers()->create(['question_id' => $correctQuestion->id, 'student_answer' => 'a']);
    $attempt->answers()->create(['question_id' => $wrongQuestion->id, 'student_answer' => 'a']);

    $attempt->submitAndAutoGrade();

    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted);
    expect((float) $attempt->total_score)->toBe(2.0);
    expect($attempt->answers()->where('question_id', $correctQuestion->id)->first()->is_correct)->toBeTrue();
    expect($attempt->answers()->where('question_id', $wrongQuestion->id)->first()->is_correct)->toBeFalse();
});

it('respects a marks_override when auto-grading', function () {
    $exam = Exam::factory()->create();
    $question = Question::factory()->for(Chapter::factory())->approved()->create(['correct_answer' => 'a', 'marks' => 2]);

    $exam->questions()->attach([$question->id => ['order_index' => 1, 'marks_override' => 10]]);

    $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
    $attempt->answers()->create(['question_id' => $question->id, 'student_answer' => 'a']);

    $attempt->submitAndAutoGrade();

    expect((float) $attempt->total_score)->toBe(10.0);
});

it('leaves cq answers ungraded for manual grading', function () {
    $exam = Exam::factory()->create();
    $cqQuestion = Question::factory()->cq()->for(Chapter::factory())->approved()->create();

    $exam->questions()->attach([$cqQuestion->id => ['order_index' => 1, 'marks_override' => null]]);

    $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
    $attempt->answers()->create(['question_id' => $cqQuestion->id, 'student_answer' => 'My essay answer']);

    $attempt->submitAndAutoGrade();

    $answer = $attempt->answers()->first();
    expect($answer->is_correct)->toBeNull();
    expect((float) $answer->obtained_marks)->toBe(0.0);
});
