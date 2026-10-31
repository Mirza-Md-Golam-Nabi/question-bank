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
use App\Models\BoardCqQuestion;
use App\Models\BoardCqQuestionPart;
use App\Models\BoardMcqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\ClassSubject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

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

it('lists only the sources of the chosen kind, and every source until one is chosen', function () {
    $sourceIds = fn (Select $field): array => array_keys($field->getOptions());

    livewire(CreateBoardQuestionPaper::class)
        ->assertFormFieldExists('board_id', fn (Select $field): bool => collect($sourceIds($field))->sort()->values()->all() === [$this->board->id, $this->university->id])
        ->fillForm(['source_type' => PaperSourceType::University->value])
        ->assertFormFieldExists('board_id', fn (Select $field): bool => $sourceIds($field) === [$this->university->id]);
});

it('lists the sources by name, A to Z', function () {
    $job = Board::factory()->ofType(PaperSourceType::Job);
    $zila = $job->create(['name' => 'Zila Exam', 'name_bn' => 'Zila Exam']);
    $bank = $job->create(['name' => 'Bank Exam', 'name_bn' => 'Bank Exam']);

    livewire(CreateBoardQuestionPaper::class)
        ->fillForm(['source_type' => PaperSourceType::Job->value])
        ->assertFormFieldExists('board_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$bank->id, $zila->id]);
});

it('sorts the sources in the database by the name shown in the active language', function () {
    Board::query()->delete();
    $first = Board::factory()->create(['name' => 'Zila', 'name_bn' => 'ক']);
    $second = Board::factory()->create(['name' => 'Medical', 'name_bn' => null]);
    $third = Board::factory()->create(['name' => 'Bank', 'name_bn' => 'খ']);

    app()->setLocale('en');
    expect(Board::orderedByDisplayName()->pluck('id')->all())->toBe([$third->id, $second->id, $first->id]);

    app()->setLocale('bn');
    expect(Board::orderedByDisplayName()->pluck('id')->all())->toBe([$second->id, $first->id, $third->id]);
});

it('shows a paper\'s MCQs and CQs whole as cards, and short in the table view', function () {
    $paper = BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => $this->classSubject->id]);
    BoardMcqQuestion::factory()->create([
        'board_question_paper_id' => $paper->id,
        'options' => [['option' => 'Whole option', 'image' => null, 'is_correct' => true], ['option' => 'Other', 'image' => null, 'is_correct' => false]],
    ]);
    $cq = BoardCqQuestion::factory()->create(['board_question_paper_id' => $paper->id]);
    BoardCqQuestionPart::factory()->create(['board_cq_question_id' => $cq->id, 'part_text' => 'Whole sub-question']);

    $list = fn (string $manager, array $query = []) => Livewire::withQueryParams($query)->test($manager, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class]);

    $list(PaperMcqQuestionsRelationManager::class)->assertSee('Whole option');
    $list(PaperCqQuestionsRelationManager::class)->assertSee('Whole sub-question');

    $list(PaperMcqQuestionsRelationManager::class, ['view' => 'table'])->assertDontSee('Whole option');
    $list(PaperCqQuestionsRelationManager::class, ['view' => 'table'])->assertDontSee('Whole sub-question');
});

it('numbers the cards through the whole list, across its pages', function () {
    $paper = BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => $this->classSubject->id]);
    BoardCqQuestion::factory()->count(6)->sequence(fn ($sequence) => ['order_index' => $sequence->index])->create(['board_question_paper_id' => $paper->id]);

    livewire(PaperCqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class])
        ->set('tableRecordsPerPage', 5)
        ->assertSeeHtmlInOrder(['<span>1.</span>', '<span>2.</span>', '<span>5.</span>'])
        ->call('gotoPage', 2, 'paperCqQuestionsRelationManagerPage')
        ->assertSeeHtml('<span>6.</span>');
});

it('keeps the cards numbered while they are being reordered', function () {
    $paper = BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => $this->classSubject->id]);
    BoardCqQuestion::factory()->count(6)->sequence(fn ($sequence) => ['order_index' => $sequence->index])->create(['board_question_paper_id' => $paper->id]);

    livewire(PaperCqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class])
        ->call('toggleTableReordering')
        ->assertOk()
        ->assertSeeHtmlInOrder(['<span>1.</span>', '<span>6.</span>']);
});

it('opens the edit form from a card\'s button only, not from a click anywhere on it', function () {
    $paper = BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => $this->classSubject->id]);
    BoardCqQuestion::factory()->create(['board_question_paper_id' => $paper->id]);

    $list = fn (array $query = []) => Livewire::withQueryParams($query)->test(PaperCqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class]);

    $list()->assertDontSeeHtml('mountTableAction(&#039;edit&#039;');
    $list(['view' => 'table'])->assertSeeHtml('mountTableAction(&#039;edit&#039;');
});

it('switches the view on the same list of the paper', function () {
    $paper = BoardQuestionPaper::factory()->create(['board_id' => $this->board->id, 'class_subject_id' => $this->classSubject->id]);

    livewire(PaperCqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class])
        ->assertTableActionHasUrl('switchDisplay', EditBoardQuestionPaper::getUrl(['record' => $paper, 'relation' => 1, 'view' => 'table']));
});

it('forgets the chosen source when the kind changes', function () {
    livewire(CreateBoardQuestionPaper::class)
        ->fillForm(['board_id' => $this->board->id])
        ->set('data.source_type', PaperSourceType::University->value)
        ->assertSchemaStateSet(['board_id' => null]);
});

it('saves a paper picked through a kind, and shows that kind when the paper is opened again', function () {
    livewire(CreateBoardQuestionPaper::class)
        ->fillForm([
            'source_type' => PaperSourceType::University->value,
            'board_id' => $this->university->id,
            'year' => 2022,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    livewire(EditBoardQuestionPaper::class, ['record' => BoardQuestionPaper::sole()->id])
        ->assertSchemaStateSet(['source_type' => PaperSourceType::University, 'board_id' => $this->university->id]);
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
