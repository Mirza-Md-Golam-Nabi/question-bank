<?php

namespace App\Filament\Staff\Widgets;

use App\Filament\Support\Widgets\DashboardStat;
use App\Models\StaffEarning;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StaffStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $staffId = Auth::id();
        $questionCounts = DashboardStat::questionCountsFor($staffId);

        // All three figures from one query.
        ['this_month' => $thisMonthEarning, 'earned' => $totalEarned, 'unpaid' => $unpaid] = StaffEarning::totalsFor($staffId);

        return [
            DashboardStat::totalQuestions($questionCounts),
            DashboardStat::make(__('Earnings this month'), DashboardStat::money($thisMonthEarning), 'heroicon-o-calendar-days', 'success'),
            DashboardStat::make(__('Total earnings'), DashboardStat::money($totalEarned), 'heroicon-o-banknotes', 'info'),
            DashboardStat::make(__('Unpaid balance'), DashboardStat::money($unpaid), 'heroicon-o-wallet', 'warning'),
            DashboardStat::approvalRate($questionCounts),
        ];
    }
}
