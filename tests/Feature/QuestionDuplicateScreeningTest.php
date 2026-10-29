<?php

use App\Enums\CqPartType;
use App\Enums\DuplicateCheckStatus;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Pages\DuplicateQuestions;
use App\Jobs\ScreenChapterForDuplicates;
use App\Jobs\ScreenQuestionForDuplicates;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionCqPart;
use App\Models\QuestionStimulus;
use App\Models\User;
use App\Services\DuplicateQuestionFinder;
use App\Services\QuestionDuplicateScreening;
use App\Services\QuestionImportText;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Once;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->chapter = Chapter::factory()->create();

    $this->siOptions = ['মিটার', 'কিলোগ্রাম', 'সেকেন্ড', 'কেলভিন'];
    // The same options, one of them spelt another way.
    $this->nearOptions = ['মিটার', 'কিলোগ্রাম', 'সেকেণ্ড', 'কেলভিন'];

    /** An MCQ of the chapter, saved as its owner's — checked on the (sync) queue as it is saved. */
    $this->stored = fn (string $question, array $options, ?User $owner = null, array $attributes = []): Question => Question::factory()->create([
        'chapter_id' => $this->chapter->id,
        'question_text' => QuestionImportText::toHtml($question),
        'options' => collect($options)->map(fn (string $option, int $index): array => [
            'option' => QuestionImportText::toHtml($option), 'image' => null, 'is_correct' => $index === 0,
        ])->all(),
        'created_by' => ($owner ?? $this->teacher)->id,
        ...$attributes,
    ])->refresh();
});

it('holds a new question that looks like one already in the chapter — one its writer cannot even see', function () {
    // Another teacher's question, still pending: invisible to the writer.
    $theirs = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $mine = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);

    expect($theirs->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and($mine->duplicate_status)->toBe(DuplicateCheckStatus::Suspected)
        ->and($mine->duplicateMatches)->toHaveCount(1)
        ->and($mine->duplicateMatches->first()->matched_question_id)->toBe($theirs->id)
        ->and($mine->duplicateMatches->first()->percent)->toBeGreaterThanOrEqual(80);
});

it('clears a question unlike every other one, and remembers how to find it', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $other = ($this->stored)('আলোর বেগ কত?', ['3 লক্ষ কিমি/সে', '2 লক্ষ কিমি/সে', '1 লক্ষ কিমি/সে', '4 লক্ষ কিমি/সে']);

    expect($other->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and($other->duplicate_guard)->toHaveLength(40)
        ->and($other->duplicate_size)->toBeGreaterThan(0)
        ->and($other->duplicateMatches)->toBeEmpty();
});

it('holds the very same question too, for the Admin to see', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $same = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', array_reverse($this->siOptions));

    expect($same->duplicate_status)->toBe(DuplicateCheckStatus::Suspected)
        ->and($same->duplicateMatches->first()->is_exact)->toBeTrue()
        ->and($same->duplicateMatches->first()->percent)->toBe(100);
});

it('compares only with the same chapter\'s current questions, not rejected ones', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, attributes: ['chapter_id' => Chapter::factory()->create()->id]);
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, attributes: ['status' => QuestionStatus::Rejected]);
    $old = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $old->newQuery()->whereKey($old->id)->update(['is_latest' => false]);

    $new = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);

    expect($new->duplicate_status)->toBe(DuplicateCheckStatus::Clear);
});

it('never checks a School\'s own question', function () {
    Queue::fake();
    $school = User::factory()->school()->create();
    $this->actingAs($school);

    $question = Question::factory()->create(['chapter_id' => $this->chapter->id, 'created_by' => $school->id]);

    expect($question->is_private)->toBeTrue()
        ->and($question->duplicate_status)->toBe(DuplicateCheckStatus::Clear);
    Queue::assertNothingPushed();
});

it('checks on the queue, once, whatever the question is saved with', function () {
    Queue::fake();

    $question = Question::factory()->cq()->create(['chapter_id' => $this->chapter->id]);

    foreach (CqPartType::cases() as $type) {
        QuestionCqPart::factory()->for($question)->create(['part_type' => $type, 'part_order' => $type->order()]);
    }

    // The question and each of its four parts asked; the job is unique per question.
    expect($question->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Checking);
    Queue::assertPushed(ScreenQuestionForDuplicates::class, 1);
});

it('keeps a question being checked or held off every exam and out of the pool — its writer\'s own exam too', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher, ['status' => QuestionStatus::Approved]);
    $held = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);

    Queue::fake();
    $checking = ($this->stored)('আলোর বেগ কত?', ['3', '2', '1', '4'], attributes: ['status' => QuestionStatus::Approved]);

    expect($checking->duplicate_status)->toBe(DuplicateCheckStatus::Checking)
        ->and(Question::query()->usableInExamBy($this->teacher)->pluck('id'))->not->toContain($held->id)
        ->and(Question::query()->usableInExamBy($this->teacher)->pluck('id'))->not->toContain($checking->id)
        ->and(Question::query()->approvedPool()->pluck('id'))->not->toContain($checking->id);
});

it('lets the Admin approve a held question only once they have kept it', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $held = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);

    expect($this->admin->can('approve', $held))->toBeFalse()
        ->and(fn () => $held->approve($this->admin))->toThrow(LogicException::class);

    QuestionDuplicateScreening::keep($held, $this->admin);

    expect($held->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and($held->duplicateMatches)->toBeEmpty()
        ->and($this->admin->can('approve', $held))->toBeTrue();

    $held->approve($this->admin);

    expect($held->refresh()->status)->toBe(QuestionStatus::Approved);
});

it('deletes a duplicate for good — and makes the version before it the current one again', function () {
    $original = ($this->stored)('আলোর বেগ কত?', ['তিন লক্ষ', 'দুই লক্ষ', 'এক লক্ষ', 'চার লক্ষ'], attributes: ['status' => QuestionStatus::Approved]);
    ($this->stored)('নিচের কোনটি মৌলিক একক?', $this->siOptions, $this->otherTeacher, ['status' => QuestionStatus::Approved]);

    $revision = $original->createRevisionWith([
        ...$original->only(['chapter_id', 'question_type', 'marks']),
        'question_text' => QuestionImportText::toHtml('নিচের কোনটি মৌলিক একক?'),
        'options' => Question::query()->where('created_by', $this->otherTeacher->id)->first()->options,
    ]);

    expect($revision->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Suspected);

    expect(QuestionDuplicateScreening::discard($revision, $this->admin))->toBeTrue();

    expect(Question::withTrashed()->find($revision->id))->toBeNull()
        ->and($original->refresh()->is_latest)->toBeTrue();
});

it('rejects rather than deletes a duplicate already on an exam, leaving the exam as it was', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $held = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $exam = Exam::factory()->create(['created_by' => $this->teacher->id]);
    $exam->questions()->attach($held->id);

    expect(QuestionDuplicateScreening::discard($held, $this->admin))->toBeFalse();

    expect($held->refresh()->status)->toBe(QuestionStatus::Rejected)
        ->and($held->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and($exam->questions()->pluck('questions.id'))->toContain($held->id);
});

it('checks a question again when what it asks or answers changes', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $question = ($this->stored)('আলোর বেগ কত?', ['3', '2', '1', '4']);

    expect($question->duplicate_status)->toBe(DuplicateCheckStatus::Clear);

    $question->update([
        'question_text' => QuestionImportText::toHtml('নিচের কোনটি মৌলিক রাশি?'),
        'options' => Question::query()->where('created_by', $this->otherTeacher->id)->first()->options,
    ]);

    expect($question->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Suspected);
});

it('checks a stimulus group\'s questions again when the stimulus changes, without holding them against each other', function () {
    $stimulus = QuestionStimulus::factory()->create(['chapter_id' => $this->chapter->id, 'text' => '<p>একটি বস্তু স্থির অবস্থা থেকে চলতে শুরু করল।</p>']);
    $first = ($this->stored)('বস্তুটির আদিবেগ কত?', ['শূন্য', 'এক', 'দুই', 'তিন'], attributes: ['stimulus_id' => $stimulus->id, 'stimulus_order' => 1]);
    $second = ($this->stored)('বস্তুটির আদিবেগ কোনটি?', ['শূন্য', 'এক', 'দুই', 'তিন'], attributes: ['stimulus_id' => $stimulus->id, 'stimulus_order' => 2]);

    expect($first->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and($second->duplicate_status)->toBe(DuplicateCheckStatus::Clear);

    Queue::fake();
    $stimulus->update(['text' => '<p>একটি গাড়ি স্থির অবস্থা থেকে চলতে শুরু করল।</p>']);

    Queue::assertPushed(ScreenQuestionForDuplicates::class, 2);
});

it('finds duplicates among the questions already there, holding the later of two alike', function () {
    // Written before the check existed: both clear, neither fingerprinted.
    Queue::fake();
    $earlier = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, attributes: ['status' => QuestionStatus::Approved]);
    $later = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher, ['status' => QuestionStatus::Approved]);
    Question::query()->update(['duplicate_status' => DuplicateCheckStatus::Clear, 'duplicate_guard' => null, 'duplicate_size' => null]);

    QuestionDuplicateScreening::make()->screenChapter($this->chapter->id);

    expect($earlier->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and($earlier->duplicate_guard)->not->toBeNull()
        ->and($later->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Suspected)
        ->and(Question::query()->approvedPool()->pluck('id')->all())->toBe([$earlier->id]);
});

it('never narrows away a question alike enough to be a duplicate', function () {
    $finder = new DuplicateQuestionFinder(80);

    foreach ([1, 7, 30, 119, 120, 121, 400] as $size) {
        [$smallest, $largest] = $finder->sizeRange($size);

        foreach (range(1, 600) as $other) {
            $reachable = 200 * min($size, $other) / ($size + $other) >= 80;

            expect($other >= $smallest && $other <= $largest)->toBe($reachable, "size {$size} against {$other}");
        }
    }
});

it('shows the Admin the held questions beside what they look like, to keep or delete', function () {
    $theirs = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $kept = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $deleted = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', array_reverse($this->siOptions));
    $this->actingAs($this->admin);

    livewire(DuplicateQuestions::class)
        ->assertCanSeeTableRecords([$kept, $deleted])
        ->assertCanNotSeeTableRecords([$theirs])
        ->mountAction(TestAction::make('compare')->table($kept))
        ->assertMountedActionModalSeeHtml('qb-question-duplicate');

    // The menu's count is redrawn at once after each.
    livewire(DuplicateQuestions::class)->callAction(TestAction::make('keep')->table($kept))->assertDispatched('refresh-sidebar');
    livewire(DuplicateQuestions::class)->callAction(TestAction::make('discard')->table($deleted))->assertDispatched('refresh-sidebar');

    expect($kept->refresh()->duplicate_status)->toBe(DuplicateCheckStatus::Clear)
        ->and(Question::withTrashed()->find($deleted->id))->toBeNull();

    livewire(DuplicateQuestions::class)->assertCountTableRecords(0);
});

it('looks through every chapter on the queue when the Admin asks', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $this->actingAs($this->admin);
    Queue::fake();

    livewire(DuplicateQuestions::class)->callAction(TestAction::make('lookThroughExisting')->table());

    Queue::assertPushed(ScreenChapterForDuplicates::class, fn ($job): bool => $job->chapterId === $this->chapter->id);
});

it('keeps a CQ\'s sub-questions in the comparison', function () {
    $cq = fn (array $parts): Question => tap(Question::factory()->cq()->create(['chapter_id' => $this->chapter->id, 'question_text' => '<p>একটি বস্তুর ভর ৫ কেজি।</p>']), function (Question $question) use ($parts): void {
        foreach (CqPartType::cases() as $index => $type) {
            QuestionCqPart::factory()->for($question)->create(['part_type' => $type, 'part_order' => $type->order(), 'part_text' => "<p>{$parts[$index]}</p>"]);
        }
    })->refresh();

    $one = $cq(['ভর কাকে বলে?', 'জড়তা ব্যাখ্যা করো।', 'ত্বরণ নির্ণয় করো।', 'বল তুলনা করো।']);
    $two = $cq(['বেগ কাকে বলে?', 'সরণ ব্যাখ্যা করো।', 'দূরত্ব নির্ণয় করো।', 'কাজ বিশ্লেষণ করো।']);

    expect($one->question_type)->toBe(QuestionType::Cq)
        ->and($two->duplicate_status)->toBe(DuplicateCheckStatus::Clear);
});

it('shows each held question as a card by default — whole, beside what it looks like — with the table a click away', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $held = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $this->actingAs($this->admin);

    livewire(DuplicateQuestions::class)
        ->assertCanSeeTableRecords([$held])
        ->assertSeeHtml('qb-question-duplicate')
        ->assertSee('সেকেণ্ড')
        ->assertActionVisible('switchDisplay')
        ->assertTableActionVisible('keep', $held);

    Livewire::withQueryParams(['view' => 'table']);

    livewire(DuplicateQuestions::class)
        ->assertCanSeeTableRecords([$held])
        ->assertTableColumnExists('alike')
        ->assertDontSeeHtml('qb-question-duplicate');
});

it('always shows the menu count — grey 0 when nothing waits, red when something does', function () {
    expect(DuplicateQuestions::getNavigationBadge())->toBe('0')
        ->and(DuplicateQuestions::getNavigationBadgeColor())->toBe('gray');

    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    Once::flush();

    expect(DuplicateQuestions::getNavigationBadge())->toBe('1')
        ->and(DuplicateQuestions::getNavigationBadgeColor())->toBe('danger');
});

it('redraws the menu count at once after deleting many', function () {
    ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, $this->otherTeacher);
    $held = ($this->stored)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $this->actingAs($this->admin);

    livewire(DuplicateQuestions::class)
        ->selectTableRecords([$held->id])
        ->callAction(TestAction::make('discardSelected')->table()->bulk())
        ->assertDispatched('refresh-sidebar');

    expect(Question::withTrashed()->find($held->id))->toBeNull();
});
