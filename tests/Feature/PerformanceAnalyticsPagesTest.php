<?php

use App\Enums\ExamType;
use App\Enums\PlanConditionType;
use App\Enums\PlanFeature;
use App\Filament\Student\Widgets\StudentAnalyticsLockedWidget;
use App\Filament\Support\Widgets\Performance\ChapterPerformanceWidget;
use App\Filament\Support\Widgets\Performance\PerformanceStatsWidget;
use App\Filament\Support\Widgets\Performance\ScoreTrendChartWidget;
use App\Filament\Support\Widgets\Performance\SubjectPerformanceWidget;
use App\Filament\Teacher\Pages\StudentPerformanceReport;
use App\Filament\Teacher\Widgets\TeacherAnalyticsLockedWidget;
use App\Filament\Teacher\Widgets\TeacherClassTrendChartWidget;
use App\Filament\Teacher\Widgets\TeacherClassWeakChaptersWidget;
use App\Filament\Teacher\Widgets\TeacherStudentsNeedingAttentionWidget;
use App\Filament\Teacher\Widgets\TeacherStudentsPerformanceWidget;
use App\Models\AttemptAnswer;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    BillingSetting::current()->fill(['performance_min_questions' => 1])->save();

    $this->student = User::factory()->student()->create(['name' => 'Rahim']);
    $this->teacher = User::factory()->teacher()->create();
    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'name' => 'Motion']);
    $this->student->update(['academic_class_id' => $this->classSubject->academic_class_id]);

    /** A paid plan for the user's role that includes the analytics, or only printing. */
    $this->subscribe = function (User $user, PlanFeature $feature = PlanFeature::Analytics): void {
        $plan = SubscriptionPlan::factory()->pro()->withConditions([
            SubscriptionPlanFactory::condition(PlanConditionType::Features, ['features' => [$feature->value]]),
        ])->create(['target_role' => $user->role->subscriptionTargetRole()]);

        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
    };

    /** The student sits a teacher's exam of one MCQ, answers out, and answers it `$answer`. */
    $this->sitTeacherExam = function (string $answer, ?User $teacher = null): void {
        $question = Question::factory()->approved()->for($this->chapter)->create([
            'options' => [
                ['option' => 'right', 'image' => null, 'is_correct' => true],
                ['option' => 'wrong', 'image' => null, 'is_correct' => false],
            ],
            'marks' => 1,
        ]);
        $exam = Exam::factory()->published()->create([
            'title' => 'Class test',
            'created_by' => ($teacher ?? $this->teacher)->id,
            'exam_type' => ExamType::TeacherExam,
            'class_subject_id' => $this->classSubject->id,
            'answers_released_at' => now(),
        ]);
        $exam->questions()->attach($question->id, ['order_index' => 1, 'marks_override' => null]);
        $exam->recalculateTotalMarks();

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id, 'student_id' => $this->student->id]);
        AttemptAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'student_answer' => $answer]);
        $attempt->submitAndAutoGrade();
    };
});

describe('the student', function () {
    beforeEach(function () {
        Filament::setCurrentPanel(Filament::getPanel('student'));
        $this->actingAs($this->student);
    });

    it('sees the locked preview, and none of the analytics, without a plan that includes them', function () {
        ($this->subscribe)($this->student, PlanFeature::Print);

        expect(StudentAnalyticsLockedWidget::canView())->toBeTrue()
            ->and(PerformanceStatsWidget::canView())->toBeFalse()
            ->and(SubjectPerformanceWidget::canView())->toBeFalse();

        livewire(StudentAnalyticsLockedWidget::class)->assertSee(__('See plans'));
        $this->get('/student')->assertOk();
    });

    it('sees their analytics with a paid plan that includes them', function () {
        ($this->subscribe)($this->student);
        ($this->sitTeacherExam)('right');

        expect(StudentAnalyticsLockedWidget::canView())->toBeFalse();

        $this->get('/student')->assertOk();
        livewire(PerformanceStatsWidget::class)->assertSee('100%');
        livewire(ScoreTrendChartWidget::class)->assertOk();
        livewire(SubjectPerformanceWidget::class)
            ->assertSee($this->classSubject->label())
            ->assertSee(__('Practise'));
    });
});

describe('the teacher', function () {
    beforeEach(function () {
        Filament::setCurrentPanel(Filament::getPanel('teacher'));
        $this->actingAs($this->teacher);
    });

    it('sees the locked preview without a plan that includes the analytics', function () {
        expect(TeacherAnalyticsLockedWidget::canView())->toBeTrue()
            ->and(TeacherStudentsPerformanceWidget::canView())->toBeFalse();

        $this->get('/teacher')->assertOk();
    });

    it('sees the class and each student with a paid plan that includes the analytics', function () {
        ($this->subscribe)($this->teacher);
        ($this->sitTeacherExam)('wrong');

        $this->get('/teacher')->assertOk();
        livewire(TeacherClassTrendChartWidget::class)->assertOk();
        livewire(TeacherClassWeakChaptersWidget::class)->assertSee('Motion');
        livewire(TeacherStudentsPerformanceWidget::class)->assertCanSeeTableRecords([$this->student]);
        livewire(TeacherStudentsNeedingAttentionWidget::class)->assertOk();
    });

    it('opens a page about one of their students, from their own exams', function () {
        ($this->subscribe)($this->teacher);
        ($this->sitTeacherExam)('wrong');

        $this->get(StudentPerformanceReport::getUrl(['student' => $this->student->id], panel: 'teacher'))
            ->assertOk()
            ->assertSee(__(":name's performance", ['name' => 'Rahim']));

        livewire(ChapterPerformanceWidget::class, ['studentId' => $this->student->id])
            ->assertSee('Motion')
            ->assertSee('0%');

        // Practising is the student's own business, not a button for the teacher.
        livewire(SubjectPerformanceWidget::class, ['studentId' => $this->student->id])->assertDontSee(__('Practise'));
    });

    it('cannot look at a student who has sat none of their exams', function () {
        ($this->subscribe)($this->teacher);
        ($this->sitTeacherExam)('right', User::factory()->teacher()->create());

        $this->get(StudentPerformanceReport::getUrl(['student' => $this->student->id], panel: 'teacher'))->assertNotFound();

        livewire(PerformanceStatsWidget::class, ['studentId' => $this->student->id])->assertNotFound();
    });
});
