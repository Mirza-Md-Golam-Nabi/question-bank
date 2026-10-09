<?php

namespace App\Filament\Staff\Widgets;

use App\Models\StaffEarning;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class StaffEarningsTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return __('Earnings in the last 6 months');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        // Oldest month first, so the line runs left to right through time.
        $months = StaffEarning::monthlyTotalsFor(Auth::id(), months: 6)->reverse()->values();

        return [
            'datasets' => [
                [
                    'label' => __('Earnings (৳)'),
                    'data' => $months->pluck('total')->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->pluck('label')->all(),
        ];
    }
}
