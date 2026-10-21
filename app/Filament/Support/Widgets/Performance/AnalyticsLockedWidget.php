<?php

namespace App\Filament\Support\Widgets\Performance;

use App\Services\SubscriptionLimitService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * What a student or teacher without the analytics in a paid plan sees in
 * their place: a blurred picture of the analytics, drawn from made-up
 * figures (nothing of theirs is worked out), and the way to a plan. A
 * panel's own widget only says which page that is.
 */
abstract class AnalyticsLockedWidget extends Widget
{
    protected string $view = 'filament.support.widgets.performance.analytics-locked';

    protected int|string|array $columnSpan = 'full';

    abstract public function subscriptionUrl(): string;

    /**
     * The text over the picture: what the analytics would show this user.
     */
    abstract public function pitch(): string;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null && ! app(SubscriptionLimitService::class)->allowsAnalytics($user);
    }

    /**
     * The made-up scores the blurred line is drawn through, as SVG points.
     */
    public function samplePoints(): string
    {
        return collect([52, 58, 55, 63, 61, 70, 68, 76, 74, 82])
            ->map(fn (int $score, int $index): string => ($index * 40 + 10).','.(110 - $score))
            ->join(' ');
    }

    /**
     * Made-up subjects and rates for the blurred bars.
     *
     * @return array<int, array{name: string, percent: int}>
     */
    public function sampleSubjects(): array
    {
        return [
            ['name' => __('Physics'), 'percent' => 82],
            ['name' => __('Chemistry'), 'percent' => 64],
            ['name' => __('Mathematics'), 'percent' => 47],
            ['name' => __('Biology'), 'percent' => 71],
        ];
    }
}
