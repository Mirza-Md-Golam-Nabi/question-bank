<?php

namespace App\Filament\Staff\Resources\BoardQuestionPapers\Pages;

use App\Filament\Staff\Resources\BoardQuestionPapers\BoardQuestionPaperResource;
use App\Filament\Support\Concerns\CreatesPapersForSources;
use App\Filament\Support\Concerns\HandlesBoardQuestionPaperForm;
use Filament\Resources\Pages\CreateRecord;

class CreateBoardQuestionPaper extends CreateRecord
{
    use CreatesPapersForSources;
    use HandlesBoardQuestionPaperForm;

    protected static string $resource = BoardQuestionPaperResource::class;
}
