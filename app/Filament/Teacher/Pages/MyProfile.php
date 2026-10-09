<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Support\Pages\MyProfilePage;
use Filament\Support\Icons\Heroicon;

class MyProfile extends MyProfilePage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;
}
