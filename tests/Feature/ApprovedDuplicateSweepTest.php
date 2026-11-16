<?php

use App\Enums\DuplicateCheckStatus;
use App\Enums\QuestionStatus;
use App\Filament\Pages\ApprovedDuplicates;
use App\Jobs\ScreenApprovedChapterForDuplicates;
use App\Models\ApprovedDuplicateSet;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\StaffEarning;
use App\Models\User;
use App\Services\ApprovedDuplicateSweep;
use App\Services\QuestionImportText;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->chapter = Chapter::factory()->create();

    $this->siOptions = ['মিটার', 'কিলোগ্রাম', 'সেকেন্ড', 'কেলভিন'];
    $this->nearOptions = ['মিটার', 'কিলোগ্রাম', 'সেকেণ্ড', 'কেলভিন'];

    // Approved questions already in the bank, as the check of new questions left them.
    Queue::fake();

    /** An approved MCQ of the chapter, clear of the check of new questions. */
    $this->approved = function (string $question, array $options, array $attributes = []): Question {
        $stored = Question::factory()->create([
            'chapter_id' => $this->chapter->id,
            'question_text' => QuestionImportText::toHtml($question),
            'options' => collect($options)->map(fn (string $option, int $index): array => [
                'option' => QuestionImportText::toHtml($option), 'image' => null, 'is_correct' => $index === 0,
            ])->all(),
            'explanation' => '<p>SI এককের মৌলিক রাশি।</p>',
            'created_by' => $this->teacher->id,
            'status' => QuestionStatus::Approved,
            ...$attributes,
        ]);
        Question::query()->whereKey($stored->id)->update(['duplicate_status' => DuplicateCheckStatus::Clear]);

        return $stored->refresh();
    };

    $this->sweep = fn () => ApprovedDuplicateSweep::make()->sweepChapter($this->chapter->id);
});

it('gathers alike approved questions into one set, through a question both are like', function () {
    $one = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $two = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $three = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', array_reverse($this->siOptions));
    ($this->approved)('আলোর বেগ কত?', ['3 লক্ষ কিমি/সে', '2 লক্ষ কিমি/সে', '1 লক্ষ কিমি/সে', '4 লক্ষ কিমি/সে']);

    ($this->sweep)();

    $set = ApprovedDuplicateSet::query()->awaitingAdmin()->sole();

    expect($set->members->pluck('question_id')->sort()->values()->all())->toBe([$one->id, $two->id, $three->id])
        ->and($set->members->firstWhere('question_id', $three->id)->is_exact)->toBeTrue()
        // They stay in the pool meanwhile.
        ->and(Question::query()->approvedPool()->count())->toBe(4);
});

it('finds nothing among questions unlike each other, and replaces the sets still waiting when run again', function () {
    $one = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    ($this->sweep)();
    ($this->sweep)();

    expect(ApprovedDuplicateSet::query()->count())->toBe(1);

    $one->forceFill(['status' => QuestionStatus::Rejected])->save();
    ($this->sweep)();

    expect(ApprovedDuplicateSet::query()->count())->toBe(0);
});

it('leaves out pending questions and a School\'s own', function () {
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, ['status' => QuestionStatus::Pending]);
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions, ['is_private' => true]);

    ($this->sweep)();

    expect(ApprovedDuplicateSet::query()->count())->toBe(0);
});

it('keeps the ticked questions, never looks through them again, and deletes the rest', function () {
    $kept = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $deleted = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    ($this->sweep)();

    $counts = ApprovedDuplicateSweep::resolve(ApprovedDuplicateSet::query()->sole(), [$kept->id], $this->admin);

    expect($counts)->toBe(['kept' => 1, 'deleted' => 1, 'rejected' => 0])
        ->and($kept->refresh()->duplicate_verified_at)->not->toBeNull()
        ->and(Question::withTrashed()->find($deleted->id))->toBeNull()
        ->and(ApprovedDuplicateSet::query()->awaitingAdmin()->count())->toBe(0);

    // A new approved one alike is still set beside the kept one.
    $later = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', array_reverse($this->siOptions));
    ($this->sweep)();

    expect(ApprovedDuplicateSet::query()->awaitingAdmin()->sole()->members->pluck('question_id')->sort()->values()->all())
        ->toBe([$kept->id, $later->id]);
});

it('never sets two questions the Admin kept against each other', function () {
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    ($this->sweep)();
    $set = ApprovedDuplicateSet::query()->sole();

    ApprovedDuplicateSweep::resolve($set, $set->members->pluck('question_id')->all(), $this->admin);
    ($this->sweep)();

    expect(ApprovedDuplicateSet::query()->awaitingAdmin()->count())->toBe(0);
});

it('rejects rather than deletes a question an exam or a Staff pay has, and only that one of its stimulus group', function () {
    $kept = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $onExam = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $paid = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', array_reverse($this->siOptions));
    // A stimulus that adds nothing to what is asked, so the paid one is still alike.
    $stimulus = QuestionStimulus::factory()->create(['chapter_id' => $this->chapter->id, 'text' => '<p></p>']);
    $groupmate = ($this->approved)('আলোর বেগ কত?', ['3 লক্ষ কিমি/সে', '2 লক্ষ কিমি/সে', '1 লক্ষ কিমি/সে', '4 লক্ষ কিমি/সে'], ['stimulus_id' => $stimulus->id, 'stimulus_order' => 1]);
    Question::query()->whereKey($paid->id)->update(['stimulus_id' => $stimulus->id, 'stimulus_order' => 2]);
    Exam::factory()->create(['created_by' => $this->teacher->id])->questions()->attach($onExam->id);
    StaffEarning::factory()->create(['question_id' => $paid->id]);
    ($this->sweep)();

    $counts = ApprovedDuplicateSweep::resolve(ApprovedDuplicateSet::query()->sole(), [$kept->id], $this->admin);

    expect($counts)->toBe(['kept' => 1, 'deleted' => 0, 'rejected' => 2])
        ->and($onExam->refresh()->status)->toBe(QuestionStatus::Rejected)
        ->and($paid->refresh()->status)->toBe(QuestionStatus::Rejected)
        ->and(StaffEarning::query()->where('question_id', $paid->id)->exists())->toBeTrue()
        ->and($groupmate->refresh()->status)->toBe(QuestionStatus::Approved);
});

it('deletes every version of a duplicate none of whose versions an exam has', function () {
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $first = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    $second = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions, ['parent_id' => $first->id, 'version' => 2]);
    Question::query()->whereKey($first->id)->update(['is_latest' => false]);
    ($this->sweep)();
    $set = ApprovedDuplicateSet::query()->sole();
    $keep = $set->members->pluck('question_id')->reject(fn (int $id): bool => $id === $second->id)->all();

    ApprovedDuplicateSweep::resolve($set, $keep, $this->admin);

    expect(Question::withTrashed()->whereKey([$first->id, $second->id])->count())->toBe(0);
});

it('refuses a decision keeping none, and a set decided already', function () {
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    ($this->sweep)();
    $set = ApprovedDuplicateSet::query()->sole();

    expect(fn () => ApprovedDuplicateSweep::resolve($set, [], $this->admin))->toThrow(ValidationException::class);

    ApprovedDuplicateSweep::resolve($set, $set->members->pluck('question_id')->all(), $this->admin);

    expect(fn () => ApprovedDuplicateSweep::resolve($set->refresh(), [$set->members->first()->question_id], $this->admin))
        ->toThrow(ValidationException::class);
});

it('shows the Admin each set whole with its explanations, and decides it there', function () {
    $kept = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    Exam::factory()->create(['created_by' => $this->teacher->id])->questions()->attach($kept->id);
    $deleted = ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->nearOptions);
    ($this->sweep)();
    $set = ApprovedDuplicateSet::query()->sole();
    $this->actingAs($this->admin);

    livewire(ApprovedDuplicates::class)
        ->assertCanSeeTableRecords([$set])
        ->assertSee('SI এককের মৌলিক রাশি।')
        // The one on an exam is ticked to begin with.
        ->mountAction(TestAction::make('decide')->table($set))
        ->assertSchemaStateSet(['kept' => [$kept->id]])
        ->callMountedAction()
        ->assertNotified()
        ->assertDispatched('refresh-sidebar');

    expect(Question::withTrashed()->find($deleted->id))->toBeNull()
        ->and(Question::query()->find($kept->id)->duplicate_verified_at)->not->toBeNull();

    livewire(ApprovedDuplicates::class)->assertCountTableRecords(0);
});

it('looks through the chapters on the queue when the Admin asks, as one batch', function () {
    ($this->approved)('নিচের কোনটি মৌলিক রাশি?', $this->siOptions);
    $this->actingAs($this->admin);
    Bus::fake();

    livewire(ApprovedDuplicates::class)->callAction('sweep', ['academic_class_id' => null]);

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1
        && $batch->jobs->first() instanceof ScreenApprovedChapterForDuplicates
        && $batch->jobs->first()->chapterId === $this->chapter->id);
});
