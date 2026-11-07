<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Enums\MistakeSource;
use App\Filament\Student\Pages\PractiseMistakes;
use App\Filament\Student\Pages\TakeExamPage;
use App\Filament\Support\QuestionBlocks;
use App\Models\AttemptAnswer;
use App\Models\BoardMcqQuestion;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\Subject;
use App\Models\User;
use App\Services\MistakeFilter;
use App\Services\PastPaperExamService;
use App\Services\PerformanceScope;
use App\Services\SelfPracticeExamService;
use App\Services\StudentMistakeTracker;
use App\Services\StudentPerformance;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

/*
 * The mistakes list takes every exam a student sits, past papers too
 * (CLAUDE.md rule 5.4): their wrong answers are listed and practised beside
 * the question bank's.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->student = User::factory()->student()->create();
    $this->subject = Subject::factory()->create(['name' => 'Physics', 'short_name' => null]);
    $this->classSubject = ClassSubject::factory()->create(['subject_id' => $this->subject->id]);
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);
    $this->student->update(['academic_class_id' => $this->classSubject->academic_class_id]);
    subscribeToEverything($this->student);
    $this->tracker = app(StudentMistakeTracker::class);

    $this->admissionPaper = BoardQuestionPaper::factory()->admission()->approved()->withMcqs(3)->create();
    $this->subjectPaper = BoardQuestionPaper::factory()->approved()->withMcqs(2)->create(['class_subject_id' => $this->classSubject->id]);

    $this->rightOf = fn (BoardMcqQuestion|Question $question): string => collect($question->options)->firstWhere('is_correct', true)['option'];
    $this->wrongOf = fn (BoardMcqQuestion|Question $question): string => collect($question->options)->firstWhere('is_correct', false)['option'];

    /** The student sits a whole past paper; `$answers` is the paper MCQ's id => pick. */
    $this->sitPaper = function (BoardQuestionPaper $paper, array $answers, ?Exam $exam = null): ExamAttempt {
        $attempt = $exam
            ? ExamAttempt::startFor($exam, $this->student)
            : app(PastPaperExamService::class)->startPractice($this->student, $paper->refresh());
        $attempt->recordAnswers(collect($answers)->mapWithKeys(fn (string $pick, int $id): array => ['p'.$id => $pick])->all());
        $attempt->submitAndAutoGrade();

        return $attempt->refresh();
    };

    $this->paperMistakes = fn (): array => DB::table('student_question_mistakes')
        ->where('student_id', $this->student->id)
        ->whereNotNull('board_mcq_question_id')
        ->orderBy('board_mcq_question_id')
        ->pluck('board_mcq_question_id')
        ->all();

    $this->bankMistake = function (): Question {
        $question = Question::factory()->approved()->for($this->chapter)->create([
            'options' => [
                ['option' => 'right', 'image' => null, 'is_correct' => true],
                ['option' => 'wrong', 'image' => null, 'is_correct' => false],
            ],
        ]);
        $exam = Exam::factory()->published()->create(['created_by' => $this->student->id, 'exam_type' => ExamType::SelfPractice]);
        $exam->questions()->attach($question->id, ['order_index' => 1, 'marks_override' => null]);
        $attempt = ExamAttempt::startFor($exam, $this->student);
        $attempt->recordAnswers([(string) $question->id => 'wrong']);
        $attempt->submitAndAutoGrade();

        return $question;
    };
});

it('puts the questions got wrong or left blank on a past paper on the list, with what was picked', function () {
    [$right, $wrong, $blank] = $this->admissionPaper->mcqQuestions;

    $attempt = ($this->sitPaper)($this->admissionPaper, [$right->id => ($this->rightOf)($right), $wrong->id => ($this->wrongOf)($wrong)]);

    expect(($this->paperMistakes)())->toBe([$wrong->id, $blank->id]);

    $row = DB::table('student_question_mistakes')->where('board_mcq_question_id', $wrong->id)->first();
    expect($row->last_answer)->toBe(($this->wrongOf)($wrong))
        ->and($row->last_wrong_exam_id)->toBe($attempt->exam_id)
        ->and($row->wrong_count)->toBe(1)
        ->and(DB::table('student_question_mistakes')->where('board_mcq_question_id', $blank->id)->value('last_answer'))->toBeNull();
});

it('counts every time it is got wrong, and takes it off after the run of correct answers', function () {
    $question = $this->admissionPaper->mcqQuestions->first();
    $answer = fn (string $pick) => ($this->sitPaper)($this->admissionPaper, [$question->id => $pick]);

    $answer(($this->wrongOf)($question));
    $answer(($this->wrongOf)($question));
    expect(DB::table('student_question_mistakes')->where('board_mcq_question_id', $question->id)->value('wrong_count'))->toBe(2);

    $answer(($this->rightOf)($question));
    $answer(($this->rightOf)($question));
    expect(($this->paperMistakes)())->not->toContain($question->id);
});

it('never lists a past paper question that has no correct answer', function () {
    $noAnswer = $this->admissionPaper->mcqQuestions->first();
    $noAnswer->update(['options' => collect($noAnswer->options)->map(fn (array $option): array => [...$option, 'is_correct' => false])->all()]);

    ($this->sitPaper)($this->admissionPaper, []);

    expect(($this->paperMistakes)())->not->toContain($noAnswer->id)
        ->and(($this->paperMistakes)())->toHaveCount(2);
});

it('keeps a teacher\'s past paper exam\'s mistakes locked until its answers are released', function () {
    $teacher = User::factory()->teacher()->create();
    subscribeToEverything($teacher);
    $exam = app(PastPaperExamService::class)->createTeacherExam($teacher, $this->admissionPaper->refresh(), ExamDeliveryMode::Online, 30);
    $exam->publish();

    ($this->sitPaper)($this->admissionPaper, [], $exam->refresh());

    expect(($this->paperMistakes)())->toHaveCount(3)
        ->and($this->tracker->practisablePaperQuestions($this->student)->count())->toBe(0);

    $exam->forceFill(['answers_released_at' => now()])->save();

    expect($this->tracker->practisablePaperQuestions($this->student)->count())->toBe(3);
});

it('lists a subject paper\'s mistakes under its subject and the others under the past papers outside them', function () {
    ($this->bankMistake)();
    ($this->sitPaper)($this->subjectPaper, []);
    ($this->sitPaper)($this->admissionPaper, []);

    expect($this->tracker->countsBySection($this->student)->all())->toEqual([
        $this->classSubject->id => 3,
        '' => 3,
    ]);
});

it('practises the question bank\'s and a past paper\'s mistakes in one exam, marked like any other', function () {
    $bankQuestion = ($this->bankMistake)();
    ($this->sitPaper)($this->subjectPaper, []);
    [$first, $second] = $this->subjectPaper->mcqQuestions;

    $attempt = app(SelfPracticeExamService::class)->generateFromMistakes($this->student, $this->classSubject, 10);
    $exam = $attempt->exam;

    expect($exam->generation_mode)->toBe(GenerationMode::Mistakes)
        ->and($exam->questions->pluck('id')->all())->toBe([$bankQuestion->id])
        ->and($exam->boardMcqQuestions->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and((float) $exam->total_marks)->toBe((float) ($bankQuestion->marks + $first->marks + $second->marks));

    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(TakeExamPage::class, ['attempt' => $attempt])
        ->assertSee($first->question_text, false)
        ->assertSee($bankQuestion->question_text, false)
        ->set('answers', [(string) $bankQuestion->id => 'right', 'p'.$first->id => ($this->rightOf)($first), 'p'.$second->id => ($this->wrongOf)($second)])
        ->call('submit')
        ->assertRedirect();

    $attempt->refresh();
    expect((float) $attempt->total_score)->toBe((float) ($bankQuestion->marks + $first->marks))
        ->and($attempt->answers()->whereNotNull('board_mcq_question_id')->count())->toBe(2)
        // One correct answer into each run; the wrong one starts again.
        ->and(DB::table('student_question_mistakes')->where('board_mcq_question_id', $first->id)->value('correct_streak'))->toBe(1)
        ->and(DB::table('student_question_mistakes')->where('question_id', $bankQuestion->id)->value('correct_streak'))->toBe(1)
        ->and(DB::table('student_question_mistakes')->where('board_mcq_question_id', $second->id)->value('wrong_count'))->toBe(2);
});

it('practises the past papers outside the class\'s subjects from the page, as an exam of no subject', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));
    ($this->sitPaper)($this->admissionPaper, []);

    livewire(PractiseMistakes::class)
        ->assertSee('Other past papers (3)')
        ->assertFormSet(['class_subject_id' => PractiseMistakes::PAST_PAPERS])
        ->fillForm(['class_subject_id' => PractiseMistakes::PAST_PAPERS, 'question_count' => 2])
        ->call('start')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $exam = Exam::where('generation_mode', GenerationMode::Mistakes)->sole();
    expect($exam->class_subject_id)->toBeNull()
        ->and($exam->boardMcqQuestions)->toHaveCount(2)
        ->and($exam->questions)->toHaveCount(0);
});

it('narrows a subject\'s past paper mistakes to one year, or takes every year', function () {
    $older = BoardQuestionPaper::factory()->approved()->withMcqs(1)->create(['class_subject_id' => $this->classSubject->id, 'year' => $this->subjectPaper->year - 1]);
    $bankQuestion = ($this->bankMistake)();
    ($this->sitPaper)($this->subjectPaper, []);
    ($this->sitPaper)($older, []);

    $drawn = fn (MistakeFilter $filter): array => $this->tracker
        ->drawForPractice($this->student, $this->classSubject, 10, filter: $filter)
        ->map(fn ($question): string => $question::class.':'.$question->id)->sort()->values()->all();
    $paperIds = fn (BoardQuestionPaper ...$papers): array => collect($papers)
        ->flatMap(fn (BoardQuestionPaper $paper) => $paper->mcqQuestions)
        ->map(fn (BoardMcqQuestion $question): string => BoardMcqQuestion::class.':'.$question->id)->sort()->values()->all();

    expect($drawn(new MistakeFilter(MistakeSource::PastPapers)))->toBe($paperIds($this->subjectPaper, $older))
        ->and($drawn(new MistakeFilter(MistakeSource::PastPapers, year: $older->year)))->toBe($paperIds($older))
        ->and($drawn(new MistakeFilter(MistakeSource::QuestionBank)))->toBe([Question::class.':'.$bankQuestion->id])
        ->and($this->tracker->countsByYear($this->student, $this->classSubject)->all())->toBe([$this->subjectPaper->year => 2, $older->year => 1]);
});

it('filters the past papers outside the class\'s subjects by year on the page', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));
    $older = BoardQuestionPaper::factory()->admission()->approved()->withMcqs(1)->create(['year' => $this->admissionPaper->year - 1]);
    $older->mcqQuestions->first()->update(['question_text' => '<p>From the older paper</p>']);
    ($this->sitPaper)($this->admissionPaper, []);
    ($this->sitPaper)($older, []);

    livewire(PractiseMistakes::class)
        ->assertFormSet(['class_subject_id' => PractiseMistakes::PAST_PAPERS])
        ->assertSee('From the older paper')
        ->fillForm(['year' => $this->admissionPaper->year, 'question_count' => 10])
        ->assertDontSee('From the older paper')
        ->call('start')
        ->assertHasNoFormErrors();

    expect(Exam::where('generation_mode', GenerationMode::Mistakes)->sole()->boardMcqQuestions)->toHaveCount(3);
});

it('lists each mistake with what was picked, the correct answer and the explanation', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));
    $question = $this->admissionPaper->mcqQuestions->first();
    $question->update(['explanation' => '<p>Because of osmosis.</p>']);
    ($this->sitPaper)($this->admissionPaper, [$question->id => ($this->wrongOf)($question)]);

    livewire(PractiseMistakes::class)
        ->assertSee($question->question_text, false)
        ->assertSee('Because of osmosis.')
        ->assertSee('Your answer')
        ->assertSee('Correct answer')
        ->assertSee('You left it unanswered.')
        ->assertSee($this->admissionPaper->label())
        ->assertSee('Wrong 1 times');
});

it('says so instead of an empty subject list when there is nothing to practise', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(PractiseMistakes::class)
        ->assertSee('No mistakes to practise')
        ->assertDontSee('Start practising');
});

it('brings in the past papers sat before, when the list is built again', function () {
    ($this->sitPaper)($this->admissionPaper, []);
    DB::table('student_question_mistakes')->delete();

    $this->tracker->rebuildFromSubmittedAttempts();

    expect(($this->paperMistakes)())->toHaveCount(3);
});

it('keeps a question-bank stimulus and a past paper stimulus of the same id apart', function () {
    $bankStimulus = QuestionStimulus::factory()->create();
    $paperStimulus = BoardMcqStimulus::factory()->create(['id' => $bankStimulus->id]);
    $bank = Question::factory()->approved()->for($this->chapter)->create(['stimulus_id' => $bankStimulus->id, 'stimulus_order' => 1]);
    $paper = $this->admissionPaper->mcqQuestions->first();
    $paper->update(['stimulus_id' => $paperStimulus->id, 'stimulus_order' => 1]);

    expect(QuestionBlocks::units([$bank, $paper]))->toHaveCount(2);
});

it('answers an exam\'s past paper MCQ under its own key', function () {
    expect(AttemptAnswer::keyFor($this->admissionPaper->mcqQuestions->first()))->toStartWith('p');
});

it('counts a past paper MCQ answered in mistake practice as its latest answer in the student\'s performance', function () {
    [$first] = $this->subjectPaper->mcqQuestions;
    ($this->sitPaper)($this->subjectPaper, []);

    $attempt = app(SelfPracticeExamService::class)->generateFromMistakes($this->student, $this->classSubject, 10);
    $attempt->recordAnswers(['p'.$first->id => ($this->rightOf)($first)]);
    $attempt->submitAndAutoGrade();

    $rows = (new ReflectionMethod(StudentPerformance::class, 'latestAnswers'))
        ->invoke(app(StudentPerformance::class), PerformanceScope::ownView($this->student), false)
        ->get();

    // One row per MCQ — the paper's two — the first now right.
    expect($rows)->toHaveCount(2)
        ->and($rows->sum('correct'))->toBe(1);
});
