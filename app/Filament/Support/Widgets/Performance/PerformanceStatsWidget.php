<?php

namespace App\Filament\Support\Widgets\Performance;

use App\Filament\Support\Widgets\DashboardStat;
use App\Filament\Support\Widgets\Performance\Concerns\ReadsStudentPerformance;
use App\Services\PerformanceRate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers at the top of a student's performance: exams this
 * month, the month's average score against last month's, the share of
 * questions answered correctly, and the days in a row with an exam.
 */
class PerformanceStatsWidget extends StatsOverviewWidget
{
    use ReadsStudentPerformance;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $scope = $this->performanceScope();
        $summary = $this->performance()->summary($scope);
        $overall = $this->performance()->overallRate($scope)->percent();
        $average = $summary['average_this_month'];
        $lastMonth = $summary['average_last_month'];

        return [
            DashboardStat::make(__('Exams this month'), $summary['exams_this_month'], 'heroicon-o-clipboard-document-check', 'info'),

            DashboardStat::make(
                __('Average score this month'),
                $average === null ? '—' : self::percent($average),
                'heroicon-o-chart-bar',
                $average === null ? 'muted' : PerformanceRate::colorFor((int) round($average)),
            )->description(match (true) {
                $average === null || $lastMonth === null => __('Nothing to compare with last month'),
                $average > $lastMonth => __(':change more than last month', ['change' => self::percent($average - $lastMonth)]),
                $average < $lastMonth => __(':change less than last month', ['change' => self::percent($lastMonth - $average)]),
                default => __('The same as last month'),
            })->descriptionIcon(match (true) {
                $average === null || $lastMonth === null => null,
                $average > $lastMonth => 'heroicon-m-arrow-trending-up',
                $average < $lastMonth => 'heroicon-m-arrow-trending-down',
                default => 'heroicon-m-minus',
            }),

            DashboardStat::make(
                __('Correct answers'),
                $overall === null ? '—' : "{$overall}%",
                'heroicon-o-check-badge',
                $overall === null ? 'muted' : PerformanceRate::colorFor($overall),
            )->description($overall === null ? __('More data needed') : __('Of the questions had (latest answer each)')),

            DashboardStat::make(
                __('Days in a row'),
                $summary['streak_days'],
                'heroicon-o-fire',
                $summary['streak_days'] > 0 ? 'warning' : 'muted',
            )->description(__('Days with at least one exam')),
        ];
    }

    private static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    }
}
