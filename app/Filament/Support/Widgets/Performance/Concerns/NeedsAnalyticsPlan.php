<?php

namespace App\Filament\Support\Widgets\Performance\Concerns;

use App\Services\SubscriptionLimitService;
use Illuminate\Support\Facades\Auth;

/**
 * An analytics widget: shown only to a user whose paid plan includes the
 * analytics (CLAUDE.md rule 11). Everyone else sees the locked preview in
 * its place (AnalyticsLockedWidget), and nothing is worked out for them.
 */
trait NeedsAnalyticsPlan
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null && app(SubscriptionLimitService::class)->allowsAnalytics($user);
    }
}
