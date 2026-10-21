<?php

use App\Enums\Difficulty;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Enums\PerformanceSource;
use App\Enums\PlanConditionType;
use App\Enums\PlanFeature;
use App\Models\AttemptAnswer;
use App\Models\BillingSetting;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PerformanceScope;
use App\Services\StudentPerformance;
use App\Services\SubscriptionLimitService;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    BillingSetting::current()->fill(['performance_min_questions' => 1])->save();

    $this->student = User::factory()->student()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);
    $this->performance = app(StudentPerformance::class);
    $this->own = fn (PerformanceSource $source = PerformanceSource::All): PerformanceScope => PerformanceScope::ownView($this->student, $source);

    $this->mcq = fn (array $attributes = []): Question => Question::factory()->approved()->for($this->chapter)->create([
        'options' => [
            ['option' => 'right', 'image' => null, 'is_correct' => true],
            ['option' => 'wrong', 'image' => null, 'is_correct' => false],
        ],
        'marks' => 1,
        ...$attributes,
    ]);

    /** A self-practice exam by default — its answers are shown at once. */
    $this->examOf = function (array $questions, array $attributes = []): Exam {
        $exam = Exam::factory()->published()->create([
            'created_by' => $this->student->id,
            'exam_type' => ExamType::SelfPractice,
            'generation_mode' => GenerationMode::Auto,
            'class_subject_id' => $this->classSubject->id,
            ...$attributes,
        ]);
        $exam->questions()->attach(collect($questions)->mapWithKeys(fn (Question $question, int $index): array => [
            $question->id => ['order_index' => $index + 1, 'marks_override' => null],
        ])->all());
        $exam->recalculateTotalMarks();

        return $exam->refresh();
    };

    $this->teacherExamOf = fn (array $questions, array $attributes = []): Exam => ($this->examOf)($questions, [
        'created_by' => $this->teacher->id,
        'exam_type' => ExamType::TeacherExam,
        'generation_mode' => null,
        ...$attributes,
    ]);

    /** The student sits the exam a minute after the last one; `$answers` is question id => chosen option. */
    $this->sit = function (Exam $exam, array $answers, ?User $student = null): ExamAttempt {
        $this->travel(1)->minutes();
        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id, 'student_id' => ($student ?? $this->student)->id]);

        foreach ($answers as $questionId => $answer) {
            AttemptAnswer::create(['attempt_id' => $attempt->id, $exam->answerColumn() => $questionId, 'student_answer' => $answer]);
        }

        $attempt->submitAndAutoGrade();

        return $attempt;
    };

    $this->chapterPercent = fn (?PerformanceScope $scope = null): ?int => $this->performance
        ->chapterRates($scope ?? ($this->own)())
        ->firstWhere('chapter.id', $this->chapter->id)['rate']?->percent();
});

it('shows the latest scores as a share of the total marks, oldest first', function () {
    [$a, $b] = [($this->mcq)(), ($this->mcq)()];

    ($this->sit)(($this->examOf)([$a, $b], ['title' => 'First']), [$a->id => 'right']);
    ($this->sit)(($this->examOf)([$a, $b], ['title' => 'Second']), [$a->id => 'right', $b->id => 'right']);

    expect($this->performance->scoreTrend(($this->own)())->map(fn (array $point): array => [$point['title'], $point['percent']])->all())
        ->toBe([['First', 50.0], ['Second', 100.0]]);
});

it('leaves mistakes practice off the scores but counts its answers question by question', function () {
    $question = ($this->mcq)();

    ($this->sit)(($this->examOf)([$question], ['title' => 'Practice']), [$question->id => 'wrong']);
    ($this->sit)(($this->examOf)([$question], ['title' => 'Mistakes', 'generation_mode' => GenerationMode::Mistakes]), [$question->id => 'right']);

    expect($this->performance->scoreTrend(($this->own)())->pluck('title')->all())->toBe(['Practice'])
        ->and(($this->chapterPercent)())->toBe(100);
});

it('counts only the latest answer to each question, across its versions', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'wrong']);

    $newVersion = ($this->mcq)(['parent_id' => $question->id, 'version' => 2]);
    ($this->sit)(($this->examOf)([$newVersion]), [$newVersion->id => 'right']);

    $rate = $this->performance->chapterRates(($this->own)())->first()['rate'];

    expect($rate->questions)->toBe(1)
        ->and($rate->percent())->toBe(100);
});

it('counts a blank as not right, and says how many were left blank', function () {
    [$right, $wrong, $blank] = [($this->mcq)(), ($this->mcq)(), ($this->mcq)()];

    ($this->sit)(($this->examOf)([$right, $wrong, $blank]), [$right->id => 'right', $wrong->id => 'wrong']);

    $rate = $this->performance->chapterRates(($this->own)())->first()['rate'];

    expect([$rate->questions, $rate->correct, $rate->wrong(), $rate->blank, $rate->percent()])->toBe([3, 1, 1, 1, 33]);
});

it('holds back a teacher exam\'s answers until they are out, but shows its score at once', function () {
    $question = ($this->mcq)();
    $exam = ($this->teacherExamOf)([$question], ['title' => 'Class test']);

    ($this->sit)($exam, [$question->id => 'wrong']);

    expect($this->performance->scoreTrend(($this->own)())->pluck('title')->all())->toBe(['Class test'])
        ->and($this->performance->chapterRates(($this->own)()))->toBeEmpty()
        ->and($this->performance->subjectRates(($this->own)()))->toBeEmpty();

    $exam->releaseAnswers();

    expect(($this->chapterPercent)())->toBe(0);
});

it('counts a past paper in its subject and the scores, never in a chapter or difficulty', function () {
    $paper = BoardQuestionPaper::factory()->approved()->withMcqs(2)->create(['class_subject_id' => $this->classSubject->id]);
    $exam = Exam::factory()->published()->create([
        'created_by' => $this->student->id,
        'exam_type' => ExamType::SelfPractice,
        'generation_mode' => GenerationMode::PastPaper,
        'board_question_paper_id' => $paper->id,
        'class_subject_id' => $this->classSubject->id,
    ]);
    $exam->recalculateTotalMarks();
    [$first, $second] = $paper->mcqQuestions->all();

    ($this->sit)($exam->refresh(), [$first->id => $first->correctAnswerValue(), $second->id => 'not it']);

    $subject = $this->performance->subjectRates(($this->own)())->first();

    expect($subject['class_subject']->id)->toBe($this->classSubject->id)
        ->and($subject['rate']->percent())->toBe(50)
        ->and($this->performance->scoreTrend(($this->own)())->pluck('percent')->all())->toBe([50.0])
        ->and($this->performance->chapterRates(($this->own)()))->toBeEmpty()
        ->and(collect($this->performance->difficultyRates(($this->own)()))->sum(fn ($rate) => $rate->questions))->toBe(0);
});

it('narrows everything to one kind of exam when asked', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question], ['title' => 'Practice']), [$question->id => 'right']);
    ($this->sit)(($this->teacherExamOf)([$question], ['title' => 'Class test', 'answers_released_at' => now()]), [$question->id => 'wrong']);

    expect($this->performance->scoreTrend(($this->own)(PerformanceSource::SelfPractice))->pluck('title')->all())->toBe(['Practice'])
        ->and($this->performance->scoreTrend(($this->own)(PerformanceSource::TeacherExam))->pluck('title')->all())->toBe(['Class test'])
        ->and(($this->chapterPercent)(($this->own)(PerformanceSource::SelfPractice)))->toBe(100)
        ->and(($this->chapterPercent)())->toBe(0);
});

it('shows a teacher only their own exams, answers released or not', function () {
    $question = ($this->mcq)();
    $otherTeacher = User::factory()->teacher()->create();

    ($this->sit)(($this->examOf)([$question], ['title' => 'Practice']), [$question->id => 'right']);
    ($this->sit)(($this->teacherExamOf)([$question], ['title' => 'Mine']), [$question->id => 'wrong']);
    ($this->sit)(($this->teacherExamOf)([$question], ['title' => 'Theirs', 'created_by' => $otherTeacher->id]), [$question->id => 'right']);

    $scope = PerformanceScope::teacherView($this->student, $this->teacher);

    expect($this->performance->scoreTrend($scope)->pluck('title')->all())->toBe(['Mine'])
        ->and(($this->chapterPercent)($scope))->toBe(0);
});

it('gives no percentage until enough questions are behind it', function () {
    BillingSetting::current()->fill(['performance_min_questions' => 3])->save();
    [$a, $b] = [($this->mcq)(), ($this->mcq)()];

    ($this->sit)(($this->examOf)([$a, $b]), [$a->id => 'right', $b->id => 'right']);

    expect(($this->chapterPercent)())->toBeNull();
});

it('splits the rates by difficulty', function () {
    $easy = ($this->mcq)(['difficulty' => Difficulty::Easy]);
    $hard = ($this->mcq)(['difficulty' => Difficulty::Hard]);

    ($this->sit)(($this->examOf)([$easy, $hard]), [$easy->id => 'right', $hard->id => 'wrong']);

    $rates = $this->performance->difficultyRates(($this->own)());

    expect(array_keys($rates))->toBe(['easy', 'medium', 'hard'])
        ->and($rates['easy']->percent())->toBe(100)
        ->and($rates['medium']->questions)->toBe(0)
        ->and($rates['hard']->percent())->toBe(0);
});

it('says how much of a subject\'s approved MCQs the student has had', function () {
    [$a, $b, $c, $d] = [($this->mcq)(), ($this->mcq)(), ($this->mcq)(), ($this->mcq)()];

    ($this->sit)(($this->examOf)([$a]), [$a->id => 'right']);

    expect($this->performance->subjectRates(($this->own)())->first()['coverage'])->toBe(25);
});

it('counts the days in a row with an exam handed in', function () {
    $question = ($this->mcq)();
    $sitOn = function (string $day) use ($question): void {
        $this->travelTo($day);
        ($this->sit)(($this->examOf)([$question]), [$question->id => 'right']);
    };

    $sitOn('2026-10-10 10:00');
    $sitOn('2026-10-12 10:00');
    $sitOn('2026-10-13 10:00');
    $sitOn('2026-10-14 09:00');

    $this->travelTo('2026-10-14 20:00');
    expect($this->performance->streakDays(($this->own)()))->toBe(3);

    // Still alive the next day until it ends without an exam.
    $this->travelTo('2026-10-15 20:00');
    expect($this->performance->streakDays(($this->own)()))->toBe(3);

    $this->travelTo('2026-10-16 20:00');
    expect($this->performance->streakDays(($this->own)()))->toBe(0);
});

it('compares this month\'s average score with last month\'s', function () {
    [$a, $b] = [($this->mcq)(), ($this->mcq)()];

    $this->travelTo('2026-09-20 10:00');
    ($this->sit)(($this->examOf)([$a, $b]), [$a->id => 'right']);

    $this->travelTo('2026-10-05 10:00');
    ($this->sit)(($this->examOf)([$a, $b]), [$a->id => 'right', $b->id => 'right']);
    ($this->sit)(($this->examOf)([$a, $b], ['generation_mode' => GenerationMode::Mistakes]), []);

    expect($this->performance->summary(($this->own)()))->toMatchArray([
        'exams_this_month' => 2,
        'average_this_month' => 100.0,
        'average_last_month' => 50.0,
    ]);
});

it('keeps each student\'s figures their own', function () {
    $question = ($this->mcq)();
    ($this->sit)(($this->examOf)([$question]), [$question->id => 'right'], User::factory()->student()->create());

    expect($this->performance->scoreTrend(($this->own)()))->toBeEmpty()
        ->and($this->performance->chapterRates(($this->own)()))->toBeEmpty();
});

describe('who sees the analytics', function () {
    beforeEach(function () {
        $this->limits = app(SubscriptionLimitService::class);
        $this->subscribe = fn (SubscriptionPlan $plan): Subscription => Subscription::factory()->create(['user_id' => $this->student->id, 'plan_id' => $plan->id]);
    });

    it('is nobody without a plan', function () {
        expect($this->limits->allowsAnalytics($this->student))->toBeFalse();
    });

    it('is never a free plan, whatever it allows', function () {
        SubscriptionPlan::factory()->free()->forStudents()->create(['conditions' => []]);

        expect($this->limits->allowsAnalytics($this->student))->toBeFalse();
    });

    it('is a paid plan that includes them', function () {
        ($this->subscribe)(SubscriptionPlan::factory()->pro()->forStudents()->withConditions([
            SubscriptionPlanFactory::condition(PlanConditionType::Features, ['features' => [PlanFeature::Analytics->value]]),
        ])->create());

        expect($this->limits->allowsAnalytics($this->student))->toBeTrue();
    });

    it('is not a paid plan whose features leave them out', function () {
        ($this->subscribe)(SubscriptionPlan::factory()->pro()->forStudents()->withConditions([
            SubscriptionPlanFactory::condition(PlanConditionType::Features, ['features' => [PlanFeature::Print->value]]),
        ])->create());

        expect($this->limits->allowsAnalytics($this->student))->toBeFalse();
    });
});
