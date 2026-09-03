<?php

namespace App\Filament\Resources\BoardQuestionPapers\Pages;

use App\Filament\Resources\BoardQuestionPapers\BoardQuestionPaperResource;
use App\Filament\Support\Concerns\HandlesBoardQuestionPaperForm;
use App\Models\BoardQuestionPaper;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBoardQuestionPaper extends EditRecord
{
    use HandlesBoardQuestionPaperForm;

    protected static string $resource = BoardQuestionPaperResource::class;

    protected ?array $mcqQuestionsData = null;

    protected ?array $cqQuestionsData = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$this->mcqQuestionsData, $this->cqQuestionsData] = $this->extractQuestionsData($data);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BoardQuestionPaper $record */
        $record->update($data);

        $this->syncQuestions($record, $this->mcqQuestionsData ?? [], $this->cqQuestionsData ?? []);

        return $record;
    }
}
