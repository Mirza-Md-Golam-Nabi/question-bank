<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Pages\ReferralsAndCommissionPage;

class ReferralsAndCommission extends ReferralsAndCommissionPage
{
    public function subscriptionUrl(): string
    {
        return MySubscription::getUrl();
    }
}
