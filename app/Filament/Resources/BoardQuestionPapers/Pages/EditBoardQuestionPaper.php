<?php

namespace App\Filament\Resources\BoardQuestionPapers\Pages;

use App\Filament\Resources\BoardQuestionPapers\BoardQuestionPaperResource;
use App\Filament\Support\Concerns\HandlesBoardQuestionPaperForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBoardQuestionPaper extends EditRecord
{
    use HandlesBoardQuestionPaperForm;

    protected static string $resource = BoardQuestionPaperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
