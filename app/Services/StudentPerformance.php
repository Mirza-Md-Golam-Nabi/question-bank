<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\GenerationMode;
use App\Enums\QuestionType;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\ExamAttempt;
use App\Models\Question;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A student's performance, worked out from the papers they have handed in
 * — the one place its rules live (CLAUDE.md rule 11). Whose exams count is
 * the PerformanceScope's; who may see it at all is
 * SubscriptionLimitService::allowsAnalytics().
 *
 * - A score is the paper's total score as a share of its total marks.
 *   "Practise my mistakes" papers are left off the score figures: they are
 *   made of questions the student got wrong, so a low score there is work
 *   on weaknesses, not a fall.
 * - The question-by-question figures (subject, chapter, difficulty,
 *   coverage) count each MCQ's latest answer only, matched across versions
 *   — a mistake put right stops counting against the student. Every kind
 *   of paper counts here, mistakes practice included. A blank is not right.
 * - A past paper's questions are not the pool's: they add to their
 *   subject's rate (when the paper has one) and to the scores, but have no
 *   chapter or difficulty.
 */
class StudentPerformance
{
    /**
     * The handful of numbers at the top of the dashboard.
     *
     * @return array{exams_this_month: int, average_this_month: ?float, average_last_month: ?float, streak_days: int}
     */
    public function summary(PerformanceScope $scope): array
    {
        $startOfMonth = now()->startOfMonth();
        $startOfLastMonth = now()->subMonthNoOverflow()->startOfMonth();

        $thisMonth = fn (Builder $attempts): Builder => $attempts
            ->where('attempt.submitted_at', '>=', $startOfLastMonth)
            ->selectRaw('case when attempt.submitted_at >= ? then 1 else 0 end as this_month', [$startOfMonth])
            ->groupBy('this_month');

        $exams = $thisMonth($this->submittedAttempts($scope))
            ->selectRaw('count(*) as exams')
            ->pluck('exams', 'this_month');

        $averages = $thisMonth($this->withoutMistakesPractice($this->scoredAttempts($scope)))
            ->selectRaw('avg('.ExamAttempt::SCORE_PERCENT_SQL.') as average')
            ->pluck('average', 'this_month');

        $average = fn (int $thisMonth): ?float => isset($averages[$thisMonth]) ? round((float) $averages[$thisMonth], 1) : null;

        return [
            'exams_this_month' => (int) ($exams[1] ?? 0),
            'average_this_month' => $average(1),
            'average_last_month' => $average(0),
            'streak_days' => $this->streakDays($scope),
        ];
    }

    /**
     * The scores of the latest papers (BillingSetting::performance_trend_exams
     * of them), oldest first, mistakes practice left out.
     *
     * @return Collection<int, array{title: string, submitted_at: CarbonInterface, percent: float}>
     */
    public function scoreTrend(PerformanceScope $scope): Collection
    {
        return $this->withoutMistakesPractice($this->scoredAttempts($scope))
            ->orderByDesc('attempt.submitted_at')
            ->orderByDesc('attempt.id')
            ->limit(BillingSetting::current()->performance_trend_exams)
            ->selectRaw('exams.title, attempt.submitted_at, '.ExamAttempt::SCORE_PERCENT_SQL.' as percent')
            ->get()
            ->reverse()
            ->values()
            ->map(fn (object $row): array => [
                'title' => (string) $row->title,
                'submitted_at' => Carbon::parse($row->submitted_at),
                'percent' => round((float) $row->percent, 1),
            ]);
    }

    /**
     * The rate over every MCQ the student has had, past papers' included.
     */
    public function overallRate(PerformanceScope $scope): PerformanceRate
    {
        return PerformanceRate::fromRow($this->latestAnswers($scope, poolOnly: false)
            ->selectRaw('count(*) as questions, sum(correct) as correct, sum(blank) as blank')
            ->first());
    }

    /**
     * Each subject's rate, with how much of its approved MCQs the student
     * has had at least once — weakest first, the ones without enough data
     * last.
     *
     * @return Collection<int, array{class_subject: ClassSubject, rate: PerformanceRate, coverage: ?int}>
     */
    public function subjectRates(PerformanceScope $scope): Collection
    {
        $rows = $this->aggregate($scope, 'class_subject_id', poolOnly: false);

        $classSubjects = ClassSubject::query()
            ->with(['subject', 'academicClass'])
            ->whereKey($rows->keys())
            ->get()
            ->keyBy('id');

        $sat = $this->latestAnswers($scope, poolOnly: true)
            ->groupBy('class_subject_id')
            ->selectRaw('class_subject_id, count(*) as sat')
            ->pluck('sat', 'class_subject_id');

        $pool = Question::query()
            ->approvedPool()
            ->where('questions.question_type', QuestionType::Mcq)
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->whereIn('chapters.class_subject_id', $sat->keys())
            ->groupBy('chapters.class_subject_id')
            ->toBase()
            ->selectRaw('chapters.class_subject_id, count(*) as questions')
            ->pluck('questions', 'class_subject_id');

        return $this->weakestFirst($rows
            ->filter(fn (PerformanceRate $rate, int $id): bool => $classSubjects->has($id))
            ->map(fn (PerformanceRate $rate, int $id): array => [
                'class_subject' => $classSubjects[$id],
                'rate' => $rate,
                'coverage' => ($pool[$id] ?? 0) > 0 ? (int) min(100, round(($sat[$id] ?? 0) / $pool[$id] * 100)) : null,
            ]));
    }

    /**
     * Each chapter's rate, weakest first.
     *
     * @return Collection<int, array{chapter: Chapter, rate: PerformanceRate}>
     */
    public function chapterRates(PerformanceScope $scope): Collection
    {
        $rows = $this->aggregate($scope, 'chapter_id', poolOnly: true);

        $chapters = Chapter::query()
            ->with('classSubject.subject')
            ->whereKey($rows->keys())
            ->get()
            ->keyBy('id');

        return $this->weakestFirst($rows
            ->filter(fn (PerformanceRate $rate, int $id): bool => $chapters->has($id))
            ->map(fn (PerformanceRate $rate, int $id): array => ['chapter' => $chapters[$id], 'rate' => $rate]));
    }

    /**
     * The rate at each difficulty, easiest first; a difficulty never met
     * comes back empty.
     *
     * @return array<string, PerformanceRate> difficulty value => rate
     */
    public function difficultyRates(PerformanceScope $scope): array
    {
        $rows = $this->aggregate($scope, 'difficulty', poolOnly: true);

        return collect(Difficulty::cases())
            ->mapWithKeys(fn (Difficulty $difficulty): array => [
                $difficulty->value => $rows[$difficulty->value] ?? new PerformanceRate(0, 0, 0),
            ])
            ->all();
    }

    /**
     * Days in a row, up to today, with at least one paper handed in — a
     * streak still alive when today has none yet but yesterday had.
     */
    public function streakDays(PerformanceScope $scope): int
    {
        // Told apart here rather than in SQL, where taking the date of a
        // timestamp is written differently by each database.
        $days = $this->submittedAttempts($scope)
            ->where('attempt.submitted_at', '>=', now()->subYear())
            ->pluck('attempt.submitted_at')
            ->map(fn (string $submittedAt): string => Carbon::parse($submittedAt)->toDateString())
            ->unique()
            ->flip();

        $day = now()->startOfDay();

        if (! $days->has($day->toDateString())) {
            $day = $day->subDay();
        }

        $streak = 0;

        while ($days->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * The scope's handed-in papers, joined to their exams.
     */
    private function submittedAttempts(PerformanceScope $scope): Builder
    {
        return $scope->constrainExams(DB::table('exam_attempts as attempt')
            ->join('exams', 'exams.id', '=', 'attempt.exam_id')
            ->where('attempt.student_id', $scope->studentId)
            ->whereIn('attempt.status', ExamAttempt::submittedStatusValues()));
    }

    /**
     * Handed-in papers that have a score to speak of.
     */
    private function scoredAttempts(PerformanceScope $scope): Builder
    {
        return $this->submittedAttempts($scope)->where('exams.total_marks', '>', 0);
    }

    /**
     * Leaves out "Practise my mistakes" papers — what the score figures do.
     */
    private function withoutMistakesPractice(Builder $attempts): Builder
    {
        return $attempts->where(fn (Builder $notMistakes) => $notMistakes
            ->whereNull('exams.generation_mode')
            ->orWhere('exams.generation_mode', '!=', GenerationMode::Mistakes->value));
    }

    /**
     * One row per MCQ the student has had — its latest answer only, the
     * pool's questions matched across versions. Columns: class_subject_id,
     * chapter_id and difficulty (both null for a past paper's question),
     * correct and blank (1 or 0).
     */
    private function latestAnswers(PerformanceScope $scope, bool $poolOnly): Builder
    {
        $ranked = 'row_number() over (partition by %s order by attempt.submitted_at desc, attempt.id desc) as answer_rank';

        $pool = $scope->constrainAnsweredExams(DB::table('exam_attempts as attempt')
            ->join('exams', 'exams.id', '=', 'attempt.exam_id')
            ->join('exam_questions as on_paper', 'on_paper.exam_id', '=', 'exams.id')
            ->join('questions', 'questions.id', '=', 'on_paper.question_id')
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->leftJoin('attempt_answers as answer', fn (JoinClause $answer) => $answer
                ->on('answer.attempt_id', '=', 'attempt.id')
                ->on('answer.question_id', '=', 'questions.id'))
            ->where('attempt.student_id', $scope->studentId)
            ->whereIn('attempt.status', ExamAttempt::submittedStatusValues())
            ->whereNull('exams.board_question_paper_id')
            ->where('questions.question_type', QuestionType::Mcq->value))
            ->selectRaw('chapters.class_subject_id, questions.chapter_id, questions.difficulty')
            ->selectRaw('case when answer.is_correct = 1 then 1 else 0 end as correct, case when answer.id is null then 1 else 0 end as blank')
            ->selectRaw(sprintf($ranked, 'coalesce(questions.parent_id, questions.id)'));

        $answers = $pool;

        if (! $poolOnly) {
            $papers = $scope->constrainAnsweredExams(DB::table('exam_attempts as attempt')
                ->join('exams', 'exams.id', '=', 'attempt.exam_id')
                ->join('board_question_papers as paper', 'paper.id', '=', 'exams.board_question_paper_id')
                ->join('board_mcq_questions as paper_mcq', 'paper_mcq.board_question_paper_id', '=', 'paper.id')
                ->leftJoin('attempt_answers as answer', fn (JoinClause $answer) => $answer
                    ->on('answer.attempt_id', '=', 'attempt.id')
                    ->on('answer.board_mcq_question_id', '=', 'paper_mcq.id'))
                ->where('attempt.student_id', $scope->studentId)
                ->whereIn('attempt.status', ExamAttempt::submittedStatusValues()))
                ->selectRaw('paper.class_subject_id, null as chapter_id, null as difficulty')
                ->selectRaw('case when answer.is_correct = 1 then 1 else 0 end as correct, case when answer.id is null then 1 else 0 end as blank')
                ->selectRaw(sprintf($ranked, 'paper_mcq.id'));

            $answers = $pool->unionAll($papers);
        }

        return DB::query()->fromSub($answers, 'answers')->where('answer_rank', 1);
    }

    /**
     * The latest answers grouped by one of their columns, a rate per group.
     *
     * @return Collection<int|string, PerformanceRate>
     */
    private function aggregate(PerformanceScope $scope, string $column, bool $poolOnly): Collection
    {
        return $this->latestAnswers($scope, $poolOnly)
            ->whereNotNull($column)
            ->groupBy($column)
            ->selectRaw("{$column} as group_key, count(*) as questions, sum(correct) as correct, sum(blank) as blank")
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->group_key => PerformanceRate::fromRow($row)]);
    }

    /**
     * Lowest rate first; the groups without enough data to have one, last.
     *
     * @template T of array{rate: PerformanceRate}
     *
     * @param  Collection<int, T>  $groups
     * @return Collection<int, T>
     */
    private function weakestFirst(Collection $groups): Collection
    {
        return $groups
            ->sortBy(fn (array $group): array => [$group['rate']->percent() === null ? 1 : 0, $group['rate']->percent() ?? 0, -$group['rate']->questions])
            ->values();
    }
}
