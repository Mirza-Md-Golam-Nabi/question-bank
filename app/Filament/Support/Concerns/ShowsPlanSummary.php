<?php

namespace App\Filament\Support\Concerns;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Facades\Auth;

/**
 * For a page or widget that shows the signed-in user "your plan": the
 * subscription, the plan whose limits apply and the monthly limit, read
 * together. A view should call planSummary() once and use its parts.
 */
trait ShowsPlanSummary
{
    /**
     * @return array{subscription: ?Subscription, plan: ?SubscriptionPlan, monthly_limit: ?int}
     */
    public function planSummary(): array
    {
        return app(SubscriptionLimitService::class)->planSummaryFor(Auth::user());
    }
}
