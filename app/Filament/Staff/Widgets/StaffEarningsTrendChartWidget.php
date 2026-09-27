<?php

namespace App\Filament\Staff\Widgets;

use App\Models\StaffEarning;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class StaffEarningsTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'গত ৬ মাসের আয়';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $earnings = StaffEarning::where('staff_id', Auth::id())->get(['amount', 'created_at']);

        $months = collect(range(5, 0))
            ->map(fn (int $monthsAgo) => now()->subMonthsNoOverflow($monthsAgo));

        return [
            'datasets' => [
                [
                    'label' => 'আয় (৳)',
                    'data' => $months
                        ->map(fn (Carbon $month) => (float) $earnings
                            ->filter(fn (StaffEarning $earning) => $earning->created_at->isSameMonth($month) && $earning->created_at->isSameYear($month))
                            ->sum('amount'))
                        ->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn (Carbon $month) => $month->format('M \'y'))->all(),
        ];
    }
}
