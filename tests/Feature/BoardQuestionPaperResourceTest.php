<?php

use App\Enums\QuestionStatus;
use App\Filament\Resources\BoardQuestionPapers\Pages\CreateBoardQuestionPaper;
use App\Filament\Resources\BoardQuestionPapers\Pages\ListBoardQuestionPapers;
use App\Models\Board;
use App\Models\BoardQuestionPaper;
use App\Models\ClassSubject;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->classSubject = ClassSubject::factory()->create();
    $this->board = Board::factory()->create();
});

it('creates a board question paper with mcq and cq questions, auto-summing cq marks', function () {
    livewire(CreateBoardQuestionPaper::class)
        ->fillForm([
            'board_id' => $this->board->id,
            'academic_class_id' => $this->classSubject->academic_class_id,
            'class_subject_id' => $this->classSubject->id,
            'year' => 2023,
            'mcq_questions' => [
                [
                    'question_text' => '<p>Q1?</p>',
                    'options' => [
                        ['option' => 'One', 'is_correct' => false],
                        ['option' => 'Two', 'is_correct' => true],
                    ],
                    'marks' => 1,
                ],
            ],
            'cq_questions' => [
                [
                    'question_text' => '<p>Stimulus</p>',
                    'cq_parts' => [
                        'knowledge' => ['text' => '<p>K</p>', 'marks' => 1],
                        'comprehension' => ['text' => '<p>C</p>', 'marks' => 2],
                        'application' => ['text' => '<p>A</p>', 'marks' => 3],
                        'higher_application' => ['text' => '<p>H</p>', 'marks' => 4],
                    ],
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $paper = BoardQuestionPaper::first();

    expect($paper->status)->toBe(QuestionStatus::Pending);
    expect($paper->mcqQuestions)->toHaveCount(1);
    expect(collect($paper->mcqQuestions->first()->options)->firstWhere('is_correct', true)['option'])->toBe('Two');
    expect($paper->cqQuestions)->toHaveCount(1);
    expect((float) $paper->cqQuestions->first()->marks)->toBe(10.0);
});

it('blocks a second paper for the same board/class-subject/year combination', function () {
    BoardQuestionPaper::factory()->create([
        'board_id' => $this->board->id,
        'class_subject_id' => $this->classSubject->id,
        'year' => 2023,
    ]);

    livewire(CreateBoardQuestionPaper::class)
        ->fillForm([
            'board_id' => $this->board->id,
            'academic_class_id' => $this->classSubject->academic_class_id,
            'class_subject_id' => $this->classSubject->id,
            'year' => 2023,
            'mcq_questions' => [],
            'cq_questions' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['year']);

    expect(BoardQuestionPaper::count())->toBe(1);
});

it('approves and rejects a whole paper from the table', function () {
    $paper = BoardQuestionPaper::factory()->create([
        'board_id' => $this->board->id,
        'class_subject_id' => $this->classSubject->id,
    ]);

    livewire(ListBoardQuestionPapers::class)
        ->callTableAction('approve', $paper)
        ->assertHasNoTableActionErrors();

    expect($paper->refresh()->status)->toBe(QuestionStatus::Approved);
});
