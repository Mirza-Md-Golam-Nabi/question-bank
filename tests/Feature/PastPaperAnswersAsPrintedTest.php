<?php

use App\Enums\Difficulty;
use App\Enums\ExamDeliveryMode;
use App\Enums\NoAnswerMarking;
use App\Enums\QuestionType;
use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Support\RelationManagers\PaperMcqQuestionsRelationManager;
use App\Filament\Teacher\Pages\PastPapers as TeacherPastPapers;
use App\Models\AttemptAnswer;
use App\Models\BoardMcqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\ExamRepeater;
use App\Services\ExamResultSheet;
use App\Services\PastPaperExamService;
use App\Services\PastPaperJsonImporter;
use App\Services\PerformanceScope;
use App\Services\QuestionJsonImporter;
use App\Services\QuestionJsonReader;
use App\Services\StudentPerformance;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ComponentAttributeBag;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    subscribeToEverything($this->teacher);

    $this->optionsWith = fn (array $correct): array => collect(['A', 'B', 'C', 'D'])
        ->map(fn (string $text, int $index): array => ['option' => $text, 'image' => null, 'is_correct' => in_array($index, $correct, true)])
        ->all();

    /** A paper of a normal MCQ, one with two right options and one with none. */
    $this->paper = BoardQuestionPaper::factory()->admission()->approved()->create(['year' => 2016]);
    $mcq = fn (array $correct, int $order): BoardMcqQuestion => BoardMcqQuestion::factory()->create([
        'board_question_paper_id' => $this->paper->id,
        'options' => ($this->optionsWith)($correct),
        'marks' => 1,
        'order_index' => $order,
    ]);
    $this->normal = $mcq([0], 1);
    $this->twoRight = $mcq([1, 2], 2);
    $this->noneRight = $mcq([], 3);

    $this->setExam = fn (NoAnswerMarking $marking, ?float $negativeMarks = null, ExamDeliveryMode $mode = ExamDeliveryMode::Online): Exam => app(PastPaperExamService::class)
        ->createTeacherExam($this->teacher, $this->paper->refresh(), $mode, 30, $negativeMarks, $marking);

    /** Hands in a paper with these answers (keyed by the paper MCQ's id). */
    $this->sit = function (Exam $exam, array $answers): ExamAttempt {
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => User::factory()->student()->create()->id,
            'is_guest' => false,
            'started_at' => now(),
        ]);
        $attempt->recordAnswers(collect($answers)->mapWithKeys(fn (string $pick, int $id): array => [AttemptAnswer::PAPER_KEY_PREFIX.$id => $pick])->all());
        $attempt->submitAndAutoGrade();

        return $attempt->refresh();
    };
});

describe('reading the JSON', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $this->json = fn (mixed ...$question): string => json_encode([[
            'question' => 'একটি প্রশ্ন?',
            'options' => ['ক', 'খ', 'গ', 'ঘ'],
            ...$question,
        ]]);
    });

    it('takes a past paper answer as one number, several or none', function () {
        $correct = fn (mixed $answer): array => collect(app(PastPaperJsonImporter::class)
            ->parse(($this->json)(answer: $answer), $this->paper, QuestionType::Mcq)->first()->options)
            ->pluck('is_correct')->all();

        expect($correct(2))->toBe([false, true, false, false])
            ->and($correct([2, 3]))->toBe([false, true, true, false])
            ->and($correct(null))->toBe([false, false, false, false]);
    });

    it('never reads a left-out answer as "no correct answer"', function () {
        expect(fn () => app(PastPaperJsonImporter::class)->parse(($this->json)(), $this->paper, QuestionType::Mcq))
            ->toThrow(ValidationException::class);
    });

    it('refuses an answer out of range', function (mixed $answer) {
        expect(fn () => app(PastPaperJsonImporter::class)->parse(($this->json)(answer: $answer), $this->paper, QuestionType::Mcq))
            ->toThrow(ValidationException::class);
    })->with([[[]], [[2, 9]], [0], ['x']]);

    it('keeps a question-bank answer to exactly one number', function (mixed $answer) {
        $chapter = Chapter::factory()->create();

        expect(fn () => app(QuestionJsonImporter::class)->parse(($this->json)(type: 'mcq', answer: $answer), $chapter, null, Difficulty::Easy))
            ->toThrow(ValidationException::class);
    })->with([[null], [[[2, 3]]]]);

    it('tells the AI tool to copy only what the source shows, and only for a past paper', function () {
        $guide = PastPaperJsonImporter::reader(QuestionType::Mcq)->formatGuide();

        expect($guide)
            ->toContain('write only what the book, image or PDF itself shows')
            ->toContain('never write one yourself')
            ->toContain('"answer": null')
            ->and((new QuestionJsonReader)->formatGuide())
            ->not->toContain('write only what the book, image or PDF itself shows');
    });

    it('accepts its own sample', function () {
        $reader = PastPaperJsonImporter::reader(QuestionType::Mcq);

        expect($reader->read(json_encode(array_map(
            fn (array $item): array => array_diff_key($item, ['image' => true]),
            array_slice(json_decode($reader->sampleJson(), true), 1, 2),
        ))))->toHaveCount(2);
    });
});

describe('the past paper form', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $this->unsatPaper = BoardQuestionPaper::factory()->admission()->create(['year' => 2017]);
        $this->mcq = BoardMcqQuestion::factory()->create([
            'board_question_paper_id' => $this->unsatPaper->id,
            'options' => ($this->optionsWith)([0]),
        ]);
        $this->list = fn () => livewire(PaperMcqQuestionsRelationManager::class, ['ownerRecord' => $this->unsatPaper, 'pageClass' => EditBoardQuestionPaper::class]);
    });

    it('saves several correct options', function () {
        ($this->list)()
            ->callAction(TestAction::make('edit')->table($this->mcq), ['options' => ($this->optionsWith)([1, 2])])
            ->assertHasNoFormErrors();

        expect($this->mcq->refresh()->correctAnswerValues())->toBe(['B', 'C'])
            ->and($this->mcq->has_correct_answer)->toBeTrue();
    });

    it('saves a question without a correct answer only when told so', function () {
        ($this->list)()
            ->callAction(TestAction::make('edit')->table($this->mcq), ['options' => ($this->optionsWith)([])])
            ->assertHasFormErrors(['options']);

        expect($this->mcq->refresh()->hasCorrectAnswer())->toBeTrue();

        ($this->list)()
            ->callAction(TestAction::make('edit')->table($this->mcq), ['options' => ($this->optionsWith)([]), 'no_correct_answer' => true])
            ->assertHasNoFormErrors();

        expect($this->mcq->refresh()->hasCorrectAnswer())->toBeFalse()
            ->and($this->mcq->has_correct_answer)->toBeFalse();

        ($this->list)()
            ->mountAction(TestAction::make('edit')->table($this->mcq))
            ->assertSchemaStateSet(['no_correct_answer' => true], 'mountedActionSchema0');
    });

    it('clears every tick when told there is no correct answer', function () {
        $this->mcq->update(['options' => ($this->optionsWith)([1, 2])]);

        $form = ($this->list)()
            ->mountAction(TestAction::make('edit')->table($this->mcq))
            ->set('mountedActions.0.data.no_correct_answer', true);

        expect(collect($form->get('mountedActions.0.data.options'))->pluck('is_correct')->all())
            ->toBe([false, false, false, false]);
    });

    it('leaves one tick in the question bank, wherever the old one was', function () {
        $question = Question::factory()->approved()->for(Chapter::factory())->create([
            'question_type' => QuestionType::Mcq,
            'options' => ($this->optionsWith)([1]),
        ]);

        $form = livewire(EditQuestion::class, ['record' => $question->id]);
        $keys = array_keys($form->get('data.options'));

        $form->set("data.options.{$keys[3]}.is_correct", true);

        expect(collect($form->get('data.options'))->pluck('is_correct')->all())
            ->toBe([false, false, false, true]);
    });
});

describe('marking an exam', function () {
    it('takes either of two correct options as right', function () {
        $exam = ($this->setExam)(NoAnswerMarking::Excluded, 0.25);

        foreach (['B', 'C'] as $pick) {
            $attempt = ($this->sit)($exam, [$this->twoRight->id => $pick]);

            expect((float) $attempt->total_score)->toBe(1.0)
                ->and($attempt->answers()->first()->is_correct)->toBeTrue();
        }
    });

    it('leaves a question without an answer out of the marks, costing nobody anything', function () {
        $exam = ($this->setExam)(NoAnswerMarking::Excluded, 0.25);

        expect((float) $exam->total_marks)->toBe(2.0);

        $attempt = ($this->sit)($exam, [$this->normal->id => 'A', $this->noneRight->id => 'B']);
        $answer = $attempt->answers()->where('board_mcq_question_id', $this->noneRight->id)->first();

        expect((float) $attempt->total_score)->toBe(1.0)
            ->and($answer->is_correct)->toBeNull()
            ->and((float) $answer->obtained_marks)->toBe(0.0)
            ->and($answer->student_answer)->toBe('B');
    });

    it('gives everyone its marks when the teacher chose that, even with it left blank', function () {
        $exam = ($this->setExam)(NoAnswerMarking::FullMarks, 0.25);

        expect((float) $exam->total_marks)->toBe(3.0);

        $picked = ($this->sit)($exam, [$this->noneRight->id => 'B']);
        $blank = ($this->sit)($exam, []);

        expect((float) $picked->total_score)->toBe(1.0)
            ->and($picked->answers()->first()->student_answer)->toBe('B')
            ->and((float) $blank->total_score)->toBe(1.0)
            ->and($blank->answers()->first()->student_answer)->toBeNull()
            ->and($blank->answers()->first()->is_correct)->toBeNull();
    });

    it('prints the same total', function (NoAnswerMarking $marking, string $total) {
        $exam = ($this->setExam)($marking, null, ExamDeliveryMode::Offline);

        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        expect((float) $exam->total_marks)->toBe((float) $total)
            ->and($exam->marksOf($exam->printedItems()))->toBe((float) $total);
    })->with([
        'left out' => [NoAnswerMarking::Excluded, '2'],
        'given to all' => [NoAnswerMarking::FullMarks, '3'],
    ]);

    it('shows the student what happened on the result', function () {
        $left = view('components.exam-result-question', [
            'question' => $this->noneRight,
            'answer' => null,
            'attributes' => new ComponentAttributeBag,
        ])->render();

        expect($left)
            ->toContain('This question has no correct answer.')
            ->toContain('It is not counted in the marks.')
            ->not->toContain('Not answered');

        $exam = ($this->setExam)(NoAnswerMarking::FullMarks);
        $given = view('components.exam-result-question', [
            'question' => $this->noneRight,
            'answer' => ($this->sit)($exam, [$this->noneRight->id => 'B'])->answers()->first(),
            'attributes' => new ComponentAttributeBag,
        ])->render();

        expect($given)
            ->toContain('Everyone gets its 1 marks.')
            ->not->toContain('✗');
    });
});

describe('the numbers around an exam', function () {
    it('never lists anyone as wrong or not answering it', function () {
        $exam = ($this->setExam)(NoAnswerMarking::Excluded);
        ($this->sit)($exam, [$this->normal->id => 'A', $this->noneRight->id => 'B']);
        ($this->sit)($exam, [$this->normal->id => 'A']);
        $sheet = app(ExamResultSheet::class);

        $stat = $sheet->questionStatsFor($exam->refresh())->firstWhere('question.id', $this->noneRight->id);

        expect($stat)
            ->is_judged->toBeFalse()
            ->wrong->toBe(0)
            ->unanswered->toBe(0)
            ->and($sheet->unansweredBy($exam, $this->noneRight->id))->toBeEmpty()
            ->and($sheet->wrongAnswersFor($exam, $this->noneRight->id))->toBeEmpty();
    });

    it('leaves it out of a student\'s performance', function () {
        $exam = ($this->setExam)(NoAnswerMarking::Excluded);
        $attempt = ($this->sit)($exam, [$this->normal->id => 'A', $this->twoRight->id => 'B']);
        $exam->forceFill(['answers_released_at' => now()])->save();

        $rows = (new ReflectionMethod(StudentPerformance::class, 'latestAnswers'))
            ->invoke(app(StudentPerformance::class), PerformanceScope::ownView($attempt->student), false)
            ->get();

        expect($rows)->toHaveCount(2)
            ->and($rows->sum('correct'))->toBe(2);
    });

    it('keeps the teacher\'s choice when the exam is taken again', function () {
        $exam = ($this->setExam)(NoAnswerMarking::FullMarks);

        $repeated = app(ExamRepeater::class)->repeat($exam, $this->teacher, 'Again', 30);

        expect($repeated->no_answer_marking)->toBe(NoAnswerMarking::FullMarks)
            ->and((float) $repeated->total_marks)->toBe(3.0);
    });
});

describe('the teacher setting the exam', function () {
    beforeEach(function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));
    });

    it('asks how to mark such questions, and saves the choice', function () {
        livewire(TeacherPastPapers::class)
            ->mountAction(TestAction::make('setAsExam')->table($this->paper))
            ->setActionData(['no_answer_marking' => NoAnswerMarking::FullMarks->value])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect(Exam::query()->latest('id')->first())
            ->no_answer_marking->toBe(NoAnswerMarking::FullMarks)
            ->and((float) Exam::query()->latest('id')->first()->total_marks)->toBe(3.0);
    });

    it('does not ask when every question has its answer', function () {
        $this->noneRight->update(['options' => ($this->optionsWith)([0])]);

        livewire(TeacherPastPapers::class)
            ->mountAction(TestAction::make('setAsExam')->table($this->paper))
            ->setActionData(['no_answer_marking' => NoAnswerMarking::FullMarks->value])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect(Exam::query()->latest('id')->first()->no_answer_marking)->toBe(NoAnswerMarking::Excluded);
    });
});
