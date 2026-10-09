<?php

use App\Enums\ExamDeliveryMode;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id, 'order_index' => 1]);

    $this->ownPending = fn (): Question => Question::factory()->for($this->chapter)->create(['created_by' => $this->teacher->id]);

    $this->examWith = fn (Question ...$questions): Exam => app(TeacherExamBuilder::class)->build(
        $this->teacher,
        $this->classSubject,
        ExamDeliveryMode::Online,
        array_map(fn (Question $question): int => $question->id, $questions),
        'Class test',
        30,
    );
});

it('lists a teacher\'s own pending questions in the picker beside the approved pool, and nobody else\'s', function () {
    $ownPending = ($this->ownPending)();
    $approved = Question::factory()->approved()->for($this->chapter)->create(['created_by' => $this->otherTeacher->id]);
    Question::factory()->rejected()->for($this->chapter)->create(['created_by' => $this->teacher->id]);
    Question::factory()->for($this->chapter)->create(['created_by' => $this->otherTeacher->id]);

    $page = livewire(SelectQuestions::class)->set('data', [
        'exam_mode' => ExamDeliveryMode::Online->value,
        'academic_class_id' => $this->classSubject->academic_class_id,
        'class_subject_id' => $this->classSubject->id,
        'chapter_id' => $this->chapter->id,
        'topic_id' => null,
        'question_type' => SelectQuestions::TYPE_BOTH,
    ]);

    expect($page->instance()->questions->pluck('id')->all())->toEqualCanonicalizing([$ownPending->id, $approved->id]);
    $page->assertSee('Yours — awaiting approval');
});

it('lets a teacher put their own pending question on their exam', function () {
    $ownPending = ($this->ownPending)();

    $exam = ($this->examWith)($ownPending);

    expect($exam->questions->pluck('id')->all())->toBe([$ownPending->id]);
});

it('refuses a question the teacher may not use', function (array $attributes, bool $isOwn) {
    $question = Question::factory()->for($this->chapter)->create([
        'created_by' => $isOwn ? $this->teacher->id : $this->otherTeacher->id,
        ...$attributes,
    ]);

    expect(fn () => ($this->examWith)($question))->toThrow(ValidationException::class);
    expect(Exam::count())->toBe(0);
})->with([
    'someone else\'s pending question' => [[], false],
    'their own rejected question' => [['status' => 'rejected'], true],
    'their own pending question that is no longer the latest version' => [['is_latest' => false], true],
]);

it('keeps a pending question out of a student\'s self-practice', function () {
    $ownPending = ($this->ownPending)();
    $student = User::factory()->student()->create();

    $attempt = app(SelfPracticeExamService::class)->generateManual($student, $this->classSubject->subject_id, [$ownPending->id]);

    expect($attempt->exam->questions()->count())->toBe(0);
});

it('opens the question to everyone once the admin approves it, and leaves the exam as it was', function () {
    $ownPending = ($this->ownPending)();
    $exam = ($this->examWith)($ownPending);

    $ownPending->approve($this->admin);

    expect(Question::approvedPool()->whereKey($ownPending->id)->exists())->toBeTrue()
        ->and($exam->questions()->pluck('questions.id')->all())->toBe([$ownPending->id]);
});

it('keeps a rejected question on the exam it is already on, but off any new one', function () {
    $ownPending = ($this->ownPending)();
    $exam = ($this->examWith)($ownPending);

    $ownPending->reject($this->admin, 'Wrong answer marked.');

    expect($exam->questions()->pluck('questions.id')->all())->toBe([$ownPending->id]);
    expect(fn () => ($this->examWith)($ownPending->refresh()))->toThrow(ValidationException::class);
});

it('does not let the owner delete a question while it is on an exam', function () {
    $onExam = ($this->ownPending)();
    $free = ($this->ownPending)();
    $exam = ($this->examWith)($onExam);

    expect($this->teacher->can('delete', $onExam))->toBeFalse()
        ->and($this->teacher->can('delete', $free))->toBeTrue()
        ->and($this->admin->can('delete', $onExam))->toBeTrue();

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertTableActionHidden('delete', $onExam)
        ->assertTableActionVisible('delete', $free);

    // Off the exam again, it can go.
    $exam->questions()->detach();

    expect($this->teacher->can('delete', $onExam->refresh()))->toBeTrue();
});

it('freezes a pending question once a student has started an exam it is on', function () {
    $question = ($this->ownPending)();
    $exam = ($this->examWith)($question);

    expect($this->teacher->can('update', $question))->toBeTrue();

    ExamAttempt::factory()->create(['exam_id' => $exam->id]);

    expect($this->teacher->can('update', $question->refresh()))->toBeFalse()
        ->and($this->admin->can('update', $question))->toBeFalse();

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertTableActionHidden('edit', $question);
});

it('still lets an approved question be edited after it has been sat, since that makes a new version', function () {
    $question = ($this->ownPending)();
    $exam = ($this->examWith)($question);
    ExamAttempt::factory()->create(['exam_id' => $exam->id]);

    $question->approve($this->admin);

    expect($this->teacher->can('update', $question->refresh()))->toBeTrue();
});

it('lists a chapter\'s questions in the same number of queries however many of them are on exams', function () {
    $queries = [];

    // The first render of the page pays for what is looked up once and
    // then remembered, so it is left out of the comparison.
    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])->html();

    foreach ([2, 12] as $questionCount) {
        $chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);
        $questions = Question::factory()->for($chapter)->count($questionCount)->create(['created_by' => $this->teacher->id]);
        $exam = Exam::factory()->published()->create(['created_by' => $this->teacher->id]);
        $exam->questions()->attach($questions->pluck('id'));
        ExamAttempt::factory()->create(['exam_id' => $exam->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        livewire(ListQuestions::class, ['chapter' => $chapter->id])->assertOk()->html();
        DB::disableQueryLog();

        $queries[$questionCount] = count(DB::getQueryLog());
    }

    expect($queries[12])->toBe($queries[2]);
});
