<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Pages\MyProfilePage;
use Filament\Support\Icons\Heroicon;

class MyProfile extends MyProfilePage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;
}
