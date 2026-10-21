<?php

namespace App\Filament\Support\Widgets\Performance;

use App\Filament\Support\Widgets\Performance\Concerns\ReadsStudentPerformance;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A student's score on each of their latest exams, as a line through time.
 */
class ScoreTrendChartWidget extends ChartWidget
{
    use ReadsStudentPerformance;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getHeading(): string|Htmlable|null
    {
        return __('Score trend');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('The score (%) on the latest exams, oldest first. "Practise my mistakes" exams are left out.');
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
        $points = $this->performance()->scoreTrend($this->performanceScope());

        return [
            'datasets' => [
                [
                    'label' => __('Score (%)'),
                    'data' => $points->pluck('percent')->all(),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $points->map(fn (array $point): string => $point['submitted_at']->translatedFormat('j M'))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['min' => 0, 'max' => 100]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
