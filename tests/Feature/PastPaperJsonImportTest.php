<?php

use App\Enums\EditorMode;
use App\Enums\PaperSourceType;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Pages\BillingSettings;
use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Support\RelationManagers\PaperCqQuestionsRelationManager;
use App\Filament\Support\RelationManagers\PaperMcqQuestionsRelationManager;
use App\Models\BillingSetting;
use App\Models\BoardMcqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\PastPaperExamService;
use App\Services\PastPaperJsonImporter;
use App\Services\QuestionJsonImporter;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->importer = app(PastPaperJsonImporter::class);
    $this->paper = BoardQuestionPaper::factory()->admission(PaperSourceType::University)->create();

    $this->mcq = fn (array $overrides = []): array => [
        'question' => 'পানির সংকেত কী?',
        'options' => ['H$_2$O', 'CO$_2$', 'NaCl', 'O$_2$'],
        'answer' => 1,
        'explanation' => 'দুটি হাইড্রোজেন ও একটি অক্সিজেন।',
        ...$overrides,
    ];

    $this->cq = fn (array $overrides = []): array => [
        'question' => 'একটি উদ্দীপক।',
        'parts' => [
            ['text' => 'ক?', 'marks' => 1, 'answer' => 'ক-এর উত্তর।'],
            ['text' => 'খ?', 'marks' => 2],
            ['text' => 'গ?', 'marks' => 3],
            ['text' => 'ঘ?', 'marks' => 4],
        ],
        ...$overrides,
    ];

    /** The problems the importer finds, or [] when it accepts the JSON. */
    $this->problems = function (array $questions, QuestionType $type = QuestionType::Mcq): array {
        try {
            $this->importer->parse(json_encode($questions), $this->paper, $type);

            return [];
        } catch (ValidationException $exception) {
            return $exception->errors()['json'];
        }
    };
});

it('adds MCQs with their explanations after the ones already in the paper', function () {
    BoardMcqQuestion::factory()->create(['board_question_paper_id' => $this->paper->id, 'order_index' => 5]);

    $result = $this->importer->import(json_encode([
        ($this->mcq)(),
        ($this->mcq)(['question' => 'দ্বিতীয়?', 'explanation' => null]),
        ($this->mcq)(['question' => 'তৃতীয়?', 'explanation' => '']),
    ]), $this->paper, QuestionType::Mcq);

    $added = $this->paper->mcqQuestions()->where('order_index', '>', 5)->get();

    expect($result)->toBe(['added' => 3, 'skipped' => 0, 'possible_duplicates' => 0])
        ->and($added->pluck('order_index')->all())->toBe([6, 7, 8])
        ->and($added[0]->editor_mode)->toBe(EditorMode::CkEditor)
        ->and($added[0]->explanation)->toContain('অক্সিজেন')
        ->and($added[1]->explanation)->toBeNull()
        ->and($added[2]->explanation)->toBeNull()
        ->and(collect($added[0]->options)->firstWhere('is_correct', true)['option'])->toContain('qb-katex-embed');
});

it('adds CQs with their parts, the answers given, and the marks summed', function () {
    $this->importer->import(json_encode([($this->cq)()]), $this->paper, QuestionType::Cq);

    $cq = $this->paper->cqQuestions()->with('parts')->sole();

    expect((float) $cq->marks)->toBe(10.0)
        ->and($cq->parts->pluck('answer')->filter()->count())->toBe(1)
        ->and($cq->parts->first()->answer)->toContain('ক-এর উত্তর');
});

it('takes a CQ whose stimulus is only its image, the same way as a chapter', function () {
    expect(($this->problems)([($this->cq)(['question' => ''])], QuestionType::Cq))->toHaveCount(1);

    $json = json_encode([
        ($this->cq)(['question' => '', 'image' => 'q6.png']),
        ($this->cq)(['question' => '', 'image' => 'q7.png']),
    ]);
    $result = $this->importer->import($json, $this->paper, QuestionType::Cq, images: imagesNamedIn($json));

    $cqs = $this->paper->cqQuestions()->with('parts')->get();

    expect($result)->toBe(['added' => 2, 'skipped' => 0, 'possible_duplicates' => 0])
        ->and($cqs->pluck('order_index')->all())->toBe([1, 2])
        ->and($cqs[0]->question_text)->toBe('')
        ->and($cqs[0]->question_image)->toStartWith(PastPaperJsonImporter::IMAGE_DIRECTORY.'/')
        ->and($cqs[0]->parts)->toHaveCount(4);
});

it('takes an MCQ that is only its image, but not one with neither text nor image', function () {
    expect(($this->problems)([($this->mcq)(['question' => ''])]))->toHaveCount(1);

    $json = json_encode([($this->mcq)(['question' => '', 'image' => 'q1.png'])]);
    $this->importer->import($json, $this->paper, QuestionType::Mcq, images: imagesNamedIn($json));

    $mcq = $this->paper->mcqQuestions()->sole();

    expect($mcq->question_text)->toBe('')
        ->and($mcq->question_image)->toStartWith(PastPaperJsonImporter::IMAGE_DIRECTORY.'/');

    livewire(PaperMcqQuestionsRelationManager::class, ['ownerRecord' => $this->paper, 'pageClass' => EditBoardQuestionPaper::class])
        ->callAction(TestAction::make('edit')->table($mcq), ['marks' => 2])
        ->assertHasNoFormErrors();

    expect((float) $mcq->refresh()->marks)->toBe(2.0)
        ->and($mcq->question_text)->toBe('');
});

it('lets an image-only CQ of a paper be edited without typing a stimulus', function () {
    $json = json_encode([($this->cq)(['question' => '', 'image' => 'q6.png'])]);
    $this->importer->import($json, $this->paper, QuestionType::Cq, images: imagesNamedIn($json));
    $cq = $this->paper->cqQuestions()->sole();

    livewire(PaperCqQuestionsRelationManager::class, ['ownerRecord' => $this->paper, 'pageClass' => EditBoardQuestionPaper::class])
        ->callAction(TestAction::make('edit')->table($cq), ['cq_parts' => ['knowledge' => ['text' => '<p>নতুন ক?</p>', 'marks' => 1]]])
        ->assertHasNoFormErrors();

    expect($cq->refresh()->question_text)->toBe('')
        ->and($cq->parts()->where('part_order', 1)->value('part_text'))->toContain('নতুন ক?');
});

it('takes only its own kind of question in each list', function () {
    expect(($this->problems)([($this->cq)()], QuestionType::Mcq))->toHaveCount(1)
        ->and(($this->problems)([($this->cq)()], QuestionType::Mcq)[0])->toContain('MCQs only')
        ->and(($this->problems)([[...($this->mcq)(), 'type' => 'mcq']], QuestionType::Cq)[0])->toContain('CQs only')
        // "type" may be left out, or given as the list's own.
        ->and(($this->problems)([[...($this->mcq)(), 'type' => 'mcq']], QuestionType::Mcq))->toBe([]);
});

it('checks an explanation and a CQ answer like any other text', function () {
    expect(($this->problems)([($this->mcq)(['explanation' => '$x^{2$'])]))->not->toBe([])
        ->and(($this->problems)([($this->cq)(['parts' => [
            ['text' => 'ক?', 'marks' => 1, 'answer' => '$x^{2$'],
            ['text' => 'খ?', 'marks' => 2],
            ['text' => 'গ?', 'marks' => 3],
            ['text' => 'ঘ?', 'marks' => 4],
        ]])], QuestionType::Cq))->not->toBe([])
        // An empty one is simply none.
        ->and(($this->problems)([($this->cq)(['parts' => [
            ['text' => 'ক?', 'marks' => 1, 'answer' => ''],
            ['text' => 'খ?', 'marks' => 2, 'answer' => null],
            ['text' => 'গ?', 'marks' => 3],
            ['text' => 'ঘ?', 'marks' => 4],
        ]])], QuestionType::Cq))->toBe([]);
});

it('leaves out a question the paper already has, or that is pasted twice', function () {
    $this->importer->import(json_encode([($this->mcq)()]), $this->paper, QuestionType::Mcq);

    $result = $this->importer->import(json_encode([($this->mcq)(), ($this->mcq)(['question' => 'নতুন?']), ($this->mcq)(['question' => 'নতুন?'])]), $this->paper, QuestionType::Mcq);

    expect($result)->toBe(['added' => 1, 'skipped' => 2, 'possible_duplicates' => 0])
        ->and($this->paper->mcqQuestions()->count())->toBe(2);
});

it('refuses more questions than the admin allows at once — the same setting as the question bank', function () {
    BillingSetting::create(['question_import_max' => 1]);

    expect(($this->problems)([($this->mcq)(), ($this->mcq)(['question' => 'আরেকটা?'])]))->toHaveCount(1);
});

it('offers a guide and a sample for each list that the list itself accepts', function (QuestionType $type, string $mentions, int $questions) {
    $reader = PastPaperJsonImporter::reader($type);

    expect($reader->formatGuide())->toEndWith($reader->sampleJson())
        ->toContain($mentions)
        ->not->toContain('"difficulty"');

    $this->importer->import($reader->sampleJson(), $this->paper, $type, images: imagesNamedIn($reader->sampleJson()));

    expect($type === QuestionType::Mcq ? $this->paper->mcqQuestions()->count() : $this->paper->cqQuestions()->count())->toBe($questions);
})->with([
    // An MCQ, two MCQs sharing a stimulus, and an MCQ with figures.
    'MCQ' => [QuestionType::Mcq, '"stimulus"', 4],
    'CQ' => [QuestionType::Cq, '"answer"', 1],
]);

it('leaves the question bank\'s own format without CQ answers, but with MCQ explanations', function () {
    expect(QuestionJsonImporter::formatGuide())->not->toContain('"answer" on a sub-question')
        ->toContain('"explanation"')
        ->toContain('"type" is "mcq" or "cq"');
});

describe('who may import', function () {
    beforeEach(function () {
        $this->staff = User::factory()->staff()->create();
        $this->ownPaper = BoardQuestionPaper::factory()->admission()->create(['created_by' => $this->staff->id]);
    });

    it('lets staff import into their own paper only while the admin has switched it on', function () {
        expect($this->staff->can('import', $this->ownPaper))->toBeFalse();

        BillingSetting::create(['staff_paper_import_enabled' => true]);

        expect($this->staff->can('import', $this->ownPaper))->toBeTrue()
            ->and($this->staff->can('import', $this->paper))->toBeFalse();

        $this->ownPaper->approve($this->admin);

        expect($this->staff->can('import', $this->ownPaper->refresh()))->toBeFalse();
    });

    it('refuses the import itself, not only the button', function () {
        $this->actingAs($this->staff);

        expect(fn () => $this->importer->import(json_encode([($this->mcq)()]), $this->ownPaper, QuestionType::Mcq))
            ->toThrow(AuthorizationException::class);
    });

    it('sends a staff member\'s rejected paper back for review when they import into it', function () {
        BillingSetting::create(['staff_paper_import_enabled' => true]);
        $this->ownPaper->reject($this->admin, 'Add the rest');
        $this->actingAs($this->staff);

        $this->importer->import(json_encode([($this->mcq)()]), $this->ownPaper->refresh(), QuestionType::Mcq);

        expect($this->ownPaper->refresh()->status)->toBe(QuestionStatus::Pending);
    });

    it('never imports into a paper someone has already sat, an admin included', function () {
        $this->paper->update(['status' => QuestionStatus::Approved]);
        BoardMcqQuestion::factory()->create(['board_question_paper_id' => $this->paper->id]);
        $student = User::factory()->student()->create();
        subscribeToEverything($student);
        app(PastPaperExamService::class)->startPractice($student, $this->paper);

        expect(ExamAttempt::count())->toBe(1)
            ->and($this->admin->can('import', $this->paper))->toBeFalse();
    });

    it('lets the admin switch staff imports on in the settings', function () {
        livewire(BillingSettings::class)
            ->fillForm(['staff_paper_import_enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(BillingSetting::current()->staff_paper_import_enabled)->toBeTrue();
    });
});

describe('the "Add from JSON" button beside the new-question button', function () {
    it('adds MCQs from the MCQ list', function () {
        livewire(PaperMcqQuestionsRelationManager::class, ['ownerRecord' => $this->paper, 'pageClass' => EditBoardQuestionPaper::class])
            ->assertActionVisible(TestAction::make('importPaperQuestionsFromJson')->table())
            ->callAction(TestAction::make('importPaperQuestionsFromJson')->table(), ['json' => json_encode([($this->mcq)()])])
            ->assertHasNoFormErrors()
            ->assertNotified();

        expect($this->paper->mcqQuestions()->count())->toBe(1);
    });

    it('adds CQs from the CQ list and shows every problem instead of adding a wrong kind', function () {
        $list = fn () => livewire(PaperCqQuestionsRelationManager::class, ['ownerRecord' => $this->paper, 'pageClass' => EditBoardQuestionPaper::class]);

        $list()->callAction(TestAction::make('importPaperQuestionsFromJson')->table(), ['json' => json_encode([($this->mcq)()])])
            ->assertHasFormErrors(['json']);

        expect($this->paper->cqQuestions()->count())->toBe(0);

        $list()->callAction(TestAction::make('importPaperQuestionsFromJson')->table(), ['json' => json_encode([($this->cq)()])])
            ->assertHasNoFormErrors();

        expect($this->paper->cqQuestions()->count())->toBe(1);
    });

    it('is hidden from staff until the admin allows it', function () {
        $staff = User::factory()->staff()->create();
        $paper = BoardQuestionPaper::factory()->admission()->create(['created_by' => $staff->id]);
        $this->actingAs($staff);

        livewire(PaperMcqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class])
            ->assertActionHidden(TestAction::make('importPaperQuestionsFromJson')->table());
    });
});
