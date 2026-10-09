<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Support\Pages\RequestReactivationPage;
use Filament\Support\Icons\Heroicon;

class RequestReactivation extends RequestReactivationPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;
}
