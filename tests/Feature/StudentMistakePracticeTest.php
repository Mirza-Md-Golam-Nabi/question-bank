<?php

use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Filament\Pages\BillingSettings;
use App\Filament\Student\Pages\PractiseMistakes;
use App\Filament\Student\Widgets\StudentWeakChaptersWidget;
use App\Models\AttemptAnswer;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use App\Services\StudentMistakeTracker;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->student = User::factory()->student()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->subject = Subject::factory()->create(['name' => 'Physics', 'short_name' => null]);
    $this->classSubject = ClassSubject::factory()->create(['subject_id' => $this->subject->id]);
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'name' => 'Motion']);
    $this->student->update(['academic_class_id' => $this->classSubject->academic_class_id]);
    $this->subscription = subscribeToEverything($this->student);
    $this->tracker = app(StudentMistakeTracker::class);

    $this->mcq = fn (array $attributes = []): Question => Question::factory()->approved()->for($this->chapter)->create([
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
        ...$attributes,
    ]);

    /** A self-practice exam by default — its answers are shown at once. */
    $this->examOf = function (array $questions, array $attributes = []): Exam {
        $exam = Exam::factory()->published()->create([
            'created_by' => $this->student->id,
            'exam_type' => ExamType::SelfPractice,
            ...$attributes,
        ]);
        $exam->questions()->attach(collect($questions)->mapWithKeys(fn (Question $question, int $index): array => [
            $question->id => ['order_index' => $index + 1, 'marks_override' => null],
        ])->all());

        return $exam;
    };

    $this->teacherExamOf = fn (array $questions, array $attributes = []): Exam => ($this->examOf)($questions, [
        'created_by' => $this->teacher->id,
        'exam_type' => ExamType::TeacherExam,
        ...$attributes,
    ]);

    /** The student sits the exam; `$answers` is question id => chosen option. */
    $this->sit = function (Exam $exam, array $answers, ?User $student = null): ExamAttempt {
        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id, 'student_id' => ($student ?? $this->student)->id]);

        foreach ($answers as $questionId => $answer) {
            AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $questionId, 'student_answer' => $answer]);
        }

        $attempt->submitAndAutoGrade();

        return $attempt;
    };

    $this->mistakeIds = fn (?User $student = null): array => DB::table('student_question_mistakes')
        ->where('student_id', ($student ?? $this->student)->id)
        ->orderBy('question_id')
        ->pluck('question_id')
        ->all();

    $this->practisableIds = fn (): array => $this->tracker->practisableQuestions($this->student)->orderBy('questions.id')->pluck('questions.id')->all();
});

it('puts the questions a student got wrong or left blank on their mistakes list', function () {
    [$right, $wrong, $blank] = [($this->mcq)(), ($this->mcq)(), ($this->mcq)()];

    ($this->sit)(($this->examOf)([$right, $wrong, $blank]), [$right->id => 'right', $wrong->id => 'wrong']);

    expect(($this->mistakeIds)())->toBe([$wrong->id, $blank->id]);
});

it('keeps each student\'s list their own', function () {
    $question = ($this->mcq)();
    $other = User::factory()->student()->create();

    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);

    expect(($this->mistakeIds)($other))->toBe([]);
});

it('takes a question off the list after two correct answers in a row, by default', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);

    ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);
    expect(($this->mistakeIds)())->toBe([$question->id]);

    ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);
    expect(($this->mistakeIds)())->toBe([]);
});

it('starts the run again when the question is got wrong in between', function () {
    $question = ($this->mcq)();
    $answer = fn (string $option) => ($this->sit)(($this->examOf)([$question]), [$question->id => $option]);

    $answer('wrong');
    $answer('right');
    $answer('wrong');
    $answer('right');

    expect(($this->mistakeIds)())->toBe([$question->id]);

    $answer('right');

    expect(($this->mistakeIds)())->toBe([]);
});

it('clears a mistake after as many correct answers in a row as the admin says', function () {
    BillingSetting::current()->fill(['mistake_clear_streak' => 3])->save();
    $question = ($this->mcq)();
    $answer = fn (string $option) => ($this->sit)(($this->examOf)([$question]), [$question->id => $option]);

    $answer('wrong');
    $answer('right');
    $answer('right');
    expect(($this->mistakeIds)())->toBe([$question->id]);

    $answer('right');
    expect(($this->mistakeIds)())->toBe([]);
});

it('does not put a correctly answered question on the list', function () {
    $question = ($this->mcq)();

    ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);

    expect(($this->mistakeIds)())->toBe([]);
});

it('keeps no list for a guest', function () {
    $question = ($this->mcq)();
    $attempt = ExamAttempt::factory()->guest()->create(['exam_id' => ($this->teacherExamOf)([$question])->id]);

    $attempt->submitAndAutoGrade();

    expect(DB::table('student_question_mistakes')->count())->toBe(0);
});

it('never records the same attempt twice', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);
    $attempt = ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);

    // A second submit of the same paper must not count as a second correct answer.
    $attempt->submitAndAutoGrade();

    expect(($this->mistakeIds)())->toBe([$question->id]);
});

it('keeps a mistake made on a teacher\'s exam out of practice until that exam\'s answers are released', function () {
    $question = ($this->mcq)();
    $exam = ($this->teacherExamOf)([$question]);

    ($this->sit)($exam, []);

    // On the list, but nothing that would show the answer yet.
    expect(($this->mistakeIds)())->toBe([$question->id])
        ->and(($this->practisableIds)())->toBe([]);
    expect(fn () => app(SelfPracticeExamService::class)->generateFromMistakes($this->student, $this->classSubject, 10))
        ->toThrow(ValidationException::class);

    $exam->forceFill(['answers_released_at' => now()])->save();

    expect(($this->practisableIds)())->toBe([$question->id]);
});

it('unlocks such a mistake by itself at the time the teacher scheduled for the answers', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->teacherExamOf)([$question], ['answers_release_at' => now()->addHour()]), []);

    expect(($this->practisableIds)())->toBe([]);

    $this->travel(2)->hours();

    expect(($this->practisableIds)())->toBe([$question->id]);
});

it('keeps a mistake locked when it is got wrong again on an exam that shows its answers', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->teacherExamOf)([$question]), []);
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);

    expect(($this->practisableIds)())->toBe([]);
});

it('practises a corrected question in its current wording, and counts it as the same mistake', function () {
    $original = ($this->mcq)();
    ($this->sit)(($this->examOf)([$original]), [$original->id => 'wrong']);

    $revision = $original->createRevisionWith([
        'chapter_id' => $original->chapter_id,
        'question_type' => $original->question_type,
        'question_text' => '<p>Corrected</p>',
        'options' => $original->options,
        'marks' => 1,
    ]);

    // The new version is pending: nothing to practise until it is approved.
    expect(($this->practisableIds)())->toBe([]);

    $revision->approve(User::factory()->admin()->create());

    expect(($this->practisableIds)())->toBe([$revision->id]);

    ($this->sit)(($this->examOf)([$revision]), [$revision->id => 'right']);
    ($this->sit)(($this->examOf)([$revision]), [$revision->id => 'right']);

    expect(($this->mistakeIds)())->toBe([]);
});

it('never offers a teacher\'s own pending question for practice', function () {
    $pending = ($this->mcq)(['status' => 'pending', 'created_by' => $this->teacher->id]);
    ($this->sit)(($this->teacherExamOf)([$pending], ['answers_released_at' => now()]), []);

    expect(($this->mistakeIds)())->toBe([$pending->id])
        ->and(($this->practisableIds)())->toBe([]);
});

it('builds a practice exam from the student\'s mistakes in the chosen subject only', function () {
    $mistakes = collect(range(1, 3))->map(fn () => ($this->mcq)());
    $otherSubject = Question::factory()->approved()->for(Chapter::factory())->create();
    $neverWrong = ($this->mcq)();
    ($this->sit)(($this->examOf)([...$mistakes, $otherSubject, $neverWrong]), [$neverWrong->id => 'right']);

    $attempt = app(SelfPracticeExamService::class)->generateFromMistakes($this->student, $this->classSubject, 2);

    expect($attempt->exam->exam_type)->toBe(ExamType::SelfPractice)
        ->and($attempt->exam->generation_mode)->toBe(GenerationMode::Mistakes)
        ->and($attempt->exam->created_by)->toBe($this->student->id)
        ->and($attempt->exam->questions)->toHaveCount(2)
        ->and($mistakes->pluck('id')->all())->toContain(...$attempt->exam->questions->pluck('id')->all());
});

it('shrinks the list as the student gets their mistakes right in mistake practice itself', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);

    $practise = function (string $option) use ($question): void {
        $attempt = app(SelfPracticeExamService::class)->generateFromMistakes($this->student, $this->classSubject, 10);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'student_answer' => $option]);
        $attempt->submitAndAutoGrade();
    };

    $practise('right');
    expect(($this->mistakeIds)())->toBe([$question->id]);

    $practise('right');
    expect(($this->mistakeIds)())->toBe([]);

    // Nothing left to practise.
    expect(fn () => $practise('right'))->toThrow(ValidationException::class);
});

it('starts mistake practice from its page, listing only subjects with mistakes', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));
    Subject::factory()->create(['name' => 'Chemistry']);
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);

    livewire(PractiseMistakes::class)
        ->assertSee('Physics (1)')
        ->assertDontSee('Chemistry')
        ->fillForm(['class_subject_id' => $this->classSubject->id, 'question_count' => 5])
        ->call('start')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(Exam::where('generation_mode', GenerationMode::Mistakes)->count())->toBe(1);
});

it('counts mistake practice towards the same monthly limit as other self-practice', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));
    $this->subscription->delete();
    SubscriptionPlan::factory()->free()->forStudents()->monthlyLimit(1)->create();
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);

    livewire(PractiseMistakes::class)
        ->fillForm(['class_subject_id' => $this->classSubject->id, 'question_count' => 5])
        ->call('start')
        ->assertNotified('Your plan does not allow this');

    expect(Exam::where('generation_mode', GenerationMode::Mistakes)->count())->toBe(0);
});

it('shows the chapters with the most mistakes on the student\'s dashboard', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));
    $otherChapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'name' => 'Light']);
    $motion = [($this->mcq)(), ($this->mcq)()];
    $light = Question::factory()->approved()->for($otherChapter)->create();
    ($this->sit)(($this->examOf)([...$motion, $light]), []);

    livewire(StudentWeakChaptersWidget::class)
        ->assertSeeInOrder(['Motion', '2', 'Light', '1'])
        ->assertSee('Practise my mistakes');
});

it('shows how large a share of a chapter\'s questions sat are still mistakes — 3 of 5 is 60%', function () {
    $this->actingAs($this->student);
    Filament::setCurrentPanel(Filament::getPanel('student'));
    $questions = collect(range(1, 5))->map(fn () => ($this->mcq)());

    // Two right, two wrong, one left blank: three mistakes of five.
    ($this->sit)(($this->examOf)($questions->all()), [
        $questions[0]->id => 'right',
        $questions[1]->id => 'right',
        $questions[2]->id => 'wrong',
        $questions[3]->id => 'wrong',
    ]);

    livewire(StudentWeakChaptersWidget::class)
        ->assertSeeInOrder(['Motion', '3', '60%', '3 of 5 questions']);

    expect(StudentMistakeTracker::mistakePercent(3, 5))->toBe(60)
        ->and(StudentMistakeTracker::mistakePercent(1, 3))->toBe(33)
        ->and(StudentMistakeTracker::mistakePercent(0, 0))->toBe(0);
});

it('hands in a paper in the same number of queries however long the mistakes list gets', function () {
    $queries = [];
    BillingSetting::current();

    foreach ([3, 30] as $questionCount) {
        $questions = collect(range(1, $questionCount))->map(fn () => ($this->mcq)());
        $exam = ($this->teacherExamOf)($questions->all());
        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id, 'student_id' => $this->student->id]);
        // Half right, half wrong.
        $questions->each(fn (Question $question, int $index) => AttemptAnswer::create([
            'attempt_id' => $attempt->id, 'question_id' => $question->id, 'student_answer' => $index % 2 ? 'right' : 'wrong',
        ]));
        $attempt = ExamAttempt::with('exam.questions')->find($attempt->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $attempt->submitAndAutoGrade();
        DB::disableQueryLog();

        $queries[$questionCount] = count(DB::getQueryLog());
    }

    expect($queries[30])->toBe($queries[3]);
});

it('brings in the papers handed in before the list existed, in the order they were sat', function () {
    $question = ($this->mcq)();
    $paper = function (bool $isCorrect) use ($question): void {
        $attempt = ExamAttempt::factory()->submitted()->create([
            'exam_id' => ($this->examOf)([$question])->id,
            'student_id' => $this->student->id,
        ]);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'student_answer' => 'x', 'is_correct' => $isCorrect]);
    };

    $paper(false);
    $paper(true);

    ExamAttempt::recordUnrecordedStatistics();

    // Wrong, then right once: still a mistake, one correct answer into its run.
    expect(($this->mistakeIds)())->toBe([$question->id])
        ->and(DB::table('student_question_mistakes')->value('correct_streak'))->toBe(1);

    // Running it again changes nothing.
    ExamAttempt::recordUnrecordedStatistics();

    expect(DB::table('student_question_mistakes')->value('correct_streak'))->toBe(1);
});

it('lets the admin set how many correct answers in a row clear a mistake', function () {
    $this->actingAs(User::factory()->admin()->create());

    livewire(BillingSettings::class)
        ->assertFormSet(['mistake_clear_streak' => 2])
        ->fillForm(['mistake_clear_streak' => 4])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(BillingSetting::current()->mistake_clear_streak)->toBe(4);
});
