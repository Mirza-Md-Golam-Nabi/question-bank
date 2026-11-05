<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Widgets\DashboardStat;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

/**
 * The cards of the "Users" page (App\Filament\Pages\UserReport) for its
 * selected tab. The page counts the figures and hands them in, so the
 * widget runs no query of its own; it is only shown on that page, never
 * on the dashboard.
 */
class UserReportStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 3, 'sm' => 2, 'xl' => 6];

    /**
     * @var array{total: int, subscribed: int, unsubscribed: int, paid: int, gifted: int, on_trial: int}
     */
    #[Reactive]
    public array $figures = [];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            DashboardStat::make(__('Total users'), $this->figures['total'] ?? 0, 'heroicon-o-users', 'info'),
            DashboardStat::make(__('Subscribed users'), $this->figures['subscribed'] ?? 0, 'heroicon-o-check-badge', 'success'),
            DashboardStat::make(__('Never subscribed'), $this->figures['unsubscribed'] ?? 0, 'heroicon-o-user-minus', 'gray'),
            DashboardStat::make(__('Bought a subscription'), $this->figures['paid'] ?? 0, 'heroicon-o-banknotes', 'success'),
            DashboardStat::make(__('Trial running'), $this->figures['on_trial'] ?? 0, 'heroicon-o-clock', 'warning'),
            DashboardStat::make(__('Given a gift'), $this->figures['gifted'] ?? 0, 'heroicon-o-gift', 'danger'),
        ];
    }
}
