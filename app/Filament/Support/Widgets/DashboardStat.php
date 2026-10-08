<?php

namespace App\Filament\Support\Widgets;

use App\Enums\QuestionStatus;
use App\Filament\Support\StatusBadgePills;
use App\Models\Question;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Builds the coloured stat cards of the Staff and Teacher dashboards, so the
 * card styling and the question figures both dashboards show ("Total
 * questions", "Approval rate") are defined once.
 */
class DashboardStat
{
    /**
     * The card background for each Filament colour. Written out in full
     * rather than assembled from the colour name, because Tailwind only
     * generates classes it can find as literal text.
     */
    private const GRADIENTS = [
        'gray' => 'bg-gradient-to-br from-slate-500 to-slate-600 [&_*]:!text-white',
        'muted' => 'bg-gradient-to-br from-gray-400 to-gray-500 [&_*]:!text-white',
        'success' => 'bg-gradient-to-br from-emerald-500 to-emerald-600 [&_*]:!text-white',
        'info' => 'bg-gradient-to-br from-sky-500 to-sky-600 [&_*]:!text-white',
        'warning' => 'bg-gradient-to-br from-amber-500 to-amber-600 [&_*]:!text-white',
        'danger' => 'bg-gradient-to-br from-rose-500 to-rose-600 [&_*]:!text-white',
    ];

    /**
     * @param  string  $color  A Filament colour; "muted" is a paler gray for "nothing to show yet".
     */
    public static function make(string $label, string|int|float|Htmlable $value, string $icon, string $color): Stat
    {
        return Stat::make($label, $value)
            ->icon($icon)
            ->color($color === 'muted' ? 'gray' : $color)
            ->extraAttributes(['class' => self::GRADIENTS[$color]]);
    }

    public static function money(float|int|string|null $amount): string
    {
        return '৳'.number_format((float) $amount, 2);
    }

    /**
     * How many of a user's (latest-version) questions are in each status.
     *
     * @return array{pending: int, approved: int, rejected: int}
     */
    public static function questionCountsFor(int $userId): array
    {
        $counts = Question::where('created_by', $userId)
            ->where('is_latest', true)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($counts[QuestionStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[QuestionStatus::Approved->value] ?? 0),
            'rejected' => (int) ($counts[QuestionStatus::Rejected->value] ?? 0),
        ];
    }

    /**
     * @param  array{pending: int, approved: int, rejected: int}  $counts
     */
    public static function totalQuestions(array $counts): Stat
    {
        return self::make(__('Total questions'), array_sum($counts), 'heroicon-o-document-text', 'gray')
            ->description(StatusBadgePills::questionStatus($counts['pending'], $counts['approved'], $counts['rejected']));
    }

    /**
     * Approved as a share of the questions an admin has already decided on
     * — pending ones don't count either way.
     *
     * @param  array{pending: int, approved: int, rejected: int}  $counts
     */
    public static function approvalRate(array $counts): Stat
    {
        $decided = $counts['approved'] + $counts['rejected'];
        $rate = $decided > 0 ? round($counts['approved'] / $decided * 100, 1) : null;

        return self::make(
            __('Approval rate'),
            $rate === null ? '—' : number_format($rate, 1).'%',
            'heroicon-o-chart-bar',
            match (true) {
                $rate === null => 'muted',
                $rate >= 70 => 'success',
                $rate >= 40 => 'warning',
                default => 'danger',
            },
        );
    }
}
