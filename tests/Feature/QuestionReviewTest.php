<?php

use App\Enums\QuestionStatus;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Filament\Support\QuestionReviewActions;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\User;
use App\Services\QuestionImportText;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->chapter = Chapter::factory()->create();

    /**
     * A teacher's question in the chapter, pending unless told otherwise —
     * made as the teacher, since an Admin's own question is approved at once.
     */
    $this->question = function (string $text, array $attributes = []): Question {
        $viewer = auth()->user();
        $this->actingAs($this->teacher);

        $question = Question::factory()->for($this->chapter)->create([
            'created_by' => $this->teacher->id,
            'question_text' => QuestionImportText::toHtml($text),
            'options' => [
                ['option' => "{$text} — one", 'image' => null, 'is_correct' => true],
                ['option' => "{$text} — two", 'image' => null, 'is_correct' => false],
            ],
            ...$attributes,
        ]);

        $this->actingAs($viewer);

        return $question;
    };

    $this->actingAs($this->admin);
    $this->list = fn () => livewire(ListQuestions::class, ['chapter' => $this->chapter->id]);
});

describe('approving many at once', function () {
    it('approves every selected question', function () {
        $questions = collect(['Alpha?', 'Beta?', 'Gamma?'])->map(fn (string $text) => ($this->question)($text));

        ($this->list)()
            ->selectTableRecords($questions->pluck('id')->all())
            ->callAction(TestAction::make('approveSelected')->table()->bulk())
            ->assertNotified(__(':count approved', ['count' => 3]));

        expect($questions->every(fn (Question $question) => $question->refresh()->status === QuestionStatus::Approved))->toBeTrue()
            ->and($questions->first()->approvalLogs()->count())->toBe(1);
    });

    it('leaves out a possible duplicate, to be approved on its own', function () {
        ($this->question)('Which is a base unit?', ['status' => QuestionStatus::Approved]);
        $lookalike = ($this->question)('Which is a base unit?');
        $other = ($this->question)('Something else entirely?');

        $result = QuestionReviewActions::approveMany(Question::query()->whereKey([$lookalike->id, $other->id])->get(), $this->admin);

        expect($result)->toBe(['approved' => 1, 'duplicates' => 1])
            ->and($lookalike->refresh()->status)->toBe(QuestionStatus::Pending)
            ->and($other->refresh()->status)->toBe(QuestionStatus::Approved);
    });

    it('approves a stimulus group once, as one', function () {
        $stimulus = QuestionStimulus::factory()->create(['chapter_id' => $this->chapter->id]);
        $first = ($this->question)('Group first?', ['stimulus_id' => $stimulus->id, 'stimulus_order' => 1]);
        $second = ($this->question)('Group second?', ['stimulus_id' => $stimulus->id, 'stimulus_order' => 2]);

        $result = QuestionReviewActions::approveMany(Question::query()->whereKey([$first->id, $second->id])->get(), $this->admin);

        expect($result['approved'])->toBe(1)
            ->and($second->refresh()->status)->toBe(QuestionStatus::Approved)
            ->and($second->approvalLogs()->count())->toBe(1);
    });

    it('rejects every selected question with one reason', function () {
        [$one, $two] = [($this->question)('One?'), ($this->question)('Two?')];

        ($this->list)()
            ->selectTableRecords([$one->id, $two->id])
            ->callAction(TestAction::make('rejectSelected')->table()->bulk(), ['rejection_reason' => 'Unclear'])
            ->assertNotified(__(':count rejected', ['count' => 2]));

        expect($one->refresh()->status)->toBe(QuestionStatus::Rejected)
            ->and($two->refresh()->rejection_reason)->toBe('Unclear');
    });

    it('is the Admin\'s alone', function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        expect(QuestionReviewActions::approveSelected()->isVisible())->toBeFalse()
            ->and(QuestionReviewActions::rejectSelected()->isVisible())->toBeFalse();
    });
});

describe('reviewing from the View window', function () {
    it('approves, then shows the next pending question without opening it again', function () {
        $first = ($this->question)('First?');
        ($this->question)('Already approved?', ['status' => QuestionStatus::Approved]);
        $third = ($this->question)('Third?');

        ($this->list)()
            ->mountAction(TestAction::make('view')->table($first))
            ->callMountedAction(arguments: ['decision' => 'approve'])
            ->assertActionMounted(TestAction::make('view')->table($third));

        expect($first->refresh()->status)->toBe(QuestionStatus::Approved);
    });

    it('rejects only with a reason', function () {
        $first = ($this->question)('First?');
        $second = ($this->question)('Second?');

        ($this->list)()
            ->mountAction(TestAction::make('view')->table($first))
            ->callMountedAction(arguments: ['decision' => 'reject'])
            ->assertNotified(__('Write a reason to reject the question.'))
            ->assertActionMounted(TestAction::make('view')->table($first));

        expect($first->refresh()->status)->toBe(QuestionStatus::Pending);

        ($this->list)()
            ->mountAction(TestAction::make('view')->table($first))
            ->setActionData(['rejection_reason' => 'Wrong answer'])
            ->callMountedAction(arguments: ['decision' => 'reject'])
            ->assertActionMounted(TestAction::make('view')->table($second));

        expect($first->refresh()->status)->toBe(QuestionStatus::Rejected)
            ->and($first->rejection_reason)->toBe('Wrong answer');
    });

    it('moves on without deciding, and closes with a note when none is left', function () {
        $only = ($this->question)('Only one?');

        ($this->list)()
            ->mountAction(TestAction::make('view')->table($only))
            ->callMountedAction(arguments: ['decision' => 'next'])
            ->assertNotified(__('No more pending questions in this list.'))
            ->assertActionNotMounted(TestAction::make('view')->table($only));

        expect($only->refresh()->status)->toBe(QuestionStatus::Pending);
    });

    it('shows a teacher the question only, with nothing to decide', function () {
        $own = ($this->question)('Mine?');
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->mountAction(TestAction::make('view')->table($own))
            ->assertMountedActionModalDontSee(__('Next question'));
    });
});

it('finds the next pending question in the list\'s order, going round from the start', function () {
    [$a, $b, $c] = [($this->question)('A?'), ($this->question)('B?', ['status' => QuestionStatus::Approved]), ($this->question)('C?')];
    $order = [$a->id, $b->id, $c->id];

    expect(QuestionReviewActions::nextPendingAfter($a->id, $order))->toBe($c->id)
        ->and(QuestionReviewActions::nextPendingAfter($c->id, $order))->toBe($a->id)
        ->and(QuestionReviewActions::nextPendingAfter($a->id, [$a->id, $b->id]))->toBeNull();
});
