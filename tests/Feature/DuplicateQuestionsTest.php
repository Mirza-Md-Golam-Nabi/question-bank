<?php

use App\Enums\Difficulty;
use App\Enums\DuplicateCheckStatus;
use App\Enums\PaperSourceType;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions as AdminListQuestions;
use App\Filament\Support\DuplicateQuestionCheck;
use App\Filament\Support\QuestionJsonImportAction;
use App\Filament\Support\RelationManagers\PaperMcqQuestionsRelationManager;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\BillingSetting;
use App\Models\BoardMcqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\User;
use App\Services\DuplicateQuestionFinder;
use App\Services\PastPaperJsonImporter;
use App\Services\QuestionDuplicateScreening;
use App\Services\QuestionImportText;
use App\Services\QuestionJsonImporter;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->chapter = Chapter::factory()->create();
    $this->finder = new DuplicateQuestionFinder(85);

    /** An unsaved MCQ, written the way the JSON import writes it. */
    $this->mcq = fn (string $question, array $options, int $answer = 1): Question => (new Question([
        'question_type' => QuestionType::Mcq,
        'question_text' => QuestionImportText::toHtml($question),
        'options' => collect($options)->map(fn (string $option, int $index): array => [
            'option' => QuestionImportText::toHtml($option), 'image' => null, 'is_correct' => $index + 1 === $answer,
        ])->all(),
    ]))->setRelation('cqParts', collect());

    /** One JSON MCQ entry. */
    $this->entry = fn (string $question, array $options, array $overrides = []): array => [
        'question' => $question, 'options' => $options, 'answer' => 1, ...$overrides,
    ];

    /** A question of the chapter, saved as its owner's. */
    $this->stored = fn (string $question, array $options, ?User $owner = null, array $attributes = []): Question => Question::factory()->create([
        'chapter_id' => $this->chapter->id,
        'question_text' => QuestionImportText::toHtml($question),
        'options' => ($this->mcq)($question, $options)->options,
        'created_by' => ($owner ?? $this->teacher)->id,
        ...$attributes,
    ]);

    $this->siOptions = ['মিটার', 'কিলোগ্রাম', 'সেকেন্ড', 'কেলভিন'];
    // The same options, one of them spelt another way.
    $this->nearOptions = ['মিটার', 'কিলোগ্রাম', 'সেকেণ্ড', 'কেলভিন'];
});

describe('telling a duplicate', function () {
    it('finds the same question whatever its spacing, punctuation, digits and order of options', function () {
        $one = ($this->mcq)('একটি দণ্ডের দৈর্ঘ্য  ১০ সেমি হলে, কত মিটার?', ['0.1', '1', '10', '100']);
        $two = ($this->mcq)('একটি দণ্ডের দৈর্ঘ্য 10 সেমি হলে কত মিটার', ['10', '100', '0.1', '1']);

        $match = $this->finder->compare($one, $two);

        expect($match->isExact)->toBeTrue()
            ->and($match->percent)->toBe(100);
    });

    it('calls a question with nearly the same options a possible duplicate, with how alike it is', function () {
        $one = ($this->mcq)('$1$ Pa = কত atm?', ['$76$', '$10^{5}$', '$9.8723 \\times 10^{-3}$', '$9.8723 \\times 10^{-6}$']);
        $two = ($this->mcq)('$1$ Pa = কত atm?', ['$76$', '$10^{5}$', '$9.0723 \\times 10^{-3}$', '$9.8723 \\times 10^{-6}$']);

        $match = $this->finder->compare($one, $two);

        expect($match->isExact)->toBeFalse()
            ->and($match->percent)->toBeGreaterThanOrEqual(85)->toBeLessThan(100);
    });

    it('calls a one-letter difference in an option a possible duplicate, but not another option', function () {
        $question = 'নিচের কোনটি মৌলিক রাশি?';

        expect($this->finder->compare(($this->mcq)($question, $this->siOptions), ($this->mcq)($question, $this->nearOptions))?->isExact)->toBeFalse()
            ->and($this->finder->compare(($this->mcq)($question, $this->siOptions), ($this->mcq)($question, ['মিটার', 'কিলোগ্রাম', 'সেকেন্ড', 'অ্যাম্পিয়ার'])))->toBeNull();
    });

    it('does not call the same words with other options a duplicate', function () {
        $one = ($this->mcq)('নিচের কোনটি মৌলিক রাশি?', ['তড়িৎ তীব্রতা', 'তড়িৎ পরিবাহিতা', 'তড়িৎ আধান', 'তড়িৎ প্রবাহ']);
        $two = ($this->mcq)('নিচের কোনটি মৌলিক রাশি?', ['আয়তন', 'দীপন তীব্রতা', 'তাপ', 'তড়িৎ বিভব']);

        expect($this->finder->compare($one, $two))->toBeNull();
    });

    it('never calls questions duplicates when a negating word, a number or a formula differs', function (string $one, string $two) {
        expect($this->finder->compare(($this->mcq)($one, $this->siOptions), ($this->mcq)($two, $this->siOptions)))->toBeNull();
    })->with([
        'negation' => ['নিচের কোনটি মৌলিক রাশির একক?', 'নিচের কোনটি মৌলিক রাশির একক নয়?'],
        'number' => ['একটি বস্তুর ভর 10 কেজি হলে তার একক কোনটি?', 'একটি বস্তুর ভর 20 কেজি হলে তার একক কোনটি?'],
        'formula' => ['$x^{2}$ রাশিটির মৌলিক একক কোনটি হবে?', '$x^{3}$ রাশিটির মৌলিক একক কোনটি হবে?'],
        'right or wrong' => ['এককের ক্ষেত্রে নিচের কোনটি সঠিক?', 'এককের ক্ষেত্রে নিচের কোনটি ভুল?'],
    ]);

    it('keeps a formula whole, its signs included', function () {
        $one = ($this->mcq)('$1$ মিলি কত?', ['$10^{-3}$', '$10^{3}$']);
        $two = ($this->mcq)('$1$ মিলি কত?', ['$10^{3}$', '$10^{-3}$']);
        $three = ($this->mcq)('$1$ মিলি কত?', ['$10^{3}$', '$10^{6}$']);

        expect($this->finder->compare($one, $two)?->isExact)->toBeTrue()
            ->and($this->finder->compare($one, $three)?->isExact)->not->toBeTrue();
    });

    it('does not compare an MCQ with a CQ', function () {
        $cq = Question::factory()->cq()->make(['question_text' => ($this->mcq)('নিচের কোনটি মৌলিক রাশি?', [])->question_text]);

        expect($this->finder->compare(($this->mcq)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions), $cq->setRelation('cqParts', collect())))->toBeNull();
    });
});

describe('adding from JSON', function () {
    beforeEach(function () {
        $this->importer = app(QuestionJsonImporter::class);
        $this->import = fn (array $entries, array $accepted = []): array => $this->importer->import(json_encode($entries), $this->chapter, null, Difficulty::Easy, $accepted);
    });

    it('adds every question of a teacher\'s or an Admin\'s JSON, the duplicates held for the Admin', function (string $writer) {
        $this->actingAs($this->{$writer});
        ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, User::factory()->teacher()->create(), ['status' => QuestionStatus::Approved]);

        expect(($this->import)([
            ($this->entry)('নিচের কোনটি মৌলিক রাশি', array_reverse($this->siOptions)),
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions),
            ($this->entry)('একেবারে নতুন প্রশ্ন?', ['ক', 'খ']),
        ]))->toBe(['added' => 3, 'skipped' => 0, 'possible_duplicates' => 0]);

        expect(Question::query()->latest('id')->take(3)->pluck('duplicate_status')->map->value->all())
            ->toBe([DuplicateCheckStatus::Clear->value, DuplicateCheckStatus::Suspected->value, DuplicateCheckStatus::Suspected->value]);
    })->with(['teacher', 'admin']);

    it('says in the preview that a look-alike is added and left to the Admin — nothing to tick', function () {
        $this->actingAs($this->teacher);
        ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));
        $json = json_encode([($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions)]);

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->mountAction('importQuestionsFromJson')
            ->fillForm(['json' => $json])
            ->assertMountedActionModalSeeHtml('qb-question-duplicate')
            ->assertMountedActionModalSee(__('It will be added; the Admin decides on it under “Possible duplicates”.'))
            ->assertFormFieldHidden(QuestionJsonImportAction::ACCEPTED_FIELD);
    });
});

/**
 * A School's own questions are never checked in the background nor seen by
 * the Admin (CLAUDE.md rule 12), so its JSON still leaves out the same
 * question and asks about a look-alike.
 */
describe('adding from JSON as a School', function () {
    beforeEach(function () {
        $this->teacher = User::factory()->school()->create();
        $this->actingAs($this->teacher);
        $this->importer = app(QuestionJsonImporter::class);
        $this->import = fn (array $entries, array $accepted = []): array => $this->importer->import(json_encode($entries), $this->chapter, null, Difficulty::Easy, $accepted);
    });

    it('leaves out the same question, adds a possible duplicate only when it is accepted', function () {
        ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
        $entries = [
            ($this->entry)('নিচের কোনটি মৌলিক রাশি', array_reverse($this->siOptions)),
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', ['মিটার', 'কিলোগ্রাম', 'সেকেন্ড', 'কেলভিন ']),
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions),
            ($this->entry)('একেবারে নতুন প্রশ্ন?', ['ক', 'খ']),
        ];

        // The first two are the stored question itself; the third only looks like it.
        expect(($this->import)($entries))->toBe(['added' => 1, 'skipped' => 2, 'possible_duplicates' => 1]);

        expect(($this->import)($entries, accepted: [2]))->toBe(['added' => 1, 'skipped' => 3, 'possible_duplicates' => 0])
            ->and(Question::count())->toBe(3);
    });

    it('holds a question against one earlier in the same JSON', function () {
        $entries = [
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions),
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions),
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions),
        ];

        expect(($this->import)($entries))->toBe(['added' => 1, 'skipped' => 1, 'possible_duplicates' => 1]);
    });

    it('decides for a stimulus group as a whole', function () {
        ($this->stored)('পরম ত্রুটি কত?', ['$1$ mm', '$0.7$ mm', '$0.5$ mm', '$0.01$ mm']);
        $group = ['stimulus' => 'একটি দণ্ডের দৈর্ঘ্য মাপা হলো।', 'questions' => [
            ($this->entry)('পরম ত্রুটি কত?', ['$1$ mm', '$0.7$ mm', '$0.5$ mm', '$0.01$ mm']),
            ($this->entry)('আপেক্ষিক ত্রুটি কত?', ['$1\\%$', '$2\\%$']),
        ]];

        // The stimulus is part of what is compared, so the group's first
        // question is not the one stored without it.
        expect(($this->import)([$group]))->toBe(['added' => 2, 'skipped' => 0, 'possible_duplicates' => 0]);

        expect(($this->import)([$group]))->toBe(['added' => 0, 'skipped' => 2, 'possible_duplicates' => 0]);
    });

    it('only holds a School\'s question against questions that School may see', function () {
        ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, User::factory()->teacher()->create());

        expect(($this->import)([($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions)]))->toBe(['added' => 1, 'skipped' => 0, 'possible_duplicates' => 0]);
    });

    it('shows a possible duplicate beside the question it looks like, to add with "Accept all"', function () {
        ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
        Filament::setCurrentPanel(Filament::getPanel('school'));
        $json = json_encode([($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions)]);

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->mountAction('importQuestionsFromJson')
            ->fillForm(['json' => $json])
            ->assertMountedActionModalSeeHtml('qb-question-duplicate')
            ->assertFormFieldExists(QuestionJsonImportAction::ACCEPTED_FIELD)
            ->assertMountedActionModalSee('Accept all')
            // Beside it in the list, a button to jump to that question in the preview.
            ->assertMountedActionModalSeeHtml(['qb-duplicate-option-jump', 'id="'.QuestionJsonImportAction::previewAnchor(1).'"']);

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->callAction('importQuestionsFromJson', data: ['json' => $json, QuestionJsonImportAction::ACCEPTED_FIELD => ['0']])
            ->assertHasNoFormErrors();

        expect(Question::count())->toBe(2);
    });

    // Past papers are not checked in the background either — the same
    // question coming back in other years is what they are — so a paper's
    // JSON still leaves out the same question and asks about a look-alike.
    it('works the same on a past paper, against that paper\'s list', function () {
        $paper = BoardQuestionPaper::factory()->admission(PaperSourceType::Medical)->create(['created_by' => $this->admin->id]);
        $this->actingAs($this->admin);
        BoardMcqQuestion::factory()->create([
            'board_question_paper_id' => $paper->id,
            'question_text' => QuestionImportText::toHtml('নিচের কোনটি মৌলিক রাশি?'),
            'options' => ($this->mcq)('', $this->siOptions)->options,
        ]);
        $json = json_encode([
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions),
            ($this->entry)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions),
        ]);

        expect(app(PastPaperJsonImporter::class)->import($json, $paper, QuestionType::Mcq))->toBe(['added' => 0, 'skipped' => 1, 'possible_duplicates' => 1]);
    });
});

describe('writing one in a form', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $classSubject = ClassSubject::find($this->chapter->class_subject_id);

        $this->form = fn (string $question, array $options, array $overrides = []): array => [
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'editor_mode' => 'ckeditor',
            'difficulty' => 'easy',
            'question_text' => QuestionImportText::toHtml($question),
            'marks' => 1,
            'options' => collect($options)->map(fn (string $option, int $index): array => ['option' => QuestionImportText::toHtml($option), 'is_correct' => $index === 0])->all(),
            ...$overrides,
        ];

        ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    });

    it('refuses the same question again', function () {
        livewire(CreateQuestion::class)
            ->fillForm(($this->form)('নিচের কোনটি মৌলিক রাশি', array_reverse($this->siOptions), [DuplicateQuestionCheck::FIELD => true]))
            ->call('create')
            ->assertHasFormErrors([DuplicateQuestionCheck::FIELD]);

        expect(Question::count())->toBe(1);
    });

    it('saves a possible duplicate only once the box is ticked', function () {
        $form = ($this->form)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);

        // The question it looks like shows whole under the message — its options and right answer too.
        livewire(CreateQuestion::class)->fillForm($form)->call('create')
            ->assertHasFormErrors([DuplicateQuestionCheck::FIELD])
            ->assertSeeHtml('qb-question-duplicate')
            ->assertSee('কেলভিন');
        expect(Question::count())->toBe(1);

        livewire(CreateQuestion::class)->fillForm([...$form, DuplicateQuestionCheck::FIELD => true])->call('create')->assertHasNoFormErrors();
        expect(Question::count())->toBe(2);
    });

    it('does not hold an edited question against itself or its own versions', function () {
        $question = Question::sole();

        livewire(EditQuestion::class, ['record' => $question->id])
            ->fillForm(['marks' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $revision = Question::where('parent_id', $question->id)->sole();

        livewire(EditQuestion::class, ['record' => $revision->id])
            ->fillForm(['marks' => 3])
            ->call('save')
            ->assertHasNoFormErrors();
    });

    it('says which question of a stimulus group looks like one already there', function () {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $item = fn (string $question, array $options): array => [
            'question_text' => QuestionImportText::toHtml($question),
            'options' => collect($options)->map(fn (string $option, int $index): array => ['option' => QuestionImportText::toHtml($option), 'is_correct' => $index === 0])->all(),
            'marks' => 1,
            'difficulty' => 'easy',
        ];
        $form = [
            'editor_mode' => 'ckeditor',
            'question_text' => '',
            'question_image' => null,
            'topic_id' => null,
            'questions' => [$item('প্রথম প্রশ্ন কোনটি?', ['ক', 'খ']), $item('নিচের কোনটি মৌলিক রাশি?', $this->siOptions)],
        ];

        livewire(AdminListQuestions::class, ['chapter' => $this->chapter->id])
            ->callAction('createStimulusGroup', data: $form)
            ->assertHasFormErrors([DuplicateQuestionCheck::FIELD]);

        expect(Question::count())->toBe(1);
    });

    it('checks a past paper\'s question against that paper\'s list', function () {
        $paper = BoardQuestionPaper::factory()->admission(PaperSourceType::Medical)->create();
        BoardMcqQuestion::factory()->create([
            'board_question_paper_id' => $paper->id,
            'question_text' => QuestionImportText::toHtml('নিচের কোনটি মৌলিক রাশি?'),
            'options' => ($this->mcq)('', $this->siOptions)->options,
        ]);
        $list = fn () => livewire(PaperMcqQuestionsRelationManager::class, ['ownerRecord' => $paper, 'pageClass' => EditBoardQuestionPaper::class]);
        $form = collect(($this->form)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions))
            ->only(['editor_mode', 'question_text', 'marks', 'options'])
            ->all();

        $list()->callAction(TestAction::make('create')->table(), $form)->assertHasFormErrors([DuplicateQuestionCheck::FIELD]);
        $list()->callAction(TestAction::make('create')->table(), [...$form, DuplicateQuestionCheck::FIELD => true])->assertHasNoFormErrors();

        expect($paper->mcqQuestions()->count())->toBe(2);
    });
});

it('shows the Admin, when approving, the question already in the chapter it looks like', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, attributes: ['status' => QuestionStatus::Approved]);
    $pending = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $this->actingAs($this->admin);

    // The background check held it first; the Admin has called it a question of its own.
    expect($pending->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Suspected);
    QuestionDuplicateScreening::keep($pending, $this->admin);

    expect(DuplicateQuestionCheck::forApproval($pending))->toHaveCount(1);

    livewire(AdminListQuestions::class, ['chapter' => $this->chapter->id])
        ->mountAction(TestAction::make('approve')->table($pending))
        ->assertMountedActionModalSeeHtml('qb-question-duplicate');
});

it('lets the Admin set how alike a possible duplicate is', function () {
    expect(BillingSetting::current()->duplicate_similarity_percent)->toBe(85);

    BillingSetting::current()->fill(['duplicate_similarity_percent' => 99])->save();

    $one = ($this->mcq)('$1$ Pa = কত atm?', ['$76$', '$10^{5}$', '$9.8723 \\times 10^{-3}$', '$9.8723 \\times 10^{-6}$']);
    $two = ($this->mcq)('$1$ Pa = কত atm?', ['$76$', '$10^{5}$', '$9.0723 \\times 10^{-3}$', '$9.8723 \\times 10^{-6}$']);

    expect(DuplicateQuestionFinder::make()->compare($one, $two))->toBeNull();
});
