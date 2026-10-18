<?php

namespace App\Filament\Staff\Resources\BoardQuestionPapers\Pages;

use App\Filament\Staff\Resources\BoardQuestionPapers\BoardQuestionPaperResource;
use App\Filament\Support\Concerns\HandlesBoardQuestionPaperForm;
use Filament\Resources\Pages\CreateRecord;

class CreateBoardQuestionPaper extends CreateRecord
{
    use HandlesBoardQuestionPaperForm;

    protected static string $resource = BoardQuestionPaperResource::class;

    /**
     * A new paper opens on its own page, where its questions are added.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
