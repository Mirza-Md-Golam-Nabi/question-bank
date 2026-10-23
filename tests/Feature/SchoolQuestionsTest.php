<?php

use App\Enums\QuestionStatus;
use App\Filament\Resources\Questions\QuestionResource as AdminQuestionResource;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Support\SchoolConnectionSignature;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->school = User::factory()->school()->googleAuthenticated()->create();
    $this->profile = SchoolProfile::factory()->connected('https://school.test:8443/app')->create(['user_id' => $this->school->id]);
    BillingSetting::create(['school_question_release_days' => 60]);

    $this->classSubject = ClassSubject::factory()->create();
    $this->chapter = Chapter::factory()->create(['class_subject_id' => $this->classSubject->id]);

    // What the School writes, signed in as it does on its "My questions".
    $this->writeQuestion = function (array $attributes = []): Question {
        $this->actingAs($this->school);

        return Question::factory()->for($this->chapter)->create([
            'created_by' => $this->school->id,
            'question_text' => '<p>The school asks this</p>',
            'options' => [
                ['option' => 'school right answer', 'image' => null, 'is_correct' => true],
                ['option' => 'school wrong answer', 'image' => null, 'is_correct' => false],
            ],
            'explanation' => '<p>Because the school says so</p>',
            ...$attributes,
        ]);
    };

    // A School exam with this question, ending when given (null = never).
    $this->onExam = function (Question $question, ?Carbon $endsAt): Exam {
        $exam = Exam::factory()->published()->create(['created_by' => $this->school->id, 'link_expires_at' => $endsAt]);
        $exam->questions()->attach($question->id);

        return $exam;
    };

    $this->released = fn (): array => Question::query()->releasedToSchoolStudents($this->school)->pluck('id')->all();

    $this->link = function (array $query = [], ?int $expires = null): string {
        $expires = (string) ($expires ?? now()->addHour()->timestamp);

        return '/school-questions/'.$this->profile->connection_code.'?'.http_build_query([
            'expires' => $expires,
            'signature' => SchoolConnectionSignature::signLink($this->profile->connection_secret, $this->profile->connection_code, $expires),
            ...$query,
        ]);
    };
});

it('keeps what a School writes as its own, never in the shared pool', function () {
    $question = ($this->writeQuestion)();

    expect($question->is_private)->toBeTrue()
        ->and($question->status)->toBe(QuestionStatus::Pending)
        ->and(Question::approvedPool()->pluck('id'))->not->toContain($question->id)
        ->and(Question::usableInExamBy($this->school)->pluck('id'))->toContain($question->id)
        ->and($this->school->can('import', Question::class))->toBeTrue();

    $teacher = User::factory()->teacher()->create();
    expect(Question::visibleTo($teacher)->pluck('id'))->not->toContain($question->id)
        ->and(Question::usableInExamBy($teacher)->pluck('id'))->not->toContain($question->id)
        ->and($teacher->can('view', $question))->toBeFalse();

    $this->actingAs(User::factory()->teacher()->create());
    expect(Question::factory()->create()->is_private)->toBeFalse();
});

it('keeps a School\'s questions away from Admins: not listed, not reviewed, not a duplicate of theirs', function () {
    $question = ($this->writeQuestion)();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    expect(AdminQuestionResource::getEloquentQuery()->pluck('id'))->not->toContain($question->id)
        ->and($admin->can('view', $question))->toBeFalse()
        ->and($admin->can('update', $question))->toBeFalse()
        ->and($admin->can('approve', $question))->toBeFalse()
        ->and(Question::query()->duplicateCandidatesIn($this->chapter->id, $admin)->pluck('id'))->not->toContain($question->id)
        ->and(fn () => $question->approve($admin))->toThrow(LogicException::class)
        ->and($question->refresh()->status)->toBe(QuestionStatus::Pending);
});

it('lets the School correct a question students have sat, as a new version that keeps the old one on its exam', function () {
    $question = ($this->writeQuestion)();
    $exam = ($this->onExam)($question, null);
    ExamAttempt::factory()->create(['exam_id' => $exam->id]);

    expect($question->isFrozenByAttempts())->toBeFalse()
        ->and($question->editsMakeNewVersion())->toBeTrue()
        ->and($this->school->can('update', $question))->toBeTrue();

    $revision = $question->createRevisionWith([...$question->only(['chapter_id', 'question_type', 'options', 'marks']), 'question_text' => '<p>Corrected</p>']);

    expect($revision->is_private)->toBeTrue()
        ->and($revision->status)->toBe(QuestionStatus::Pending)
        ->and($question->refresh()->is_latest)->toBeFalse()
        ->and($exam->questions()->pluck('questions.id')->all())->toBe([$question->id]);

    // Not sat yet: corrected where it is, like any pending question.
    expect(($this->writeQuestion)()->editsMakeNewVersion())->toBeFalse();
});

it('opens a question to the students the day after the last exam it is on has ended', function () {
    $this->travelTo(Carbon::parse('2026-10-22 10:00'));
    $question = ($this->writeQuestion)();

    ($this->onExam)($question, Carbon::parse('2026-10-22 08:00'));
    expect(($this->released)())->toBe([]);

    $this->travelTo(Carbon::parse('2026-10-23 00:01'));
    expect(($this->released)())->toBe([$question->id]);

    // Put on another exam still to come, it closes again until that is over.
    ($this->onExam)($question, Carbon::parse('2026-10-30 12:00'));
    expect(($this->released)())->toBe([]);

    $this->travelTo(Carbon::parse('2026-10-31 09:00'));
    expect(($this->released)())->toBe([$question->id]);
});

it('opens a question after the Admin\'s days when an exam it is on has no end time, or it is on none', function () {
    $this->travelTo(Carbon::parse('2026-01-01 10:00'));
    $onOpenExam = ($this->writeQuestion)();
    ($this->onExam)($onOpenExam, null);
    ($this->onExam)($onOpenExam, Carbon::parse('2026-01-02 10:00'));
    $onNoExam = ($this->writeQuestion)();

    $this->travelTo(Carbon::parse('2026-02-15 10:00'));
    expect(($this->released)())->toBe([]);

    $this->travelTo(Carbon::parse('2026-03-02 10:00'));
    expect(($this->released)())->toEqualCanonicalizing([$onOpenExam->id, $onNoExam->id]);

    BillingSetting::current()->update(['school_question_release_days' => 90]);
    expect(($this->released)())->toBe([]);
});

it('opens a corrected question by the exams of every version, from when the first was written', function () {
    $this->travelTo(Carbon::parse('2026-10-01 10:00'));
    $question = ($this->writeQuestion)();
    ($this->onExam)($question, Carbon::parse('2026-10-05 10:00'));
    $revision = $question->createRevisionWith([...$question->only(['chapter_id', 'question_type', 'options', 'marks']), 'question_text' => '<p>Corrected</p>']);

    $this->travelTo(Carbon::parse('2026-10-06 10:00'));

    expect(($this->released)())->toBe([$revision->id]);
});

it('never opens a teacher\'s question or another School\'s through a School\'s link', function () {
    $this->travelTo(Carbon::parse('2026-01-01'));
    $teacherQuestion = Question::factory()->approved()->for($this->chapter)->create();
    $this->actingAs($other = User::factory()->school()->create());
    $otherSchools = Question::factory()->for($this->chapter)->create(['created_by' => $other->id]);

    $this->travelTo(Carbon::parse('2026-06-01'));

    expect(($this->released)())->toBe([])
        ->and(Question::releasedToSchoolStudents($other)->pluck('id')->all())->toBe([$otherSchools->id])
        ->and($teacherQuestion->is_private)->toBeFalse();
});

it('shows the open questions with their answers through a link the school\'s software signs', function () {
    $this->travelTo(Carbon::parse('2026-01-01'));
    $open = ($this->writeQuestion)();
    ($this->onExam)(($this->writeQuestion)(['question_text' => '<p>Still on an exam to come</p>']), Carbon::parse('2026-12-31'));
    $this->travelTo(Carbon::parse('2026-06-01'));
    auth()->logout();

    $this->get(($this->link)(['lang' => 'en']))
        ->assertOk()
        ->assertSee($this->classSubject->label())
        ->assertSee('The school asks this')
        ->assertSee('school right answer')
        ->assertSee('Because the school says so')
        ->assertDontSee('Still on an exam to come')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://school.test:8443");

    expect($open->id)->toBeIn(($this->released)());
});

it('refuses a link that is not signed with the School\'s secret, has expired, or stays open too long', function () {
    $this->get('/school-questions/'.$this->profile->connection_code.'?expires='.now()->addHour()->timestamp.'&signature=wrong')->assertForbidden();
    $this->get(($this->link)([], now()->subMinute()->timestamp))->assertForbidden();
    $this->get(($this->link)([], now()->addSeconds(SchoolConnectionSignature::MAX_LINK_SECONDS + 60)->timestamp))->assertForbidden();
    $this->get('/school-questions/QB-UNKNOWN00?expires=1&signature=x')->assertForbidden();
});

it('refuses the link of a School that is suspended', function () {
    $this->school->update(['status' => 'suspended']);

    $this->get(($this->link)())->assertForbidden();
});

it('tells the students when nothing is open yet', function () {
    ($this->writeQuestion)();
    auth()->logout();

    $this->get(($this->link)(['lang' => 'en']))
        ->assertOk()
        ->assertSee('No questions are open yet.');
});

it('gives the School the "My questions" pages without the review status', function () {
    $question = ($this->writeQuestion)();

    $this->get('/school/questions')->assertOk();
    $this->get("/school/questions/chapters/{$this->chapter->id}/questions")->assertOk();
    $this->get('/school/questions/create')->assertOk();
    $this->get("/school/questions/{$question->id}/edit")->assertOk();
});
