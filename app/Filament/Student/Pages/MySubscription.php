<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\MySubscriptionPage;
use Filament\Support\Icons\Heroicon;

class MySubscription extends MySubscriptionPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Account;

    protected static ?int $navigationSort = 2;
}
