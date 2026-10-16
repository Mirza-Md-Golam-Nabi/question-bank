<?php

use App\Enums\Difficulty;
use App\Enums\ExamDeliveryMode;
use App\Enums\PlanConditionType;
use App\Enums\PlanDuration;
use App\Enums\PlanFeature;
use App\Enums\QuestionType;
use App\Filament\Student\Pages\GeneratePracticeExam;
use App\Filament\Student\Pages\MyProfile as StudentMyProfile;
use App\Filament\Teacher\Pages\MySubscription as TeacherMySubscription;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use App\Services\SubscriptionLimitService;
use App\Services\SubscriptionPurchaseService;
use App\Services\TeacherExamBuilder;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->purchases = app(SubscriptionPurchaseService::class);
    $this->admin = User::factory()->admin()->create();
    BillingSetting::create(['bkash_number' => '01700000000']);

    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);

    $this->condition = fn (PlanConditionType $type, array $data): array => SubscriptionPlanFactory::condition($type, $data);

    $this->payment = fn (string $transactionId = 'TRX1', array $picks = []): array => [
        'payer_provider' => 'bkash',
        'payer_number' => '01811111111',
        'transaction_id' => $transactionId,
        ...$picks,
    ];

    /** A plan where the buyer picks one subject of the given classes. */
    $this->oneSubjectPlan = fn (array $classIds = []): SubscriptionPlan => SubscriptionPlan::factory()->withConditions([
        ($this->condition)(PlanConditionType::Classes, ['class_ids' => $classIds, 'pick_count' => 1]),
        ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [], 'pick_count' => 1]),
    ])->create(['price' => 149, 'duration_unit' => PlanDuration::Years, 'duration_length' => 1]);
});

describe('buying a plan', function () {
    it('keeps the class and subject the buyer picked, and a copy of the plan\'s conditions', function () {
        $teacher = User::factory()->teacher()->create();
        $plan = ($this->oneSubjectPlan)();
        $picks = ['class_ids' => $this->classSubject->academic_class_id, 'subject_ids' => $this->classSubject->subject_id];

        $this->purchases->approve($this->purchases->request($teacher, $plan, ($this->payment)('TRX1', $picks)), $this->admin);

        // A later edit of the plan doesn't touch what was bought.
        $plan->update(['conditions' => []]);
        $subscription = $teacher->activeSubscription();

        expect($subscription->selections)->toBe([
            'class_ids' => [$this->classSubject->academic_class_id],
            'subject_ids' => [$this->classSubject->subject_id],
        ])
            ->and($subscription->conditions)->not->toBe([])
            ->and($subscription->ends_at->isSameDay(now()->addYear()))->toBeTrue();

        expect(app(SubscriptionLimitService::class)->allows($teacher, $this->classSubject))->toBeTrue()
            ->and(app(SubscriptionLimitService::class)->allows($teacher, ClassSubject::factory()->create()))->toBeFalse();
    });

    it('refuses picks the plan does not offer', function (array $picks) {
        $teacher = User::factory()->teacher()->create();
        $plan = ($this->oneSubjectPlan)([$this->classSubject->academic_class_id]);

        expect(fn () => $this->purchases->request($teacher, $plan, ($this->payment)('TRX1', $picks)))
            ->toThrow(ValidationException::class);
        expect($teacher->payments()->count())->toBe(0);
    })->with([
        'nothing picked' => [[]],
        'a class the plan does not list' => [fn () => ['class_ids' => AcademicClass::factory()->create()->id, 'subject_ids' => $this->classSubject->subject_id]],
        'a subject not taught in the class' => [fn () => ['class_ids' => $this->classSubject->academic_class_id, 'subject_ids' => ClassSubject::factory()->create()->subject_id]],
    ]);

    it('moves a student\'s profile class to the class they buy for', function () {
        $otherClass = AcademicClass::factory()->create();
        $student = User::factory()->student()->create(['academic_class_id' => $otherClass->id]);
        $plan = SubscriptionPlan::factory()->forStudents()->withConditions([
            ($this->condition)(PlanConditionType::Classes, ['class_ids' => [], 'pick_count' => 1]),
        ])->create(['price' => 79]);

        $this->purchases->request($student, $plan, ($this->payment)('TRX1', ['class_ids' => $this->classSubject->academic_class_id]));

        expect($student->fresh()->academic_class_id)->toBe($this->classSubject->academic_class_id);
    });

    it('gives the free trial at once, but only once per account', function () {
        $teacher = User::factory()->teacher()->create();
        $trial = SubscriptionPlan::factory()->trial()->create();

        $payment = $this->purchases->request($teacher, $trial, []);

        expect($payment->isSuccessful())->toBeTrue()
            ->and($teacher->activeSubscription()->ends_at->isSameDay(now()->addDays(7)))->toBeTrue();

        $teacher->subscriptions()->update(['ends_at' => now()->subDay()]);

        expect(fn () => $this->purchases->request($teacher, $trial, []))->toThrow(ValidationException::class);
    });

    it('ends an "until the exam" plan on its date, and stops selling it after that date', function () {
        $this->travelTo('2027-01-10 09:00:00');
        $student = User::factory()->student()->create();
        $plan = SubscriptionPlan::factory()->forStudents()->withConditions([])->create([
            'price' => 349,
            'duration_unit' => PlanDuration::UntilDate,
            'ends_on' => '2027-04-30',
        ]);

        $this->purchases->approve($this->purchases->request($student, $plan, ($this->payment)()), $this->admin);

        expect($student->activeSubscription()->ends_at->toDateTimeString())->toBe('2027-04-30 23:59:59');

        $this->travelTo('2027-05-01 09:00:00');

        expect($this->purchases->isAvailableTo(User::factory()->student()->create(), $plan))->toBeFalse();
    });

    it('does not sell a plan the admin has taken off sale', function () {
        $plan = SubscriptionPlan::factory()->pro()->create(['is_active' => false]);

        expect($this->purchases->isAvailableTo(User::factory()->teacher()->create(), $plan))->toBeFalse();
    });

    it('starts the trial from the my-subscription page, showing each plan\'s conditions', function () {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));
        $trial = SubscriptionPlan::factory()->trial()->create(['name' => 'Seven days free']);
        SubscriptionPlan::factory()->withConditions([
            ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [], 'pick_count' => 1]),
        ])->create(['name' => 'One subject', 'price' => 149]);

        $page = livewire(TeacherMySubscription::class)
            ->assertSee('Seven days free')
            ->assertSee('Any 1 subject of your choice')
            ->callAction('buy', arguments: ['plan' => $trial->id])
            ->assertHasNoActionErrors();

        expect($teacher->activeSubscription()->plan_id)->toBe($trial->id);

        // Off the plans on sale once used — straight away, on the same page.
        $page->assertDontSee('Start free trial');
        expect($page->instance()->plans()->pluck('id')->all())->not->toContain($trial->id);
    });

    it('sells a several-class plan\'s subject only when every one of its classes teaches it', function () {
        $teacher = User::factory()->teacher()->create();
        $junior = AcademicClass::factory()->create();
        $ssc = AcademicClass::factory()->create();
        $common = ClassSubject::factory()->create(['academic_class_id' => $junior->id])->subject;
        ClassSubject::factory()->create(['academic_class_id' => $ssc->id, 'subject_id' => $common->id]);
        $groupSubject = ClassSubject::factory()->create(['academic_class_id' => $ssc->id])->subject;
        $plan = SubscriptionPlan::factory()->withConditions([
            ($this->condition)(PlanConditionType::Classes, ['class_ids' => [$junior->id, $ssc->id]]),
            ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [], 'pick_count' => 1]),
        ])->create(['price' => 449]);

        expect($plan->planConditions()->pickableSubjectIds([$junior->id, $ssc->id]))->toBe([$common->id]);

        expect(fn () => $this->purchases->request($teacher, $plan, ($this->payment)('TRX1', ['subject_ids' => $groupSubject->id])))
            ->toThrow(ValidationException::class, 'This subject is not taught in every class of the plan.');

        $this->purchases->request($teacher, $plan, ($this->payment)('TRX2', ['subject_ids' => $common->id]));

        expect($teacher->payments()->count())->toBe(1);
    });

    it('offers in the buy form only the subjects the plan lets the buyer pick, once a class is chosen', function () {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));
        $otherSubjectId = ClassSubject::factory()->create()->subject_id;
        $plan = ($this->oneSubjectPlan)();

        livewire(TeacherMySubscription::class)
            ->mountAction('buy', arguments: ['plan' => $plan->id])
            ->assertFormFieldExists('subject_ids', 'mountedActionSchema0', fn ($field): bool => $field->getOptions() === [])
            ->fillForm(['class_ids' => $this->classSubject->academic_class_id], 'mountedActionSchema0')
            ->assertFormFieldExists('subject_ids', 'mountedActionSchema0', fn ($field): bool => array_keys($field->getOptions()) === [$this->classSubject->subject_id])
            ->assertFormFieldExists('subject_ids', 'mountedActionSchema0', fn ($field): bool => ! array_key_exists($otherSubjectId, $field->getOptions()));
    });
});

describe('a teacher\'s exams', function () {
    beforeEach(function () {
        $this->teacher = User::factory()->teacher()->create();
        $this->builder = app(TeacherExamBuilder::class);
        $this->build = fn (array $questionIds, ExamDeliveryMode $mode = ExamDeliveryMode::Offline, ?ClassSubject $classSubject = null): Exam => $this->builder
            ->build($this->teacher, $classSubject ?? $this->classSubject, $mode, $questionIds, 'Test', 30);
    });

    it('cannot be saved without a plan, and is counted against the plan that allowed it', function () {
        $question = Question::factory()->approved()->for($this->chapter)->create();

        expect(fn () => ($this->build)([$question->id]))->toThrow(ValidationException::class);

        $subscription = subscribeToEverything($this->teacher);

        expect(($this->build)([$question->id])->subscription_id)->toBe($subscription->id);
    });

    it('can only be for a class and subject the plan covers', function () {
        Subscription::factory()->create([
            'user_id' => $this->teacher->id,
            'plan_id' => SubscriptionPlan::factory()->withConditions([
                ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [$this->classSubject->subject_id]]),
            ]),
        ]);
        $otherSubject = ClassSubject::factory()->create(['academic_class_id' => $this->classSubject->academic_class_id]);
        $elsewhere = Question::factory()->approved()->for(Chapter::factory()->create(['class_subject_id' => $otherSubject->id]))->create();

        expect(fn () => ($this->build)([$elsewhere->id], classSubject: $otherSubject))
            ->toThrow(ValidationException::class, 'does not include this class and subject');
    });

    it('cannot use questions of a difficulty the plan leaves out', function () {
        Subscription::factory()->create([
            'user_id' => $this->teacher->id,
            'plan_id' => SubscriptionPlan::factory()->withConditions([
                ($this->condition)(PlanConditionType::Difficulties, ['difficulties' => ['easy', 'medium']]),
            ]),
        ]);
        $hard = Question::factory()->approved()->for($this->chapter)->create(['difficulty' => Difficulty::Hard]);

        expect(fn () => ($this->build)([$hard->id]))->toThrow(ValidationException::class, 'Hard');
    });

    it('cannot be taken online on a plan without online exams', function () {
        Subscription::factory()->create([
            'user_id' => $this->teacher->id,
            'plan_id' => SubscriptionPlan::factory()->withConditions([
                ($this->condition)(PlanConditionType::Features, ['features' => [PlanFeature::Print->value]]),
            ]),
        ]);
        $mcq = Question::factory()->approved()->for($this->chapter)->create(['question_type' => QuestionType::Mcq]);

        expect(fn () => ($this->build)([$mcq->id], ExamDeliveryMode::Online))->toThrow(ValidationException::class);
        expect(($this->build)([$mcq->id], ExamDeliveryMode::Offline))->toBeInstanceOf(Exam::class);
    });

    it('cannot be printed on a plan without printing', function () {
        Subscription::factory()->create([
            'user_id' => $this->teacher->id,
            'plan_id' => SubscriptionPlan::factory()->withConditions([
                ($this->condition)(PlanConditionType::Features, ['features' => [PlanFeature::OnlineExam->value]]),
            ]),
        ]);
        $exam = Exam::factory()->delivery(ExamDeliveryMode::Both)->create(['created_by' => $this->teacher->id]);

        $this->actingAs($this->teacher)->get(route('filament.teacher.exams.print', $exam))->assertForbidden();
    });

    it('offers in the question picker only the classes and subjects the plan covers, and only its difficulties', function () {
        Subscription::factory()->create([
            'user_id' => $this->teacher->id,
            'plan_id' => SubscriptionPlan::factory()->withConditions([
                ($this->condition)(PlanConditionType::Subjects, ['subject_ids' => [$this->classSubject->subject_id]]),
                ($this->condition)(PlanConditionType::Difficulties, ['difficulties' => ['easy']]),
            ]),
        ]);
        $notCovered = ClassSubject::factory()->create();
        $easy = Question::factory()->approved()->for($this->chapter)->create(['difficulty' => Difficulty::Easy]);
        Question::factory()->approved()->for($this->chapter)->create(['difficulty' => Difficulty::Hard]);
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        $page = livewire(SelectQuestions::class)->set('data', [
            'exam_mode' => ExamDeliveryMode::Offline->value,
            'academic_class_id' => $this->classSubject->academic_class_id,
            'class_subject_id' => $this->classSubject->id,
            'chapter_id' => $this->chapter->id,
            'topic_id' => null,
            'question_type' => SelectQuestions::TYPE_BOTH,
        ]);

        expect($page->instance()->accessibleClassSubjectIds)->toBe([$this->classSubject->id])
            ->and($page->instance()->accessibleClassSubjectIds)->not->toContain($notCovered->id)
            ->and($page->instance()->questions->pluck('id')->all())->toBe([$easy->id]);
    });

    it('sends a teacher with no plan to the plans from the question picker', function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(SelectQuestions::class)->assertSee('You need a plan to build exams');
    });
});

describe('a student\'s practice', function () {
    beforeEach(function () {
        $this->student = User::factory()->student()->create(['academic_class_id' => $this->classSubject->academic_class_id]);
        SubscriptionPlan::factory()->free()->forStudents()->withConditions([
            ($this->condition)(PlanConditionType::Difficulties, ['difficulties' => ['easy']]),
            ($this->condition)(PlanConditionType::DailyExamLimit, ['limit' => 1]),
        ])->create();
        $this->easy = Question::factory()->approved()->for($this->chapter)->count(2)->create(['difficulty' => Difficulty::Easy]);
        $this->hard = Question::factory()->approved()->for($this->chapter)->create(['difficulty' => Difficulty::Hard]);
        $this->service = app(SelfPracticeExamService::class);
    });

    it('draws only easy questions on the free plan, one exam a day', function () {
        $attempt = $this->service->generateAuto($this->student, $this->classSubject, null, 10);

        expect($attempt->exam->questions->pluck('id')->sort()->values()->all())->toBe($this->easy->pluck('id')->sort()->values()->all())
            ->and($attempt->exam->subscription_id)->toBeNull();

        expect(fn () => $this->service->generateAuto($this->student, $this->classSubject, null, 10))
            ->toThrow(ValidationException::class);
    });

    it('refuses a hard question on the free plan, in either mode', function () {
        expect(fn () => $this->service->generateManual($this->student, $this->classSubject, [$this->hard->id]))
            ->toThrow(ValidationException::class, 'Hard');
        expect(fn () => $this->service->generateAuto($this->student, $this->classSubject, Difficulty::Hard, 5))
            ->toThrow(ValidationException::class, 'Hard');
    });

    it('opens every difficulty with a paid plan', function () {
        subscribeToEverything($this->student);

        $attempt = $this->service->generateManual($this->student, $this->classSubject, [$this->hard->id]);

        expect($attempt->exam->questions->pluck('id')->all())->toBe([$this->hard->id]);
    });

    it('offers only the free plan\'s difficulties and only the student\'s own class on the practice page', function () {
        $this->actingAs($this->student);
        Filament::setCurrentPanel(Filament::getPanel('student'));
        $otherClass = ClassSubject::factory()->create();

        livewire(GeneratePracticeExam::class)
            ->fillForm(['class_subject_id' => $this->classSubject->id])
            ->assertFormFieldExists('difficulty', fn ($field): bool => array_keys($field->getOptions()) === ['easy'])
            ->assertFormFieldExists('class_subject_id', fn ($field): bool => ! array_key_exists($otherClass->id, $field->getOptions()))
            // Another class's subject sent by hand gets nowhere.
            ->fillForm(['class_subject_id' => $otherClass->id, 'question_count' => 5])
            ->call('start');

        expect(Exam::count())->toBe(0);
    });

    it('tells a student without a class to set it on their profile, where it is saved', function () {
        $this->student->update(['academic_class_id' => null]);
        $this->actingAs($this->student);
        Filament::setCurrentPanel(Filament::getPanel('student'));

        livewire(GeneratePracticeExam::class)->assertSee('Set your class first');

        livewire(StudentMyProfile::class)
            ->fillForm(['academic_class_id' => $this->classSubject->academic_class_id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->student->fresh()->academic_class_id)->toBe($this->classSubject->academic_class_id);
    });
});
