<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ExamRepeater;
use App\Services\PastPaperExamService;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    subscribeToEverything($this->teacher);
    $this->repeater = app(ExamRepeater::class);

    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);
    $this->author = User::factory()->teacher()->create();
    $this->questions = Question::factory()->approved()->count(3)->for($this->chapter)->create(['created_by' => $this->author->id]);

    $this->exam = app(TeacherExamBuilder::class)->build(
        $this->teacher, $this->classSubject, ExamDeliveryMode::Online, $this->questions->modelKeys(), 'Class test', 25, negativeMarks: 0.25,
    );
    $this->exam->publish();
    ExamAttempt::factory()->submitted()->create(['exam_id' => $this->exam->id]);
    $this->exam->releaseAnswers();
});

it('makes a new exam of the same paper, its own link and results, leaving the old one as it was', function () {
    $repeated = $this->repeater->repeat($this->exam, $this->teacher, 'Class test (2)', 30);

    expect($repeated->id)->not->toBe($this->exam->id)
        ->and($repeated->title)->toBe('Class test (2)')
        ->and($repeated->duration_minutes)->toBe(30)
        ->and((float) $repeated->negative_marks)->toBe(0.25)
        ->and($repeated->delivery_mode)->toBe(ExamDeliveryMode::Online)
        ->and($repeated->class_subject_id)->toBe($this->classSubject->id)
        ->and($repeated->status)->toBe(ExamStatus::Draft)
        ->and($repeated->share_token)->toBeNull()
        ->and($repeated->showsAnswersToStudents())->toBeFalse()
        ->and($repeated->attempts()->count())->toBe(0)
        ->and($repeated->questions()->pluck('questions.id')->all())->toBe($this->exam->questions()->pluck('questions.id')->all());

    $this->exam->refresh();
    expect($this->exam->status)->toBe(ExamStatus::Published)
        ->and($this->exam->attempts()->count())->toBe(1)
        ->and($this->exam->showsAnswersToStudents())->toBeTrue();
});

it('puts on the current approved version of a question, and leaves out one rejected or awaiting approval again', function () {
    [$edited, $rejected, $kept] = $this->questions->all();

    $this->actingAs($this->author);
    $revision = $edited->createRevisionWith([...$edited->only(['chapter_id', 'question_type', 'options', 'marks']), 'question_text' => '<p>Corrected</p>']);
    $rejected->reject(User::factory()->admin()->create(), 'Wrong');

    expect($this->repeater->preview($this->exam, $this->teacher))->toBe(['questionIds' => [$kept->id], 'leftOut' => 2]);

    $revision->approve(User::factory()->admin()->create());

    expect($this->repeater->preview($this->exam, $this->teacher)['questionIds'])->toEqualCanonicalizing([$revision->id, $kept->id]);
});

it('keeps a teacher\'s own question still awaiting approval', function () {
    $this->actingAs($this->teacher);
    $own = Question::factory()->for($this->chapter)->create(['created_by' => $this->teacher->id]);
    $exam = app(TeacherExamBuilder::class)->build($this->teacher, $this->classSubject, ExamDeliveryMode::Offline, [$own->id], 'Own', 20);

    expect($this->repeater->repeat($exam, $this->teacher, 'Own (2)', 20)->questions()->pluck('questions.id')->all())->toBe([$own->id]);
});

it('counts the new exam against the plan, like any new exam', function () {
    $limited = User::factory()->teacher()->create();
    Subscription::factory()->create(['user_id' => $limited->id, 'plan_id' => SubscriptionPlan::factory()->pro()->monthlyLimit(1)->create()->id]);
    $exam = app(TeacherExamBuilder::class)->build($limited, $this->classSubject, ExamDeliveryMode::Online, $this->questions->modelKeys(), 'Once', 20);

    expect(fn () => $this->repeater->repeat($exam, $limited, 'Twice', 20))->toThrow(ValidationException::class)
        ->and(Exam::query()->where('created_by', $limited->id)->count())->toBe(1);
});

it('refuses when none of the questions can be used any more', function () {
    $admin = User::factory()->admin()->create();
    $this->questions->each(fn (Question $question) => $question->reject($admin, 'Wrong'));

    expect(fn () => $this->repeater->repeat($this->exam, $this->teacher, 'Again', 20))->toThrow(ValidationException::class);
});

it('takes a whole past paper again', function () {
    $paper = BoardQuestionPaper::factory()->admission()->approved()->withMcqs(3)->create();
    $exam = app(PastPaperExamService::class)->createTeacherExam($this->teacher, $paper, ExamDeliveryMode::Online, 45, 0.5);

    $repeated = $this->repeater->repeat($exam, $this->teacher, 'Paper again', 40, now()->addDay());

    expect($repeated->board_question_paper_id)->toBe($paper->id)
        ->and($repeated->title)->toBe('Paper again')
        ->and($repeated->duration_minutes)->toBe(40)
        ->and((float) $repeated->negative_marks)->toBe(0.5)
        ->and($repeated->link_expires_at)->not->toBeNull();
});

it('lets only the exam\'s own teacher take a teacher exam again', function () {
    expect($this->repeater->canRepeat($this->exam, $this->teacher))->toBeTrue()
        ->and($this->repeater->canRepeat($this->exam, User::factory()->teacher()->create()))->toBeFalse()
        ->and($this->repeater->canRepeat(Exam::factory()->selfPractice()->create(), $this->teacher))->toBeFalse();
});

it('takes an exam again from the exams list', function () {
    Filament::setCurrentPanel('teacher');
    $this->actingAs($this->teacher);

    livewire(ListExams::class)
        ->callAction(TestAction::make('takeAgain')->table($this->exam), [
            'title' => 'Second group',
            'duration_minutes' => 25,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(Exam::query()->where('title', 'Second group')->sole()->questions()->count())->toBe(3);
});

it('is in the School panel too', function () {
    $school = User::factory()->school()->create();
    subscribeToEverything($school);
    $exam = app(TeacherExamBuilder::class)->build($school, $this->classSubject, ExamDeliveryMode::Online, $this->questions->modelKeys(), 'School test', 20);

    expect($this->repeater->repeat($exam, $school, 'School test (2)', 20)->created_by)->toBe($school->id)
        ->and($exam->fresh()->status)->toBe(ExamStatus::Draft);
});
