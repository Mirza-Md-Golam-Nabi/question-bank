<?php

namespace App\Filament\Resources\BoardQuestionPapers\Pages;

use App\Filament\Resources\BoardQuestionPapers\BoardQuestionPaperResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBoardQuestionPapers extends ListRecords
{
    protected static string $resource = BoardQuestionPaperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
