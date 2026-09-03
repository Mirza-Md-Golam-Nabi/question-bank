<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTeachers extends ManageRecords
{
    protected static string $resource = TeacherResource::class;

    // No CreateAction — Teacher accounts only come from Google sign-in.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
