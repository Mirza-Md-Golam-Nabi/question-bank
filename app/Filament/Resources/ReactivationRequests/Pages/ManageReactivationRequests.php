<?php

namespace App\Filament\Resources\ReactivationRequests\Pages;

use App\Filament\Resources\ReactivationRequests\ReactivationRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReactivationRequests extends ManageRecords
{
    protected static string $resource = ReactivationRequestResource::class;

    // No CreateAction — a request only comes from the suspended user.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
