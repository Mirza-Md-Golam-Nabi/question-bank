<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\ReferralsAndCommissionPage;

class ReferralsAndCommission extends ReferralsAndCommissionPage
{
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Account;

    protected static ?int $navigationSort = 3;

    public function subscriptionUrl(): string
    {
        return MySubscription::getUrl();
    }
}
