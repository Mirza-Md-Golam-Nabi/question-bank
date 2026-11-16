<?php

use App\Enums\CqPartType;
use App\Enums\Difficulty;
use App\Enums\DuplicateCheckStatus;
use App\Enums\QuestionStatus;
use App\Enums\QuestionSyncRunStatus;
use App\Filament\Pages\FetchQuestionsFromLive;
use App\Filament\Pages\QuestionSharing;
use App\Models\BillingSetting;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\QuestionSyncRun;
use App\Models\StaffEarning;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionSyncImporter;
use App\Services\QuestionSyncSource;
use App\Support\QuestionSync;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Livewire\livewire;

/*
 * Both servers are this one application here: a request the test server
 * sends is answered by the same app acting as the live server, so the two
 * share one database — the live chapter and the test chapter are simply
 * two chapters of it.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');

    config([
        'question-bank.question_sync.role' => QuestionSync::CONSUMER,
        'question-bank.question_sync.secret' => 'shared-secret',
        'question-bank.question_sync.source_url' => 'https://live.test',
    ]);

    BillingSetting::current()->fill(['question_sync_open_until' => now()->addHour()])->save();

    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->liveChapter = Chapter::factory()->create();
    $this->testChapter = Chapter::factory()->create();

    Http::fake(function (ClientRequest $request) {
        config(['question-bank.question_sync.role' => QuestionSync::SOURCE]);

        $response = test()
            ->withHeaders(collect($request->headers())->map(fn (array $values): string => $values[0])->all())
            ->get(Str::after($request->url(), 'https://live.test'));

        config(['question-bank.question_sync.role' => QuestionSync::CONSUMER]);

        return Http::response(
            $response->baseResponse instanceof StreamedResponse ? $response->streamedContent() : $response->getContent(),
            $response->getStatusCode(),
        );
    });

    /** A question of the live chapter in its approved pool. */
    $this->liveQuestion = function (array $attributes = [], ?Closure $factory = null): Question {
        $question = ($factory ?? fn ($factory) => $factory)(Question::factory()->approved())->create([
            'chapter_id' => $this->liveChapter->id,
            ...$attributes,
        ]);
        $question->newQuery()->whereKey($question->id)->update(['duplicate_status' => DuplicateCheckStatus::Clear]);

        return $question->fresh();
    };

    /** A new version of a question with another text, as editing it would make. */
    $this->revise = fn (Question $question, string $text): Question => $question->createRevisionWith([
        ...$question->only(['chapter_id', 'topic_id', 'question_type', 'editor_mode', 'question_image', 'options', 'explanation', 'marks']),
        'question_text' => $text,
    ]);

    $this->fetchChapter = fn (): QuestionSyncRun => QuestionSyncImporter::startChapter($this->superAdmin, $this->liveChapter->id, 'Live chapter', $this->testChapter)->fresh();

    /** The current version of what came from this live question. */
    $this->fetched = fn (Question $live): ?Question => Question::query()
        ->where('is_latest', true)
        ->where(fn ($query) => $query
            ->where('remote_origin_id', $live->parent_id ?? $live->id)
            ->orWhereIn('parent_id', Question::query()->where('remote_origin_id', $live->parent_id ?? $live->id)->select('id')))
        ->first();
});

it('brings a chapter\'s approved questions with answers, explanations and topics, approved and unchecked', function () {
    Topic::factory()->create(['chapter_id' => $this->testChapter->id, 'name' => 'Newton  Laws']);
    $mcq = ($this->liveQuestion)([
        'topic_id' => Topic::factory()->create(['chapter_id' => $this->liveChapter->id, 'name' => 'newton laws'])->id,
        'explanation' => '<p>Because of inertia.</p>',
        'difficulty' => Difficulty::Hard,
    ]);
    $withNewTopic = ($this->liveQuestion)(['topic_id' => Topic::factory()->create(['chapter_id' => $this->liveChapter->id, 'name' => 'Friction'])->id]);
    $cq = ($this->liveQuestion)([], fn ($factory) => $factory->cq());
    foreach (CqPartType::ordered() as $order => $type) {
        $cq->cqParts()->create(['part_type' => $type, 'part_order' => $order + 1, 'part_text' => "<p>{$type->value}</p>", 'marks' => $order + 1]);
    }
    $pending = Question::factory()->create(['chapter_id' => $this->liveChapter->id, 'status' => QuestionStatus::Pending]);
    $staffOwned = ($this->liveQuestion)(['created_by' => User::factory()->staff()->create()->id]);

    $run = ($this->fetchChapter)();

    expect($run->status)->toBe(QuestionSyncRunStatus::Done)
        ->and($run->countOf('added'))->toBe(4)
        ->and($run->countOf('new_topics'))->toBe(1);

    $copy = ($this->fetched)($mcq);
    expect($copy->chapter_id)->toBe($this->testChapter->id)
        ->and($copy->status)->toBe(QuestionStatus::Approved)
        ->and($copy->created_by)->toBe($this->superAdmin->id)
        ->and($copy->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and($copy->options)->toBe($mcq->options)
        ->and($copy->explanation)->toBe('<p>Because of inertia.</p>')
        ->and($copy->difficulty)->toBe(Difficulty::Hard)
        ->and($copy->topic->name)->toBe('Newton  Laws');

    expect(($this->fetched)($withNewTopic)->topic->name)->toBe('Friction')
        ->and(($this->fetched)($cq)->cqParts)->toHaveCount(4)
        ->and((float) ($this->fetched)($cq)->marks)->toBe(10.0)
        ->and(($this->fetched)($pending))->toBeNull()
        ->and(StaffEarning::query()->where('question_id', ($this->fetched)($staffOwned)->id)->exists())->toBeFalse();
});

it('leaves a question without a topic without one', function () {
    $live = ($this->liveQuestion)(['topic_id' => null]);

    ($this->fetchChapter)();

    expect(($this->fetched)($live)->topic_id)->toBeNull();
});

it('leaves an unchanged question as it is but brings its difficulty, and makes a new version of a changed one', function () {
    $same = ($this->liveQuestion)(['difficulty' => Difficulty::Easy]);
    $changed = ($this->liveQuestion)();
    ($this->fetchChapter)();
    $firstCopy = ($this->fetched)($changed);

    $same->newQuery()->whereKey($same->id)->update(['difficulty' => Difficulty::Medium]);
    $revision = ($this->revise)($changed, '<p>Corrected on live.</p>');
    $revision->approve(User::factory()->admin()->create());
    $revision->newQuery()->whereKey($revision->id)->update(['duplicate_status' => DuplicateCheckStatus::Clear]);

    $run = ($this->fetchChapter)();

    expect($run->countOf('unchanged'))->toBe(1)
        ->and($run->countOf('updated'))->toBe(1)
        ->and(($this->fetched)($same)->difficulty)->toBe(Difficulty::Medium);

    $newCopy = ($this->fetched)($revision);
    expect($newCopy->id)->not->toBe($firstCopy->id)
        ->and($newCopy->parent_id)->toBe($firstCopy->id)
        ->and($newCopy->question_text)->toBe('<p>Corrected on live.</p>')
        ->and($newCopy->status)->toBe(QuestionStatus::Approved)
        ->and($firstCopy->fresh()->is_latest)->toBeFalse();
});

it('keeps a change made on the test server until the live question has a new version', function () {
    $live = ($this->liveQuestion)();
    ($this->fetchChapter)();
    ($this->revise)(($this->fetched)($live), '<p>Edited on test.</p>');

    ($this->fetchChapter)();

    expect(($this->fetched)($live)->question_text)->toBe('<p>Edited on test.</p>');
});

it('does not bring back a question deleted on the test server', function () {
    $live = ($this->liveQuestion)();
    ($this->fetchChapter)();
    ($this->fetched)($live)->delete();

    $run = ($this->fetchChapter)();

    expect($run->countOf('skipped'))->toBe(1)
        ->and(Question::query()->where('chapter_id', $this->testChapter->id)->count())->toBe(0);
});

it('brings a stimulus with its questions as one group', function () {
    $stimulus = QuestionStimulus::factory()->create(['chapter_id' => $this->liveChapter->id]);
    $first = ($this->liveQuestion)(['stimulus_id' => $stimulus->id, 'stimulus_order' => 1]);
    $second = ($this->liveQuestion)(['stimulus_id' => $stimulus->id, 'stimulus_order' => 2]);

    ($this->fetchChapter)();

    $copies = [($this->fetched)($first), ($this->fetched)($second)];
    expect($copies[0]->stimulus_id)->toBe($copies[1]->stimulus_id)
        ->and($copies[0]->stimulus->text)->toBe($stimulus->text)
        ->and($copies[0]->stimulus->remote_id)->toBe($stimulus->id)
        ->and($copies[1]->stimulus_order)->toBe(2);
});

it('gives a group a new version, with a new stimulus, when it has one on live', function () {
    $stimulus = QuestionStimulus::factory()->create(['chapter_id' => $this->liveChapter->id]);
    $first = ($this->liveQuestion)(['stimulus_id' => $stimulus->id, 'stimulus_order' => 1]);
    $second = ($this->liveQuestion)(['stimulus_id' => $stimulus->id, 'stimulus_order' => 2]);
    ($this->fetchChapter)();
    $oldCopies = [($this->fetched)($first), ($this->fetched)($second)];

    // The live group edited: a new stimulus and a new version of each.
    $revised = QuestionStimulus::factory()->create(['chapter_id' => $this->liveChapter->id, 'text' => '<p>A corrected stimulus.</p>']);
    foreach ([$first, $second] as $question) {
        $revision = $question->createRevisionWith([
            ...$question->only(['chapter_id', 'question_type', 'question_text', 'editor_mode', 'options', 'marks', 'stimulus_order']),
            'stimulus_id' => $revised->id,
        ]);
        $revision->newQuery()->whereKey($revision->id)->update(['status' => QuestionStatus::Approved, 'duplicate_status' => DuplicateCheckStatus::Clear]);
    }

    $run = ($this->fetchChapter)();

    $newCopies = [($this->fetched)($first), ($this->fetched)($second)];
    expect($run->countOf('updated'))->toBe(2)
        ->and($newCopies[0]->stimulus_id)->toBe($newCopies[1]->stimulus_id)
        ->and($newCopies[0]->stimulus_id)->not->toBe($oldCopies[0]->stimulus_id)
        ->and($newCopies[0]->stimulus->text)->toBe('<p>A corrected stimulus.</p>')
        ->and($newCopies[0]->status)->toBe(QuestionStatus::Approved)
        ->and($newCopies[1]->status)->toBe(QuestionStatus::Approved)
        ->and($oldCopies[0]->fresh()->is_latest)->toBeFalse();
});

it('brings the images the questions have', function () {
    Storage::disk('public')->put('questions/figure.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    ($this->liveQuestion)(['question_image' => 'questions/figure.png']);
    ($this->liveQuestion)(['question_image' => 'questions/missing.png']);

    $run = ($this->fetchChapter)();

    // One database and one disk here: the image is there already.
    expect($run->countOf('missing_images'))->toBe(1);
});

it('fetches a past paper whole, approved, and replaces its questions when fetched again', function () {
    $live = BoardQuestionPaper::factory()->approved()->withMcqs(2)->create();
    $cq = $live->cqQuestions()->create(['question_text' => '<p>Stimulus</p>', 'marks' => 0, 'order_index' => 1]);
    foreach (CqPartType::ordered() as $order => $type) {
        $cq->parts()->create(['part_type' => $type, 'part_order' => $order + 1, 'part_text' => '<p>Part</p>', 'answer' => '<p>Answer</p>', 'marks' => 2]);
    }
    $testClassSubject = ClassSubject::factory()->create();

    $run = QuestionSyncImporter::startPaper($this->superAdmin, $live->id, 'Live paper', $testClassSubject->id)->fresh();

    $copy = $run->boardQuestionPaper;
    expect($run->status)->toBe(QuestionSyncRunStatus::Done)
        ->and($run->countOf('added'))->toBe(3)
        ->and($copy->status)->toBe(QuestionStatus::Approved)
        ->and($copy->board_id)->toBe($live->board_id)
        ->and($copy->class_subject_id)->toBe($testClassSubject->id)
        ->and($copy->mcqQuestions)->toHaveCount(2)
        ->and($copy->cqQuestions->first()->parts->first()->answer)->toBe('<p>Answer</p>')
        ->and((float) $copy->cqQuestions->first()->marks)->toBe(8.0);

    $live->mcqQuestions()->first()->delete();
    $again = QuestionSyncImporter::startPaper($this->superAdmin, $live->id, 'Live paper', $testClassSubject->id)->fresh();

    expect($again->countOf('updated'))->toBe(2)
        ->and($copy->fresh()->mcqQuestions)->toHaveCount(1)
        ->and(BoardQuestionPaper::query()->where('remote_id', $live->id)->count())->toBe(1);
});

it('leaves a fetched paper someone has sat, and a paper made on the test server, as they are', function () {
    $live = BoardQuestionPaper::factory()->approved()->withMcqs(2)->create();
    $testClassSubject = ClassSubject::factory()->create();
    $run = QuestionSyncImporter::startPaper($this->superAdmin, $live->id, 'Live paper', $testClassSubject->id)->fresh();
    $exam = Exam::factory()->create(['board_question_paper_id' => $run->board_question_paper_id]);
    ExamAttempt::factory()->create(['exam_id' => $exam->id]);

    $sat = QuestionSyncImporter::startPaper($this->superAdmin, $live->id, 'Live paper', $testClassSubject->id)->fresh();

    expect($sat->countOf('skipped'))->toBe(2)->and($sat->message)->not->toBeNull();

    $other = BoardQuestionPaper::factory()->approved()->withMcqs(1)->create();
    $madeHere = ClassSubject::factory()->create();
    BoardQuestionPaper::factory()->create(['board_id' => $other->board_id, 'year' => $other->year, 'class_subject_id' => $madeHere->id]);

    $taken = QuestionSyncImporter::startPaper($this->superAdmin, $other->id, 'Other paper', $madeHere->id)->fresh();

    expect($taken->countOf('skipped'))->toBe(1)
        ->and(BoardQuestionPaper::query()->where('remote_id', $other->id)->exists())->toBeFalse();
});

it('gives nothing while the sharing is closed', function () {
    ($this->liveQuestion)();
    BillingSetting::current()->fill(['question_sync_open_until' => now()->subMinute()])->save();

    $run = ($this->fetchChapter)();

    expect($run->status)->toBe(QuestionSyncRunStatus::Failed)
        ->and($run->message)->toContain('closed')
        ->and(Question::query()->where('chapter_id', $this->testChapter->id)->count())->toBe(0);
});

it('refuses a request signed with another secret, and has no API anywhere but the live server', function () {
    config(['question-bank.question_sync.role' => QuestionSync::SOURCE]);

    $this->withHeaders(['X-Timestamp' => (string) now()->timestamp, 'X-Signature' => 'wrong'])
        ->getJson('/api/question-sync/classes')
        ->assertUnauthorized();

    $this->withHeaders(QuestionSync::headers('GET', 'classes', []))
        ->getJson('/api/question-sync/classes')
        ->assertOk();

    // A signature for one list is not one for another.
    $this->withHeaders(QuestionSync::headers('GET', 'classes', []))
        ->getJson("/api/question-sync/chapters/{$this->liveChapter->id}/questions?kind=single")
        ->assertUnauthorized();

    config(['question-bank.question_sync.role' => QuestionSync::CONSUMER]);

    $this->withHeaders(QuestionSync::headers('GET', 'classes', []))
        ->getJson('/api/question-sync/classes')
        ->assertNotFound();
});

it('hands over only image files of the public disk', function () {
    config(['question-bank.question_sync.role' => QuestionSync::SOURCE]);
    Storage::disk('public')->put('questions/a.png', 'png');
    Storage::disk('public')->put('secret.txt', 'no');

    foreach (['../.env' => 404, 'secret.txt' => 404, 'questions/a.png' => 200] as $path => $status) {
        $this->withHeaders(QuestionSync::headers('GET', 'image', ['path' => $path]))
            ->get('/api/question-sync/image?'.http_build_query(['path' => $path]))
            ->assertStatus($status);
    }
});

it('lets only the Super Admin open the sharing, and only on the live server', function () {
    config(['question-bank.question_sync.role' => QuestionSync::SOURCE]);

    expect(fn () => QuestionSyncSource::open(User::factory()->admin()->create(), 2))->toThrow(ValidationException::class);

    QuestionSyncSource::open($this->superAdmin, 3);
    expect(QuestionSyncSource::openUntil()->diffInMinutes(now(), absolute: true))->toBeGreaterThan(170);

    QuestionSyncSource::close($this->superAdmin);
    expect(QuestionSyncSource::isOpen())->toBeFalse();

    config(['question-bank.question_sync.role' => QuestionSync::CONSUMER]);
    expect(fn () => QuestionSyncSource::open($this->superAdmin, 2))->toThrow(ValidationException::class);
});

it('lets only the Super Admin fetch, and only on the test server', function () {
    expect(fn () => QuestionSyncImporter::startChapter(User::factory()->admin()->create(), $this->liveChapter->id, 'x', $this->testChapter))
        ->toThrow(ValidationException::class);

    config(['question-bank.question_sync.role' => QuestionSync::SOURCE]);
    expect(fn () => QuestionSyncImporter::startChapter($this->superAdmin, $this->liveChapter->id, 'x', $this->testChapter))
        ->toThrow(ValidationException::class);
});

it('shows each server only its own page, and only to the Super Admin', function () {
    Filament::setCurrentPanel('admin');

    $this->actingAs($this->superAdmin);
    expect(FetchQuestionsFromLive::canAccess())->toBeTrue()
        ->and(QuestionSharing::canAccess())->toBeFalse();

    config(['question-bank.question_sync.role' => QuestionSync::SOURCE]);
    expect(FetchQuestionsFromLive::canAccess())->toBeFalse()
        ->and(QuestionSharing::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->admin()->create());
    expect(QuestionSharing::canAccess())->toBeFalse();
});

it('fetches a chapter from the page, choosing on both sides', function () {
    $live = ($this->liveQuestion)();
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->superAdmin);
    $liveClassSubject = $this->liveChapter->classSubject;
    $testClassSubject = $this->testChapter->classSubject;

    livewire(FetchQuestionsFromLive::class)
        ->assertSet('connectionProblem', null)
        ->fillForm([
            'academic_class_id' => $testClassSubject->academic_class_id,
            'class_subject_id' => $testClassSubject->id,
            'chapter_id' => $this->testChapter->id,
            'remote_class_id' => $liveClassSubject->academic_class_id,
            'remote_class_subject_id' => $liveClassSubject->id,
            'remote_chapter_id' => $this->liveChapter->id,
        ], 'chapterForm')
        ->call('fetchChapter')
        ->assertHasNoFormErrors(form: 'chapterForm')
        ->assertNotified();

    expect(($this->fetched)($live))->not->toBeNull()
        ->and(QuestionSyncRun::query()->first()->remote_label)->toContain($this->liveChapter->name);
});
