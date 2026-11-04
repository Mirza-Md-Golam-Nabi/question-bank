<?php

namespace App\Services;

use App\Models\AcademicClass;
use App\Models\BoardQuestionPaper;
use App\Models\Question;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * How the question bank is growing — the "Live database update" card on
 * every panel's dashboard: how many questions joined it on each of the last
 * days, when the last one did, and which classes they were of.
 *
 * A question joins the bank the day it is approved (`approved_at`): an MCQ
 * or a CQ of the shared bank (Question::scopeAddedToBank()), and every MCQ
 * and CQ of a past paper on the day the paper is. Nothing pending,
 * rejected, a School's own or a corrected version is counted.
 *
 * The same for everyone, so worked out once a minute for all of them —
 * however many dashboards are open, the database is asked once.
 */
final class QuestionBankGrowth
{
    public const DAYS = 7;

    private const CACHE_KEY = 'question-bank-growth';

    private const CACHE_SECONDS = 60;

    /**
     * @return array{
     *     days: list<array{date: string, count: int}>,
     *     last_added_at: string|null,
     *     classes: list<string>,
     *     source_types: list<string>,
     * } Each day oldest first (`Y-m-d`); the classes and past paper kinds
     *   (PaperSourceType values, for a paper without a class) of the last day
     *   anything joined.
     */
    public function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->workOut());
    }

    /**
     * @return array{days: list<array{date: string, count: int}>, last_added_at: string|null, classes: list<string>, source_types: list<string>}
     */
    private function workOut(): array
    {
        $days = collect(range(self::DAYS - 1, 0))->map(fn (int $daysAgo): CarbonImmutable => CarbonImmutable::today()->subDays($daysAgo));

        $questionCounts = $this->bankQuestionsPerDay($days->all());
        $paperCounts = $this->pastPaperQuestionsPerDay($days->first());

        $lastAddedAt = collect([
            Question::query()->addedToBank()->max('approved_at'),
            BoardQuestionPaper::query()->approved()->max('approved_at'),
        ])->filter()->max();

        $lastDay = $lastAddedAt ? CarbonImmutable::parse($lastAddedAt)->startOfDay() : null;

        return [
            'days' => $days->map(fn (CarbonImmutable $day, int $index): array => [
                'date' => $day->toDateString(),
                'count' => $questionCounts[$index] + ($paperCounts[$day->toDateString()] ?? 0),
            ])->values()->all(),
            'last_added_at' => $lastAddedAt ? CarbonImmutable::parse($lastAddedAt)->toIso8601String() : null,
            ...($lastDay ? $this->addedOn($lastDay) : ['classes' => [], 'source_types' => []]),
        ];
    }

    /**
     * One query for every day, so it reads the same on SQLite and MySQL (no
     * date function of either).
     *
     * @param  list<CarbonImmutable>  $days
     * @return list<int>
     */
    private function bankQuestionsPerDay(array $days): array
    {
        $columns = [];
        $bindings = [];

        foreach ($days as $index => $day) {
            $columns[] = "sum(case when approved_at >= ? and approved_at < ? then 1 else 0 end) as day_{$index}";
            array_push($bindings, $day, $day->addDay());
        }

        $row = Question::query()
            ->addedToBank()
            ->where('approved_at', '>=', $days[0])
            ->selectRaw(implode(', ', $columns), $bindings)
            ->toBase()
            ->first();

        return array_map(fn (int $index): int => (int) ($row->{"day_{$index}"} ?? 0), array_keys($days));
    }

    /**
     * @return array<string, int> Questions keyed by the day (`Y-m-d`) their paper was approved.
     */
    private function pastPaperQuestionsPerDay(CarbonImmutable $from): array
    {
        return BoardQuestionPaper::query()
            ->approved()
            ->where('approved_at', '>=', $from)
            ->select(['id', 'approved_at'])
            ->withCount(['mcqQuestions', 'cqQuestions'])
            ->get()
            ->groupBy(fn (BoardQuestionPaper $paper): string => $paper->approved_at->toDateString())
            ->map(fn ($papers): int => $papers->sum(fn (BoardQuestionPaper $paper): int => $paper->mcq_questions_count + $paper->cq_questions_count))
            ->all();
    }

    /**
     * The classes of what joined the bank that day, and — since an
     * admission or job test's paper has no class — the kinds of those
     * papers instead ("Medical admission").
     *
     * @return array{classes: list<string>, source_types: list<string>}
     */
    private function addedOn(CarbonImmutable $day): array
    {
        $thatDay = [$day, $day->endOfDay()];

        $papers = BoardQuestionPaper::query()
            ->approved()
            ->whereBetween('approved_at', $thatDay)
            ->with(['classSubject:id,academic_class_id', 'board:id,type'])
            ->get(['id', 'board_id', 'class_subject_id']);

        $classes = AcademicClass::query()
            ->where(fn (Builder $classes) => $classes
                ->whereHas('classSubjects.chapters.questions', fn (Builder $questions) => $questions->addedToBank()->whereBetween('approved_at', $thatDay))
                ->orWhereIn('id', $papers->pluck('classSubject.academic_class_id')->filter()->all()))
            ->ordered()
            ->pluck('name')
            ->all();

        return [
            'classes' => $classes,
            'source_types' => $papers
                ->whereNull('class_subject_id')
                ->map(fn (BoardQuestionPaper $paper): string => $paper->board->type->value)
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
