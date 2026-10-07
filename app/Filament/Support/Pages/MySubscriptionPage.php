<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\Subscription;
use App\Services\SubscriptionLimitService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by the Teacher and Student panels' "My Subscription" page — same
 * "current plan + usage" view for both roles, just backed by whichever
 * user is logged into that panel.
 */
abstract class MySubscriptionPage extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.my-subscription';

    public function subscription(): ?Subscription
    {
        return app(SubscriptionLimitService::class)->activeSubscriptionFor(Auth::user());
    }

    public function monthlyLimit(): ?int
    {
        return app(SubscriptionLimitService::class)->monthlyExamLimitFor(Auth::user());
    }
}
