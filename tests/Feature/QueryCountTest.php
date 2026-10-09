<?php

use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Staff\Pages\MonthlyEarnings;
use App\Filament\Staff\Pages\MyEarnings;
use App\Filament\Staff\Widgets\StaffEarningsTrendChartWidget;
use App\Filament\Staff\Widgets\StaffQuestionRatesWidget;
use App\Filament\Staff\Widgets\StaffStatsOverviewWidget;
use App\Models\BoardCqQuestion;
use App\Models\BoardCqQuestionPart;
use App\Models\BoardMcqQuestion;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Question;
use App\Models\QuestionRate;
use App\Models\ReferralReward;
use App\Models\StaffEarning;
use App\Models\StaffPayout;
use App\Models\Subject;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionPurchaseService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

/**
 * Guards against N+1 queries: each of these does the same work for a small
 * and a larger number of rows and expects the number of queries not to
 * grow with it.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->queriesRunBy = function (Closure $work): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    /**
     * A published exam of that many MCQs, each with option "a" correct.
     *
     * @return array{0: Exam, 1: array<int, string>} The exam, and an all-correct answer sheet.
     */
    $this->examWith = function (int $questionCount): array {
        $exam = Exam::factory()->published()->create();
        $questions = Question::factory()->approved()->for(Chapter::factory())->count($questionCount)->create([
            'options' => [
                ['option' => 'a', 'image' => null, 'is_correct' => true],
                ['option' => 'b', 'image' => null, 'is_correct' => false],
            ],
            'marks' => 1,
        ]);
        $exam->questions()->attach($questions->mapWithKeys(fn (Question $question, int $index): array => [
            $question->id => ['order_index' => $index, 'marks_override' => null],
        ])->all());

        return [$exam, $questions->mapWithKeys(fn (Question $question): array => [$question->id => 'a'])->all()];
    };
});

it('saves and grades a paper in the same number of queries however many questions it has', function () {
    $queries = [];

    foreach ([3, 30] as $questionCount) {
        [$exam, $answers] = ($this->examWith)($questionCount);
        $attempt = ExamAttempt::startFor($exam->fresh(), User::factory()->student()->create());

        $queries[$questionCount] = ($this->queriesRunBy)(function () use ($attempt, $answers): void {
            $attempt->recordAnswers($answers);
            $attempt->submitAndAutoGrade();
        });

        expect((float) $attempt->refresh()->total_score)->toBe((float) $questionCount);
    }

    expect($queries[30])->toBe($queries[3]);
});

it('prices a list of plans in the same number of queries however many plans there are', function () {
    $student = User::factory()->student()->create();
    $purchases = app(SubscriptionPurchaseService::class);
    $queries = [];

    // Run once first: whatever is read a single time per request (the
    // billing settings) must not count against the smaller run only.
    $purchases->quotes($student, [], useWallet: true);

    foreach ([2, 12] as $planCount) {
        $plans = SubscriptionPlan::factory()->pro()->forStudents()->count($planCount)->create();

        $queries[$planCount] = ($this->queriesRunBy)(fn () => $purchases->quotes($student, $plans, useWallet: true));
    }

    expect($queries[12])->toBe($queries[2]);
});

it('lists the staff who are owed money in one query however many there are', function () {
    foreach (range(1, 6) as $index) {
        $staff = User::factory()->staff()->create();
        StaffEarning::factory()->create(['staff_id' => $staff->id, 'question_id' => Question::factory(), 'amount' => 10]);
        ReferralReward::factory()->create(['referrer_id' => $staff->id, 'amount' => 5]);
    }

    $queries = ($this->queriesRunBy)(function (): void {
        $owed = StaffPayout::staffOwedMoney()->get();

        expect($owed)->toHaveCount(6);
        expect($owed->map(fn (User $staff): float => StaffPayout::amountDueOn($staff))->unique()->all())->toBe([15.0]);
    });

    expect($queries)->toBe(1);
});

it('renders the admin payments table in the same number of queries however many payments it lists', function () {
    $this->actingAs(User::factory()->admin()->create());
    $queries = [];

    // Run once first, so one-off work of the first render doesn't count
    // against the smaller run only.
    livewire(ManagePayments::class)->assertOk();

    foreach ([2, 8] as $paymentCount) {
        Payment::query()->delete();

        foreach (range(1, $paymentCount) as $index) {
            $referrer = User::factory()->teacher()->create(['phone' => '01811111111']);
            $buyer = User::factory()->student()->create();
            $buyer->forceFill(['referred_by_id' => $referrer->id])->save();
            Payment::factory()->pending()->create(['user_id' => $buyer->id, 'payer_number' => '01811111111']);
        }

        $queries[$paymentCount] = ($this->queriesRunBy)(fn () => livewire(ManagePayments::class)->assertOk());
    }

    expect($queries[8])->toBe($queries[2]);
});

it('shows every subject\'s rate in the same number of queries however many subjects there are', function () {
    $this->actingAs(User::factory()->staff()->create());
    Filament::setCurrentPanel(Filament::getPanel('staff'));
    $queries = [];

    foreach ([2, 9] as $subjectCount) {
        Subject::factory()->count($subjectCount)->create()
            ->each(fn (Subject $subject) => QuestionRate::factory()->create(['subject_id' => $subject->id]));

        $queries[$subjectCount] = ($this->queriesRunBy)(fn () => livewire(StaffQuestionRatesWidget::class)->assertOk());
    }

    expect($queries[9])->toBe($queries[2]);
});

it('opens a board paper for editing in the same number of queries however many questions it has', function () {
    $this->actingAs(User::factory()->admin()->create());
    $queries = [];

    // Run once first, so one-off work of the first render doesn't count
    // against the smaller run only.
    livewire(EditBoardQuestionPaper::class, ['record' => BoardQuestionPaper::factory()->create()->id])->assertOk();

    foreach ([2, 9] as $questionCount) {
        $paper = BoardQuestionPaper::factory()->create();
        BoardMcqQuestion::factory()->count($questionCount)->create(['board_question_paper_id' => $paper->id]);
        BoardCqQuestion::factory()->count($questionCount)->create(['board_question_paper_id' => $paper->id])
            ->each(fn (BoardCqQuestion $question) => BoardCqQuestionPart::factory()->create(['board_cq_question_id' => $question->id]));

        $queries[$questionCount] = ($this->queriesRunBy)(
            fn () => livewire(EditBoardQuestionPaper::class, ['record' => $paper->id])->assertOk(),
        );
    }

    expect($queries[9])->toBe($queries[2]);
});

it('shows a staff member\'s earnings pages in the same number of queries however much they have earned', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('staff'));
    $queries = [];
    $chapter = Chapter::factory()->create();

    foreach ([2, 12] as $earningCount) {
        foreach (range(1, $earningCount) as $index) {
            StaffEarning::factory()->create([
                'staff_id' => $staff->id,
                'question_id' => Question::factory()->approved()->for($chapter),
                'amount' => 5,
            ]);
        }

        $queries[$earningCount] = ($this->queriesRunBy)(function (): void {
            livewire(MyEarnings::class)->assertOk();
            livewire(MonthlyEarnings::class)->assertOk();
            livewire(StaffStatsOverviewWidget::class)->assertOk();
            livewire(StaffEarningsTrendChartWidget::class)->assertOk();
        });
    }

    expect($queries[12])->toBe($queries[2]);
});
