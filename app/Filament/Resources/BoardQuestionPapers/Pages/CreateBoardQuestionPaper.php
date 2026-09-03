<?php

namespace App\Filament\Resources\BoardQuestionPapers\Pages;

use App\Filament\Resources\BoardQuestionPapers\BoardQuestionPaperResource;
use App\Filament\Support\Concerns\HandlesBoardQuestionPaperForm;
use App\Models\BoardQuestionPaper;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBoardQuestionPaper extends CreateRecord
{
    use HandlesBoardQuestionPaperForm;

    protected static string $resource = BoardQuestionPaperResource::class;

    protected ?array $mcqQuestionsData = null;

    protected ?array $cqQuestionsData = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$this->mcqQuestionsData, $this->cqQuestionsData] = $this->extractQuestionsData($data);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var BoardQuestionPaper $record */
        $record = $this->getModel()::create($data);

        $this->syncQuestions($record, $this->mcqQuestionsData ?? [], $this->cqQuestionsData ?? []);

        return $record;
    }
}
