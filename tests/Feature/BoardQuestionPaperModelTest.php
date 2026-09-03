<?php

use App\Enums\CqPartType;
use App\Enums\QuestionStatus;
use App\Models\Board;
use App\Models\BoardCqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\ClassSubject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('defaults a new board question paper to pending regardless of who creates it', function () {
    $paper = BoardQuestionPaper::factory()->create(['created_by' => $this->admin->id]);

    expect($paper->status)->toBe(QuestionStatus::Pending);
});

it('approves and rejects a whole paper at once', function () {
    $paper = BoardQuestionPaper::factory()->create();

    $paper->approve($this->admin);
    expect($paper->status)->toBe(QuestionStatus::Approved);

    $paper->reject($this->admin, 'Missing answer key');
    expect($paper->status)->toBe(QuestionStatus::Rejected);
    expect($paper->rejection_reason)->toBe('Missing answer key');
});

it('rejects duplicate board+class_subject+year combinations at the database level', function () {
    $board = Board::factory()->create();
    $classSubject = ClassSubject::factory()->create();

    BoardQuestionPaper::factory()->create([
        'board_id' => $board->id,
        'class_subject_id' => $classSubject->id,
        'year' => 2023,
    ]);

    expect(fn () => BoardQuestionPaper::factory()->create([
        'board_id' => $board->id,
        'class_subject_id' => $classSubject->id,
        'year' => 2023,
    ]))->toThrow(QueryException::class);
});

it('sums board cq question parts marks into the parent, same as the regular question bank', function () {
    $cqQuestion = BoardCqQuestion::factory()->create();

    foreach (CqPartType::ordered() as $type) {
        $cqQuestion->parts()->create([
            'part_type' => $type,
            'part_order' => $type->order(),
            'part_text' => '<p>text</p>',
            'marks' => 2,
        ]);
    }

    expect((float) $cqQuestion->refresh()->marks)->toBe(8.0);
});
