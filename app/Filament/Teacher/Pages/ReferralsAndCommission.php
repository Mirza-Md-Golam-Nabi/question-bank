<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Support\Pages\ReferralsAndCommissionPage;

class ReferralsAndCommission extends ReferralsAndCommissionPage
{
    public function subscriptionUrl(): string
    {
        return MySubscription::getUrl();
    }
}
