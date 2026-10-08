<?php

namespace App\Filament\Staff\Widgets;

use App\Enums\StaffEarningStatus;
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

        $earnings = StaffEarning::where('staff_id', $staffId);

        $thisMonthEarning = (clone $earnings)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $totalEarned = (clone $earnings)->sum('amount');
        $unpaid = (clone $earnings)->where('status', StaffEarningStatus::PendingPayout)->sum('amount');

        return [
            DashboardStat::totalQuestions($questionCounts),
            DashboardStat::make(__('Earnings this month'), DashboardStat::money($thisMonthEarning), 'heroicon-o-calendar-days', 'success'),
            DashboardStat::make(__('Total earnings'), DashboardStat::money($totalEarned), 'heroicon-o-banknotes', 'info'),
            DashboardStat::make(__('Unpaid balance'), DashboardStat::money($unpaid), 'heroicon-o-wallet', 'warning'),
            DashboardStat::approvalRate($questionCounts),
        ];
    }
}
