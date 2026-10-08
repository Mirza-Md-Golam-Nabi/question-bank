<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Filament\Teacher\Resources\Exams\Pages\CreateExam;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\AttemptAnswer;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ExamResultSheet;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));
});

it('creates a draft exam from the approved pool of a chosen subject', function () {
    $subject = Subject::factory()->create();
    $chapter = Chapter::factory()->create();
    $question = Question::factory()->approved()->for($chapter)->create();

    // Make the question resolvable under the chosen subject by aligning the
    // chapter's class_subject to it.
    $chapter->classSubject()->update(['subject_id' => $subject->id]);

    livewire(CreateExam::class)
        ->fillForm([
            'title' => 'Midterm',
            'subject_id' => $subject->id,
            'duration_minutes' => 60,
            'questions' => [$question->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $exam = Exam::first();
    expect($exam->title)->toBe('Midterm');
    expect($exam->status)->toBe(ExamStatus::Draft);
    expect($exam->created_by)->toBe($this->teacher->id);
    expect($exam->questions)->toHaveCount(1);
});

it('publishes an exam, generating a share token and recalculating total marks', function () {
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 5]);
    Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => $plan->id]);

    $exam = Exam::factory()->create(['created_by' => $this->teacher->id]);
    $question = Question::factory()->approved()->for(Chapter::factory())->create(['marks' => 4]);
    $exam->questions()->attach([$question->id => ['order_index' => 1, 'marks_override' => null]]);

    livewire(ListExams::class)
        ->callTableAction('publish', $exam)
        ->assertHasNoTableActionErrors();

    $exam->refresh();
    expect($exam->status)->toBe(ExamStatus::Published);
    expect($exam->share_token)->not->toBeNull();
    expect((float) $exam->total_marks)->toBe(4.0);
});

it('does not offer the publish action once the monthly limit is reached', function () {
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 1]);
    Subscription::factory()->create(['user_id' => $this->teacher->id, 'plan_id' => $plan->id]);

    Exam::factory()->create(['created_by' => $this->teacher->id]);
    $secondExam = Exam::factory()->create(['created_by' => $this->teacher->id]);

    livewire(ListExams::class)
        ->assertTableActionHidden('publish', $secondExam);
});

it('shows the subject by its short name in the exams table, falling back to the full name', function () {
    $withShortName = Exam::factory()->create([
        'created_by' => $this->teacher->id,
        'subject_id' => Subject::factory()->create(['name' => 'Information and Communication Technology', 'short_name' => 'ICT'])->id,
    ]);
    $withoutShortName = Exam::factory()->create([
        'created_by' => $this->teacher->id,
        'subject_id' => Subject::factory()->create(['name' => 'Physics', 'short_name' => null])->id,
    ]);

    livewire(ListExams::class)
        ->assertTableColumnStateSet('subject.short_name', 'ICT', $withShortName)
        ->assertTableColumnStateSet('subject.short_name', 'Physics', $withoutShortName);
});

it('offers a copy-share-link icon only for an exam that has a share link', function () {
    $published = Exam::factory()->published()->create(['created_by' => $this->teacher->id]);
    $draft = Exam::factory()->create(['created_by' => $this->teacher->id]);

    livewire(ListExams::class)
        ->assertTableActionVisible('copyShareLink', $published)
        ->assertTableActionHidden('copyShareLink', $draft)
        // The link itself is what the button copies.
        ->assertSeeHtml($published->share_token);

    expect($published->shareUrl())->toBe(route('guest-exam.show', $published->share_token));
    expect($draft->shareUrl())->toBeNull();
});

describe('exam end time', function () {
    beforeEach(function () {
        $this->freezeTime();
        $this->exam = Exam::factory()->published()->create(['created_by' => $this->teacher->id]);
    });

    it('lets the teacher set, change and remove the end time from the exams table', function () {
        $endsAt = now()->addDay()->startOfMinute();

        livewire(ListExams::class)
            ->callTableAction('setEndTime', $this->exam, data: ['link_expires_at' => $endsAt->toDateTimeString()])
            ->assertHasNoTableActionErrors();

        expect($this->exam->fresh()->link_expires_at->equalTo($endsAt))->toBeTrue();

        livewire(ListExams::class)->callTableAction('setEndTime', $this->exam, data: ['link_expires_at' => null]);

        expect($this->exam->fresh()->link_expires_at)->toBeNull();
    });

    it('refuses an end time in the past', function () {
        livewire(ListExams::class)
            ->callTableAction('setEndTime', $this->exam, data: ['link_expires_at' => now()->subHour()->toDateTimeString()])
            ->assertHasTableActionErrors(['link_expires_at']);
    });

    it('stops the share link taking new attempts once the end time has passed', function () {
        $this->exam->closeLinkAt(now()->addHour());

        expect($this->exam->isAcceptingAttempts())->toBeTrue();
        $this->get(route('guest-exam.show', $this->exam->share_token))->assertOk()->assertDontSee('no longer active');

        $this->travel(61)->minutes();

        expect($this->exam->fresh()->isAcceptingAttempts())->toBeFalse();
        $this->get(route('guest-exam.show', $this->exam->share_token))->assertOk()->assertSee('no longer active');
        $this->post(route('guest-exam.start', $this->exam->share_token), ['guest_name' => 'Late', 'guest_contact' => '01700000001'])
            ->assertNotFound();
    });

    it('lets a student who started before the end time still submit after it', function () {
        $this->exam->closeLinkAt(now()->addMinutes(10));

        $this->post(route('guest-exam.start', $this->exam->share_token), ['guest_name' => 'Rahim', 'guest_contact' => '01700000002']);
        $attempt = ExamAttempt::sole();

        $this->travel(15)->minutes();

        $this->get(route('guest-exam.take', $attempt))->assertOk();
        $this->post(route('guest-exam.submit', $attempt))->assertOk();
    });

    it('has no end time to set on a print-only exam', function () {
        $offline = Exam::factory()->delivery(ExamDeliveryMode::Offline)->create(['created_by' => $this->teacher->id]);

        livewire(ListExams::class)
            ->assertTableActionVisible('setEndTime', $this->exam)
            ->assertTableActionHidden('setEndTime', $offline);
    });
});

it('shows the class of an exam before its subject, and a dash for an exam without one', function () {
    $classSubject = ClassSubject::factory()->create();
    $withClass = Exam::factory()->create([
        'created_by' => $this->teacher->id,
        'subject_id' => $classSubject->subject_id,
        'class_subject_id' => $classSubject->id,
    ]);
    $withoutClass = Exam::factory()->create(['created_by' => $this->teacher->id]);

    $page = livewire(ListExams::class)
        ->assertTableColumnStateSet('classSubject.academicClass.name', $classSubject->academicClass->name, $withClass)
        ->assertTableColumnStateSet('classSubject.academicClass.name', null, $withoutClass);

    $columns = array_keys($page->instance()->getTable()->getColumns());

    expect(array_search('classSubject.academicClass.name', $columns))
        ->toBe(array_search('subject.short_name', $columns) - 1);
});

describe('cancelling an exam', function () {
    beforeEach(function () {
        $this->exam = Exam::factory()->published()->create(['created_by' => $this->teacher->id]);
        $this->question = Question::factory()->approved()->for(Chapter::factory())->create([
            'options' => [
                ['option' => 'right', 'image' => null, 'is_correct' => true],
                ['option' => 'wrong', 'image' => null, 'is_correct' => false],
            ],
        ]);
        $this->exam->questions()->attach($this->question->id, ['order_index' => 1, 'marks_override' => null]);

        $this->sit = function (string $name, bool $submit = true, ?Exam $exam = null): ExamAttempt {
            $attempt = ExamAttempt::create([
                'exam_id' => ($exam ?? $this->exam)->id, 'is_guest' => true, 'guest_name' => $name,
                'guest_contact' => fake()->unique()->numerify('017########'), 'started_at' => now(),
            ])->fresh();
            $attempt->recordAnswers([$this->question->id => 'right']);

            if ($submit) {
                $attempt->submitAndAutoGrade();
            }

            return $attempt;
        };
    });

    it('deletes every attempt and answer of the exam, submitted or still in progress', function () {
        ($this->sit)('Submitted');
        ($this->sit)('Still writing', submit: false);

        expect($this->exam->cancel())->toBe(2);

        expect(ExamAttempt::count())->toBe(0);
        expect(AttemptAnswer::count())->toBe(0);
        expect(app(ExamResultSheet::class)->rowsFor($this->exam))->toBeEmpty();
    });

    it('leaves other exams and their results alone', function () {
        $otherExam = Exam::factory()->published()->create(['created_by' => $this->teacher->id]);
        $otherExam->questions()->attach($this->question->id, ['order_index' => 1, 'marks_override' => null]);
        $kept = ($this->sit)('Other exam student', exam: $otherExam);
        ($this->sit)('This exam student');

        $this->exam->cancel();

        expect(ExamAttempt::pluck('id')->all())->toBe([$kept->id]);
        expect(AttemptAnswer::where('attempt_id', $kept->id)->count())->toBe(1);
    });

    it('takes the exam back to a draft with its answers locked, keeping the questions and the link', function () {
        ($this->sit)('A student');
        $this->exam->releaseAnswers();
        $token = $this->exam->share_token;

        $this->exam->cancel();
        $exam = $this->exam->fresh();

        expect($exam->status)->toBe(ExamStatus::Draft);
        expect($exam->showsAnswersToStudents())->toBeFalse();
        expect($exam->questions)->toHaveCount(1);
        expect($exam->share_token)->toBe($token);
        // A draft's link takes no attempts, so nobody starts it mid-edit.
        expect($exam->isAcceptingAttempts())->toBeFalse();
    });

    it('makes the exam editable again, and publishable on the same link', function () {
        ($this->sit)('A student');
        $token = $this->exam->share_token;

        expect(app(TeacherExamBuilder::class)->canBeEdited($this->exam))->toBeFalse();

        livewire(ListExams::class)
            ->assertTableActionHidden('editQuestions', $this->exam)
            ->assertTableActionVisible('cancelExam', $this->exam)
            ->callTableAction('cancelExam', $this->exam)
            ->assertNotified();

        expect(app(TeacherExamBuilder::class)->canBeEdited($this->exam->fresh()))->toBeTrue();

        livewire(ListExams::class)
            ->assertTableActionVisible('editQuestions', $this->exam)
            ->assertTableActionHidden('cancelExam', $this->exam)
            ->callTableAction('publish', $this->exam);

        expect($this->exam->fresh()->status)->toBe(ExamStatus::Published);
        expect($this->exam->fresh()->share_token)->toBe($token);
    });

    it('offers no cancel on an exam nobody has started', function () {
        livewire(ListExams::class)->assertTableActionHidden('cancelExam', $this->exam);
    });

    it('does not let a teacher cancel another teacher\'s exam', function () {
        $othersExam = Exam::factory()->published()->create();
        ExamAttempt::create(['exam_id' => $othersExam->id, 'is_guest' => true, 'guest_name' => 'X', 'guest_contact' => '01700000001', 'started_at' => now()]);

        expect($this->teacher->can('update', $othersExam))->toBeFalse();
        livewire(ListExams::class)->assertCanNotSeeTableRecords([$othersExam]);
    });
});
