<?php

namespace App\Filament\Staff\Pages;

use App\Filament\Support\Pages\MyProfilePage;
use Filament\Support\Icons\Heroicon;

class MyProfile extends MyProfilePage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    /**
     * Staff have no subscription or referral pages to group it with.
     */
    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = null;

    public function earningsUrl(): string
    {
        return MyEarnings::getUrl();
    }
}
