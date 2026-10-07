<?php

namespace App\Filament\Staff\Widgets;

use App\Enums\QuestionStatus;
use App\Enums\StaffEarningStatus;
use App\Filament\Support\StatusBadgePills;
use App\Models\Question;
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

        $questionCounts = Question::where('created_by', $staffId)
            ->where('is_latest', true)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pending = (int) ($questionCounts[QuestionStatus::Pending->value] ?? 0);
        $approved = (int) ($questionCounts[QuestionStatus::Approved->value] ?? 0);
        $rejected = (int) ($questionCounts[QuestionStatus::Rejected->value] ?? 0);
        $decided = $approved + $rejected;
        $approvalRate = $decided > 0 ? round($approved / $decided * 100, 1) : null;

        $earnings = StaffEarning::where('staff_id', $staffId);

        $thisMonthEarning = (clone $earnings)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $totalEarned = (clone $earnings)->sum('amount');
        $unpaid = (clone $earnings)->where('status', StaffEarningStatus::PendingPayout)->sum('amount');

        return [
            Stat::make(__('Total questions'), $pending + $approved + $rejected)
                ->description(StatusBadgePills::questionStatus($pending, $approved, $rejected))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-slate-500 to-slate-600 [&_*]:!text-white']),

            Stat::make(__('Earnings this month'), '৳'.number_format((float) $thisMonthEarning, 2))
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-emerald-500 to-emerald-600 [&_*]:!text-white']),

            Stat::make(__('Total earnings'), '৳'.number_format((float) $totalEarned, 2))
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-sky-500 to-sky-600 [&_*]:!text-white']),

            Stat::make(__('Unpaid balance'), '৳'.number_format((float) $unpaid, 2))
                ->icon('heroicon-o-wallet')
                ->color('warning')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-amber-500 to-amber-600 [&_*]:!text-white']),

            Stat::make(__('Approval rate'), $approvalRate === null ? '—' : number_format($approvalRate, 1).'%')
                ->icon('heroicon-o-chart-bar')
                ->color(match (true) {
                    $approvalRate === null => 'gray',
                    $approvalRate >= 70 => 'success',
                    $approvalRate >= 40 => 'warning',
                    default => 'danger',
                })
                ->extraAttributes([
                    'class' => match (true) {
                        $approvalRate === null => 'bg-gradient-to-br from-gray-400 to-gray-500 [&_*]:!text-white',
                        $approvalRate >= 70 => 'bg-gradient-to-br from-emerald-500 to-emerald-600 [&_*]:!text-white',
                        $approvalRate >= 40 => 'bg-gradient-to-br from-amber-500 to-amber-600 [&_*]:!text-white',
                        default => 'bg-gradient-to-br from-rose-500 to-rose-600 [&_*]:!text-white',
                    },
                ]),
        ];
    }
}
