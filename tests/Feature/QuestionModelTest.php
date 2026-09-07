<?php

use App\Enums\QuestionStatus;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('auto approves a question when the acting user is an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);
    $question = Question::factory()->for(Chapter::factory())->create(['created_by' => $admin->id]);

    expect($question->status)->toBe(QuestionStatus::Approved);
    expect($question->approved_by)->toBe($admin->id);
});

it('defaults a teacher-created question to pending', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher);
    $question = Question::factory()->for(Chapter::factory())->create(['created_by' => $teacher->id]);

    expect($question->status)->toBe(QuestionStatus::Pending);
    expect($question->approved_by)->toBeNull();
});

it('shows an owner their own question and hides other pending questions from other teachers', function () {
    $owner = User::factory()->teacher()->create();
    $otherTeacher = User::factory()->teacher()->create();
    $chapter = Chapter::factory()->create();

    $ownPending = Question::factory()->for($chapter)->create(['created_by' => $owner->id]);
    $othersApproved = Question::factory()->approved()->for($chapter)->create();

    $visibleToOwner = Question::visibleTo($owner)->pluck('id');
    $visibleToOther = Question::visibleTo($otherTeacher)->pluck('id');

    expect($visibleToOwner)->toContain($ownPending->id, $othersApproved->id);
    expect($visibleToOther)->not->toContain($ownPending->id);
    expect($visibleToOther)->toContain($othersApproved->id);
});

it('creates a new pending revision instead of mutating an approved question in place', function () {
    $teacher = User::factory()->teacher()->create();
    $chapter = Chapter::factory()->create();

    $original = Question::factory()->approved()->for($chapter)->create(['created_by' => $teacher->id]);

    $revision = $original->createRevisionWith([
        'chapter_id' => $chapter->id,
        'question_type' => $original->question_type,
        'question_text' => '<p>Edited</p>',
        'options' => $original->options,
        'marks' => $original->marks,
        'difficulty' => $original->difficulty,
    ]);

    $original->refresh();

    expect($original->is_latest)->toBeFalse();
    expect($original->status)->toBe(QuestionStatus::Approved);
    expect($revision->parent_id)->toBe($original->id);
    expect($revision->version)->toBe($original->version + 1);
    expect($revision->status)->toBe(QuestionStatus::Pending);
    expect($revision->is_latest)->toBeTrue();
    expect($revision->created_by)->toBe($teacher->id);
    expect(Question::approvedPool()->count())->toBe(0);
});

it('approves a question and logs the action', function () {
    $admin = User::factory()->admin()->create();
    $question = Question::factory()->for(Chapter::factory())->create();

    $question->approve($admin);

    expect($question->status)->toBe(QuestionStatus::Approved);
    expect($question->approved_by)->toBe($admin->id);
    expect($question->approvalLogs()->count())->toBe(1);
});

it('rejects a question with a reason and logs the action', function () {
    $admin = User::factory()->admin()->create();
    $question = Question::factory()->for(Chapter::factory())->create();

    $question->reject($admin, 'Duplicate question.');

    expect($question->status)->toBe(QuestionStatus::Rejected);
    expect($question->rejection_reason)->toBe('Duplicate question.');
    expect($question->approvalLogs()->count())->toBe(1);
});
