<?php

namespace App\Filament\Support\Widgets\Performance;

use App\Enums\Difficulty;
use App\Filament\Support\Widgets\Performance\Concerns\ReadsStudentPerformance;
use App\Services\PerformanceRate;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * How a student does at each difficulty: right, wrong and blank, stacked.
 */
class DifficultyChartWidget extends ChartWidget
{
    use ReadsStudentPerformance;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getHeading(): string|Htmlable|null
    {
        return __('By difficulty');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('Questions answered right, wrong or left blank at each difficulty (latest answer each).');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rates = collect($this->performance()->difficultyRates($this->performanceScope()));

        return [
            'datasets' => [
                ['label' => __('Right'), 'data' => $rates->map(fn (PerformanceRate $rate): int => $rate->correct)->values()->all(), 'backgroundColor' => '#10b981'],
                ['label' => __('Wrong'), 'data' => $rates->map(fn (PerformanceRate $rate): int => $rate->wrong())->values()->all(), 'backgroundColor' => '#f43f5e'],
                ['label' => __('Blank'), 'data' => $rates->map(fn (PerformanceRate $rate): int => $rate->blank)->values()->all(), 'backgroundColor' => '#94a3b8'],
            ],
            'labels' => $rates->keys()->map(fn (string $difficulty): string => Difficulty::from($difficulty)->getLabel())->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
