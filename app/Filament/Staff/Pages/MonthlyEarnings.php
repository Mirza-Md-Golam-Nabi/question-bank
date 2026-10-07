<?php

namespace App\Filament\Staff\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\StaffEarning;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Drill-down reached by clicking the "Total Earned" card on MyEarnings —
 * not a nav item itself (shouldRegisterNavigation), just a detail view.
 */
class MonthlyEarnings extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.staff.pages.monthly-earnings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Monthly Earnings');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MyEarnings::getUrl(panel: 'staff') => __('My Earnings'),
            __('Monthly Earnings'),
        ];
    }

    /**
     * The last 24 months (this month first, oldest last) with how much was
     * earned in each. Grouped in PHP from one query rather than 24 separate
     * `whereMonth` round-trips.
     *
     * @return Collection<int, array{label: string, total: float}>
     */
    public function monthlyEarnings(): Collection
    {
        $earnings = StaffEarning::where('staff_id', Auth::id())->get(['amount', 'created_at']);

        return collect(range(0, 23))
            ->map(function (int $monthsAgo) use ($earnings) {
                $month = now()->subMonthsNoOverflow($monthsAgo);

                return [
                    'label' => $month->format('M \'y'),
                    'total' => (float) $earnings
                        ->filter(fn (StaffEarning $earning) => $earning->created_at->isSameMonth($month) && $earning->created_at->isSameYear($month))
                        ->sum('amount'),
                ];
            });
    }
}
