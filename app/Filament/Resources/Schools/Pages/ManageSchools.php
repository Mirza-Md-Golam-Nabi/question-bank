<?php

namespace App\Filament\Resources\Schools\Pages;

use App\Filament\Resources\Schools\SchoolResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSchools extends ManageRecords
{
    protected static string $resource = SchoolResource::class;

    // No CreateAction — School accounts only come from Google sign-in.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
