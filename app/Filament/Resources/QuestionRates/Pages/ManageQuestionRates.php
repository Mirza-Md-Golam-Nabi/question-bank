<?php

namespace App\Filament\Resources\QuestionRates\Pages;

use App\Filament\Resources\QuestionRates\QuestionRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageQuestionRates extends ManageRecords
{
    protected static string $resource = QuestionRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
