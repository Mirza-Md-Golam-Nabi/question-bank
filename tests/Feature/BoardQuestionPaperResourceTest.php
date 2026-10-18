<?php

use App\Enums\EditorMode;
use App\Enums\PaperSourceType;
use App\Enums\QuestionStatus;
use App\Filament\Resources\BoardQuestionPapers\Pages\CreateBoardQuestionPaper;
use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Resources\BoardQuestionPapers\Pages\ListBoardQuestionPapers;
use App\Filament\Staff\Resources\BoardQuestionPapers\BoardQuestionPaperResource as StaffBoardQuestionPaperResource;
use App\Filament\Staff\Resources\BoardQuestionPapers\Pages\ListBoardQuestionPapers as StaffListBoardQuestionPapers;
use App\Filament\Support\RelationManagers\PaperCqQuestionsRelationManager;
use App\Filament\Support\RelationManagers\PaperMcqQuestionsRelationManager;
use App\Models\Board;
use App\Models\BoardMcqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\ClassSubject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->classSubject = ClassSubject::factory()->create();
    $this->board = Board::factory()->create();
    $this->university = Board::factory()->ofType(PaperSourceType::University)->create();
});

it('creates a board paper and opens it, so its questions are added one by one', function () {
    $page = livewire(CreateBoardQuestionPaper::class)
        ->fillForm([
            'board_id' => $this->board->id,
            'academic_class_id' => $this->classSubject->academic_class_id,
            'class_subject_id' => $this->classSubject->id,
            'year' => 2023,
            'duration_minutes' => 25,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $paper = BoardQuestionPaper::sole();

    $page->assertRedirect(EditBoardQuestionPaper::getUrl(['record' => $paper]));

    expect($paper->status)->toBe(QuestionStatus::Pending)
        ->and($paper->class_subject_id)->toBe($this->classSubject->id)
        ->and($paper->duration_minutes)->toBe(25);
});

it('creates an admission paper with a source, year and unit only — no class or subject', function () {
    livewire(CreateBoardQuestionPaper::class)
        ->fillForm([
            'board_id' => $this->university->id,
            'year' => 2022,
            'unit' => 'Ka',
            'negative_marks' => 0.25,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(BoardQuestionPaper::sole())
        ->class_subject_id->toBeNull()
        ->unit->toBe('Ka')
        ->negative_marks->toBe(0.25);
});

it('adds an MCQ with an explanation and a CQ with answers to a paper, the CQ marks summed', function () {
    $paper = BoardQuestionPaper::factory()->admission(PaperSourceType::University)->create();

    livewire(PaperMcqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class])
        ->callAction(TestAction::make('create')->table(), [
            'editor_mode' => EditorMode::CkEditor->value,
            'question_text' => '<p>Q1?</p>',
            'marks' => 1,
            'options' => [
                ['option' => 'One', 'is_correct' => false],
                ['option' => 'Two', 'is_correct' => true],
            ],
            'explanation' => '<p>Because two.</p>',
        ])
        ->assertHasNoFormErrors();

    livewire(PaperCqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class])
        ->callAction(TestAction::make('create')->table(), [
            'editor_mode' => EditorMode::CkEditor->value,
            'question_text' => '<p>Stimulus</p>',
            'cq_parts' => [
                'knowledge' => ['text' => '<p>K</p>', 'marks' => 1, 'answer' => '<p>K answer</p>'],
                'comprehension' => ['text' => '<p>C</p>', 'marks' => 2],
                'application' => ['text' => '<p>A</p>', 'marks' => 3],
                'higher_application' => ['text' => '<p>H</p>', 'marks' => 4],
            ],
        ])
        ->assertHasNoFormErrors();

    $paper->refresh();
    $mcq = $paper->mcqQuestions->sole();
    $cq = $paper->cqQuestions->sole();

    expect(collect($mcq->options)->firstWhere('is_correct', true)['option'])->toBe('Two')
        ->and($mcq->explanation)->toBe('<p>Because two.</p>')
        ->and((float) $cq->marks)->toBe(10.0)
        ->and($cq->parts->firstWhere('part_type.value', 'knowledge')->answer)->toBe('<p>K answer</p>');
});

it('blocks a second paper for the same source, year and unit — admission papers included', function () {
    BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => $this->classSubject->id, 'year' => 2023]);
    BoardQuestionPaper::factory()->create(['board_id' => $this->university->id, 'class_subject_id' => null, 'year' => 2023, 'unit' => 'Ka']);

    livewire(CreateBoardQuestionPaper::class)
        ->fillForm([
            'board_id' => $this->board->id,
            'academic_class_id' => $this->classSubject->academic_class_id,
            'class_subject_id' => $this->classSubject->id,
            'year' => 2023,
        ])
        ->call('create')
        ->assertHasFormErrors(['year']);

    livewire(CreateBoardQuestionPaper::class)
        ->fillForm(['board_id' => $this->university->id, 'year' => 2023, 'unit' => 'Ka'])
        ->call('create')
        ->assertHasFormErrors(['year']);

    expect(BoardQuestionPaper::count())->toBe(2);
});

it('approves a whole paper from the table once it has questions', function () {
    $empty = BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => $this->classSubject->id]);
    $paper = BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => ClassSubject::factory()]);
    BoardMcqQuestion::factory()->create(['board_question_paper_id' => $paper->id]);

    livewire(ListBoardQuestionPapers::class)
        ->assertActionHidden(TestAction::make('approve')->table($empty))
        ->callAction(TestAction::make('approve')->table($paper))
        ->assertHasNoActionErrors();

    expect($paper->refresh()->status)->toBe(QuestionStatus::Approved);
});

it('shows a staff member only their own papers, and lets them change them only until approved', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    $own = BoardQuestionPaper::factory()->create(['created_by' => $staff->id]);
    $someoneElses = BoardQuestionPaper::factory()->create();

    livewire(StaffListBoardQuestionPapers::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$someoneElses])
        ->assertActionDoesNotExist(TestAction::make('approve')->table($own));

    expect($staff->can('update', $own))->toBeTrue();

    $own->approve($this->admin);

    expect($staff->can('update', $own->refresh()))->toBeFalse()
        ->and(StaffBoardQuestionPaperResource::getEloquentQuery()->pluck('id')->all())->toBe([$own->id]);
});
