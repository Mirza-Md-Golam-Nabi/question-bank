<?php

use App\Enums\CqPartType;
use App\Enums\ExamDeliveryMode;
use App\Enums\GenerationMode;
use App\Enums\PaperSourceType;
use App\Enums\PlanConditionType;
use App\Enums\PlanFeature;
use App\Enums\QuestionStatus;
use App\Filament\Student\Pages\ExamResultPage;
use App\Filament\Student\Pages\PastPapers as StudentPastPapers;
use App\Filament\Student\Pages\TakeExamPage;
use App\Filament\Teacher\Pages\PastPapers as TeacherPastPapers;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\BillingSetting;
use App\Models\Board;
use App\Models\BoardCqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ExamResultSheet;
use App\Services\PastPaperExamService;
use App\Services\SubscriptionLimitService;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->exams = app(PastPaperExamService::class);
    $this->limits = app(SubscriptionLimitService::class);
    $this->condition = fn (PlanConditionType $type, array $data): array => SubscriptionPlanFactory::condition($type, $data);

    $this->paper = BoardQuestionPaper::factory()->admission()->approved()->withMcqs(3)->create([
        'year' => 2018,
        'negative_marks' => 0.25,
        'duration_minutes' => 60,
    ]);

    /** The paper's MCQs as [id => [correct option, a wrong option]]. */
    $this->answerKey = fn (BoardQuestionPaper $paper): array => $paper->mcqQuestions->mapWithKeys(fn ($mcq) => [
        $mcq->id => [
            collect($mcq->options)->firstWhere('is_correct', true)['option'],
            collect($mcq->options)->firstWhere('is_correct', false)['option'],
        ],
    ])->all();

    /** Gives a student their role's free plan with these conditions. */
    $this->freeStudentPlan = fn (array $conditions): SubscriptionPlan => SubscriptionPlan::factory()->forStudents()->withConditions($conditions)->create([
        'price' => 0,
        'is_default_free' => true,
    ]);
});

describe('a student sitting a past paper', function () {
    it('sits the whole paper as their own practice, marked with the paper\'s negative marking, the answers shown at once', function () {
        $student = User::factory()->student()->create();
        subscribeToEverything($student);

        $attempt = $this->exams->startPractice($student, $this->paper);
        $exam = $attempt->exam;

        expect($exam->isPastPaper())->toBeTrue()
            ->and($exam->generation_mode)->toBe(GenerationMode::PastPaper)
            ->and($exam->duration_minutes)->toBe(60)
            ->and((float) $exam->total_marks)->toBe(3.0)
            ->and($exam->showsAnswersToStudents())->toBeTrue()
            ->and($exam->questions()->count())->toBe(0);

        [$first, $second] = array_keys(($this->answerKey)($this->paper));
        $key = ($this->answerKey)($this->paper);

        $attempt->recordAnswers([$first => $key[$first][0], $second => $key[$second][1]]);
        $attempt->submitAndAutoGrade();

        // 1 right, 1 wrong (-0.25), 1 blank.
        expect((float) $attempt->refresh()->total_score)->toBe(0.75)
            ->and($attempt->answersByQuestion()->keys()->sort()->values()->all())->toBe([$first, $second])
            ->and($attempt->answers()->whereNotNull('question_id')->exists())->toBeFalse()
            // A past paper's questions are not the pool's: no mistakes list entry.
            ->and(DB::table('student_question_mistakes')->count())->toBe(0);
    });

    it('shows the questions with where they came from and the explanation on the result', function () {
        $student = User::factory()->student()->create();
        subscribeToEverything($student);
        $mcq = $this->paper->mcqQuestions->first();
        $mcq->update(['explanation' => '<p>Because of osmosis.</p>']);

        $attempt = $this->exams->startPractice($student, $this->paper->refresh());
        $attempt->submitAndAutoGrade();

        $html = view('components.exam-result', ['attempt' => $attempt->refresh()->load('answers'), 'slot' => ''])->render();

        expect($html)->toContain($this->paper->label())
            ->toContain('Because of osmosis.');
    });

    it('refuses a paper that is not approved, or has no MCQ to sit online', function (Closure $paper) {
        $student = User::factory()->student()->create();
        subscribeToEverything($student);

        expect(fn () => $this->exams->startPractice($student, $paper()))->toThrow(ValidationException::class);
        expect(Exam::count())->toBe(0);
    })->with([
        'pending' => [fn () => BoardQuestionPaper::factory()->admission()->withMcqs(2)->create()],
        'CQ only' => [fn () => BoardQuestionPaper::factory()->admission()->approved()->create()],
    ]);
});

describe('what a plan allows of past papers', function () {
    it('counts past papers in a rolling period of their own, apart from the self-practice limits', function () {
        $student = User::factory()->student()->create();
        ($this->freeStudentPlan)([
            ($this->condition)(PlanConditionType::DailyExamLimit, ['limit' => 1]),
            ($this->condition)(PlanConditionType::PastPapers, ['limit' => 1, 'per_days' => 7]),
        ]);

        $this->exams->startPractice($student, $this->paper);
        $grant = $this->limits->grantsFor($student)[0];

        expect($this->limits->usageOf($student, $grant)['today'])->toBe(0)
            ->and($this->limits->pastPaperUsageOf($student, $grant))->toBe(1);

        expect(fn () => $this->exams->startPractice($student, $this->paper))
            ->toThrow(ValidationException::class, 'You can take the next one from');

        $this->travel(7)->days();
        $this->travel(1)->minutes();

        expect($this->exams->startPractice($student, $this->paper))->toBeInstanceOf(ExamAttempt::class);
    });

    it('keeps the paper to the kinds, sources and age the plan reaches, and needs the feature', function (Closure $conditions, bool $allowed) {
        $student = User::factory()->student()->create();
        ($this->freeStudentPlan)($conditions());

        expect($this->limits->allowsExam($student, Exam::factory()->make(['board_question_paper_id' => $this->paper->id])))->toBe($allowed);
    })->with([
        'no condition at all' => [fn () => [], true],
        'medical papers only' => [fn () => [SubscriptionPlanFactory::condition(PlanConditionType::PastPapers, ['source_types' => [PaperSourceType::Medical->value]])], true],
        'university papers only' => [fn () => [SubscriptionPlanFactory::condition(PlanConditionType::PastPapers, ['source_types' => [PaperSourceType::University->value]])], false],
        'another source only' => [fn () => [SubscriptionPlanFactory::condition(PlanConditionType::PastPapers, ['board_ids' => [Board::factory()->create()->id]])], false],
        'papers at least 5 years old (2018 is)' => [fn () => [SubscriptionPlanFactory::condition(PlanConditionType::PastPapers, ['min_age_years' => 5])], true],
        'papers at least 20 years old' => [fn () => [SubscriptionPlanFactory::condition(PlanConditionType::PastPapers, ['min_age_years' => 20])], false],
        'features without past papers' => [fn () => [SubscriptionPlanFactory::condition(PlanConditionType::Features, ['features' => [PlanFeature::OnlineExam->value]])], false],
    ]);

    it('keeps a board\'s paper to the classes and subjects the plan covers', function () {
        $student = User::factory()->student()->create();
        $classSubject = ClassSubject::factory()->create();
        $boardPaper = BoardQuestionPaper::factory()->approved()->withMcqs(1)->create(['class_subject_id' => $classSubject->id]);
        ($this->freeStudentPlan)([
            ($this->condition)(PlanConditionType::Classes, ['class_ids' => [ClassSubject::factory()->create()->academic_class_id]]),
        ]);

        expect(fn () => $this->exams->startPractice($student, $boardPaper))->toThrow(ValidationException::class)
            // An admission paper has no class to be outside of.
            ->and($this->exams->startPractice($student, $this->paper))->toBeInstanceOf(ExamAttempt::class);
    });
});

describe('a teacher setting a past paper', function () {
    it('makes a draft exam of the whole paper that guests sit online, with per-question results', function () {
        $teacher = User::factory()->teacher()->create();
        subscribeToEverything($teacher);

        $exam = $this->exams->createTeacherExam($teacher, $this->paper, ExamDeliveryMode::Online, 45, 0.5);

        expect($exam->status->value)->toBe('draft')
            ->and($exam->duration_minutes)->toBe(45)
            ->and($exam->negative_marks)->toBe(0.5)
            ->and($exam->showsAnswersToStudents())->toBeFalse();

        $exam->publish();
        $key = ($this->answerKey)($this->paper);
        [$first, $second] = array_keys($key);

        $attempt = ExamAttempt::startForGuest($exam->refresh(), 'Rafi', '01711111111');
        $attempt->recordAnswers([$first => $key[$first][0], $second => $key[$second][1]]);
        $attempt->submitAndAutoGrade();

        expect((float) $attempt->refresh()->total_score)->toBe(0.5);

        // The share link's page renders an admission paper's exam too.
        $this->get(route('guest-exam.show', $exam->share_token))->assertOk()->assertSee($this->paper->board->display_name);

        $stats = app(ExamResultSheet::class)->questionStatsFor($exam)->keyBy(fn (array $row) => $row['question']->id);

        expect($stats[$first]['correct'])->toBe(1)
            ->and($stats[$second]['wrong'])->toBe(1)
            ->and($stats->count())->toBe(3);
    });

    it('prints the whole paper, CQ and answers included', function () {
        $teacher = User::factory()->teacher()->create();
        subscribeToEverything($teacher);
        $cq = BoardCqQuestion::factory()->create(['board_question_paper_id' => $this->paper->id, 'question_text' => '<p>The stimulus</p>']);
        $cq->parts()->create(['part_type' => CqPartType::Knowledge, 'part_order' => 1, 'part_text' => '<p>What is a cell?</p>', 'answer' => '<p>The unit of life.</p>', 'marks' => 1]);

        $exam = $this->exams->createTeacherExam($teacher, $this->paper->refresh(), ExamDeliveryMode::Both, 60);

        $this->actingAs($teacher)
            ->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 1]))
            ->assertOk()
            ->assertSee('The stimulus', escape: false)
            ->assertSee('The unit of life.', escape: false)
            ->assertSee($this->paper->label());

        // Total marks: online ones only (the 3 MCQs).
        expect((float) $exam->total_marks)->toBe(3.0);
    });

    it('needs print in the teacher\'s plan to print a past paper', function () {
        $teacher = User::factory()->teacher()->create();
        $plan = SubscriptionPlan::factory()->withConditions([
            ($this->condition)(PlanConditionType::Features, ['features' => [PlanFeature::OnlineExam->value, PlanFeature::PastPapers->value]]),
        ])->create();
        Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

        expect(fn () => $this->exams->createTeacherExam($teacher, $this->paper, ExamDeliveryMode::Offline, 60))->toThrow(ValidationException::class);
        expect($this->exams->createTeacherExam($teacher, $this->paper, ExamDeliveryMode::Online, 60))->toBeInstanceOf(Exam::class);
    });
});

describe('the past papers page', function () {
    it('lets a student sit an approved paper and takes them into the exam, marking which papers their plan reaches', function () {
        $student = User::factory()->student()->create();
        ($this->freeStudentPlan)([
            ($this->condition)(PlanConditionType::PastPapers, ['min_age_years' => 5]),
        ]);
        $recent = BoardQuestionPaper::factory()->admission()->approved()->withMcqs(1)->create(['year' => (int) date('Y')]);
        $pending = BoardQuestionPaper::factory()->admission()->withMcqs(1)->create();
        $this->actingAs($student);
        Filament::setCurrentPanel(Filament::getPanel('student'));

        $page = livewire(StudentPastPapers::class)
            ->assertCanSeeTableRecords([$this->paper, $recent])
            ->assertCanNotSeeTableRecords([$pending])
            ->assertTableColumnStateSet('in_plan', true, $this->paper)
            ->assertTableColumnStateSet('in_plan', false, $recent)
            ->callAction(TestAction::make('sit')->table($this->paper));

        $attempt = ExamAttempt::sole();

        $page->assertRedirect(TakeExamPage::urlFor($attempt));
        expect($attempt->student_id)->toBe($student->id);

        // The exam page and the result page both render an admission paper
        // — which has no subject — and say where each question came from.
        livewire(TakeExamPage::class, ['attempt' => $attempt])
            ->assertOk()
            ->assertSee($this->paper->board->display_name)
            ->assertSee($this->paper->label())
            ->call('submit');

        livewire(ExamResultPage::class, ['attempt' => $attempt->refresh()])
            ->assertOk()
            ->assertSee($this->paper->label());

        // Outside the plan: refused with a message, nothing started.
        livewire(StudentPastPapers::class)
            ->callAction(TestAction::make('sit')->table($recent))
            ->assertNotified();

        expect(ExamAttempt::count())->toBe(1);
    });

    it('shows a student only the CQs of a paper before they sit it, with the answers', function () {
        $student = User::factory()->student()->create();
        $cq = BoardCqQuestion::factory()->create(['board_question_paper_id' => $this->paper->id, 'question_text' => '<p>The stimulus</p>']);
        $cq->parts()->create(['part_type' => CqPartType::Knowledge, 'part_order' => 1, 'part_text' => '<p>K</p>', 'answer' => '<p>The K answer</p>', 'marks' => 1]);
        $mcqText = $this->paper->mcqQuestions->first()->question_text;
        $this->actingAs($student);
        Filament::setCurrentPanel(Filament::getPanel('student'));

        livewire(StudentPastPapers::class)
            ->mountAction(TestAction::make('preview')->table($this->paper))
            ->assertMountedActionModalSeeHtml('The K answer')
            ->assertMountedActionModalDontSeeHtml($mcqText);
    });

    it('lets a teacher set a paper as a print exam, which lands in their exams list', function () {
        $teacher = User::factory()->teacher()->create();
        subscribeToEverything($teacher);
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(TeacherPastPapers::class)
            ->callAction(TestAction::make('setAsExam')->table($this->paper), [
                'delivery_mode' => ExamDeliveryMode::Offline->value,
                'duration_minutes' => 90,
            ])
            ->assertHasNoFormErrors()
            ->assertRedirect(ExamResource::getUrl());

        expect(Exam::sole())
            ->board_question_paper_id->toBe($this->paper->id)
            ->delivery_mode->toBe(ExamDeliveryMode::Offline)
            ->duration_minutes->toBe(90)
            ->negative_marks->toBeNull();
    });
});

describe('the paper itself', function () {
    it('is frozen once anyone has sat it', function () {
        $student = User::factory()->student()->create();
        subscribeToEverything($student);

        expect($this->paper->isEditableBy($this->admin))->toBeTrue();

        $this->exams->startPractice($student, $this->paper);

        expect($this->paper->isEditableBy($this->admin))->toBeFalse();
    });

    it('says where it came from by the source\'s short name in the active language', function () {
        $board = Board::factory()->create(['short_name' => 'DU', 'short_name_bn' => 'ঢাবি']);
        $paper = BoardQuestionPaper::factory()->admission()->create(['board_id' => $board->id, 'year' => 2023, 'unit' => 'Ka']);

        app()->setLocale('en');
        expect($paper->label())->toBe('DU 2023 · Ka unit');

        app()->setLocale('bn');
        expect($paper->label())->toStartWith('ঢাবি 2023');
    });

    it('tells an admission paper already entered apart, even without a class and subject', function () {
        expect(BoardQuestionPaper::isTaken($this->paper->board_id, null, 2018, ''))->toBeTrue()
            ->and(BoardQuestionPaper::isTaken($this->paper->board_id, null, 2018, 'Kha'))->toBeFalse()
            ->and(BoardQuestionPaper::isTaken($this->paper->board_id, null, 2018, '', exceptId: $this->paper->id))->toBeFalse();
    });
});

describe('paying staff per paper', function () {
    beforeEach(function () {
        BillingSetting::create(['paper_rates' => [PaperSourceType::Medical->value => 300, PaperSourceType::Board->value => 100]]);
        $this->staff = User::factory()->staff()->create();
    });

    it('pays the rate for the paper\'s kind once, when it is approved', function () {
        $paper = BoardQuestionPaper::factory()->admission()->withMcqs(1)->create(['created_by' => $this->staff->id]);

        $paper->approve($this->admin);
        $paper->reject($this->admin, 'Typo');
        $paper->approve($this->admin);

        expect($paper->staffEarning()->count())->toBe(1)
            ->and((float) $paper->staffEarning->amount)->toBe(300.0)
            ->and($this->staff->staffProfile->total_questions_approved)->toBe(0);

        // A later change of rate doesn't touch it.
        BillingSetting::current()->update(['paper_rates' => [PaperSourceType::Medical->value => 999]]);
        expect((float) $paper->staffEarning()->first()->amount)->toBe(300.0);
    });

    it('pays nothing for a rejected paper, or for one an admin entered', function () {
        $rejected = BoardQuestionPaper::factory()->admission()->create(['created_by' => $this->staff->id]);
        $rejected->reject($this->admin, 'Incomplete');

        BoardQuestionPaper::factory()->admission()->create(['created_by' => $this->admin->id])->approve($this->admin);

        expect(DB::table('staff_earnings')->count())->toBe(0);
    });

    it('sends a staff member\'s rejected paper back for review when they change it', function () {
        $paper = BoardQuestionPaper::factory()->admission()->create(['created_by' => $this->staff->id]);
        $paper->reject($this->admin, 'Fix question 3');

        $paper->markEditedBy($this->staff);

        expect($paper->refresh()->status)->toBe(QuestionStatus::Pending)
            ->and($paper->rejection_reason)->toBeNull();
    });
});
