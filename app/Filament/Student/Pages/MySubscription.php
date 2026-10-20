<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Pages\MySubscriptionPage;
use Filament\Support\Icons\Heroicon;

class MySubscription extends MySubscriptionPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;
}
