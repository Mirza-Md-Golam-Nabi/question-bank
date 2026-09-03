<?php

namespace App\Filament\Resources\Staffs\Pages;

use App\Filament\Resources\Staffs\StaffResource;
use Filament\Resources\Pages\ManageRecords;

class ManageStaffs extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    // No CreateAction — Staff accounts only come from Google sign-in.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
