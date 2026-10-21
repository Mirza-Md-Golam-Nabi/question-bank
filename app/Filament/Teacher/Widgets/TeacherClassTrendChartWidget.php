<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Widgets\Concerns\ReadsClassPerformance;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * The class's average score on each of the teacher's latest exams, and how
 * many sat each.
 */
class TeacherClassTrendChartWidget extends ChartWidget
{
    use ReadsClassPerformance;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getHeading(): string|Htmlable|null
    {
        return __('Class score trend');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('The average score (%) on your latest exams, and how many sat each — guests included.');
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
        $exams = $this->classPerformance()->classTrend(Auth::user(), $this->classSubjectId());

        return [
            'datasets' => [
                [
                    'label' => __('Average score (%)'),
                    'data' => $exams->pluck('average')->all(),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'type' => 'bar',
                    'label' => __('Participants'),
                    'data' => $exams->pluck('participants')->all(),
                    'backgroundColor' => 'rgba(148, 163, 184, 0.35)',
                    'yAxisID' => 'participants',
                ],
            ],
            'labels' => $exams->map(fn (array $exam): string => str($exam['title'])->limit(18)->toString())->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['min' => 0, 'max' => 100, 'position' => 'left'],
                'participants' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
