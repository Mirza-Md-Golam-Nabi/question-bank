<?php

namespace App\Filament\Support\Widgets;

use App\Enums\Locale;
use App\Enums\PaperSourceType;
use App\Services\QuestionBankGrowth;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * "LIVE — Database update!" on every panel's dashboard: how many questions
 * joined the question bank on each of the last days, when the last one did,
 * and which classes they were of (QuestionBankGrowth). Looked at again once
 * a minute while the dashboard is open.
 */
class QuestionBankGrowthWidget extends Widget
{
    protected string $view = 'filament.support.widgets.question-bank-growth';

    protected static ?int $sort = 50;

    protected int|string|array $columnSpan = 'full';

    /**
     * How many lines the chart's scale has above zero.
     */
    private const SCALE_STEPS = 4;

    /**
     * @return array{
     *     bars: list<array{label: string, count: string, percent: float}>,
     *     scale: list<string>,
     *     total: string,
     *     days: string,
     *     last_added: string|null,
     *     chips: list<string>,
     * }
     */
    public function growth(): array
    {
        $summary = app(QuestionBankGrowth::class)->summary();
        $locale = Locale::current();

        $step = $this->scaleStep(max(array_column($summary['days'], 'count')));
        $top = $step * self::SCALE_STEPS;

        return [
            'bars' => array_map(fn (array $day): array => [
                'label' => $locale->writeDigits(CarbonImmutable::parse($day['date'])->locale(app()->getLocale())->translatedFormat('d M')),
                'count' => $locale->writeDigits((string) $day['count']),
                'percent' => round($day['count'] / $top * 100, 2),
            ], $summary['days']),
            // Highest first, as the scale reads from the top down.
            'scale' => array_map(fn (int $line): string => $locale->writeDigits((string) ($step * $line)), range(self::SCALE_STEPS, 0)),
            'total' => $locale->writeDigits((string) array_sum(array_column($summary['days'], 'count'))),
            'days' => $locale->writeDigits((string) QuestionBankGrowth::DAYS),
            'last_added' => $summary['last_added_at']
                ? $locale->writeDigits(CarbonImmutable::parse($summary['last_added_at'])->locale(app()->getLocale())->diffForHumans())
                : null,
            'chips' => [
                ...$summary['classes'],
                ...array_map(fn (string $type): string => PaperSourceType::from($type)->getLabel(), $summary['source_types']),
            ],
        ];
    }

    /**
     * A round step for the scale (1, 2, 2.5, 3 or 5 times a power of ten)
     * that fits the busiest day in SCALE_STEPS lines — 1077 reads on a
     * scale of 300s, up to 1200.
     */
    private function scaleStep(int $busiestDay): int
    {
        $rough = max(1, $busiestDay) / self::SCALE_STEPS;
        $power = 10 ** floor(log10($rough));

        foreach ([1, 2, 2.5, 3, 5, 10] as $multiple) {
            if ($multiple * $power >= $rough) {
                return (int) max(1, ceil($multiple * $power));
            }
        }

        return (int) (10 * $power);
    }
}
