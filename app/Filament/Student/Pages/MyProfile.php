<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\MyProfilePage;
use Filament\Support\Icons\Heroicon;

class MyProfile extends MyProfilePage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Account;

    protected static ?int $navigationSort = 1;

    public function earningsUrl(): string
    {
        return ReferralsAndCommission::getUrl();
    }
}
