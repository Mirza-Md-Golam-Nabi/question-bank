<?php

namespace App\Filament\Support\Concerns;

use App\Services\PlanGrant;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Facades\Auth;

/**
 * For a page or widget that shows the signed-in user "your plans": every
 * active subscription and the free plan, each with how much of its daily
 * and monthly allowance is used. A view should call planSummary() once
 * and hand it to <x-plan-usage>.
 */
trait ShowsPlanSummary
{
    /**
     * @return array<int, array{grant: PlanGrant, today: int, month: int}>
     */
    public function planSummary(): array
    {
        return once(fn (): array => app(SubscriptionLimitService::class)->summaryFor(Auth::user()));
    }
}
