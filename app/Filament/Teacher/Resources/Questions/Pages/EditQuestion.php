<?php

namespace App\Filament\Teacher\Resources\Questions\Pages;

use App\Filament\Support\Concerns\HandlesQuestionForm;
use App\Filament\Teacher\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQuestion extends EditRecord
{
    use HandlesQuestionForm;

    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->cqPartsData = $this->extractCqPartsData($data);
        $this->normalizeMcqOptions($data);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Question $record */
        if ($this->isEditingAnApprovedQuestion($record)) {
            return $this->handleApprovedQuestionEdit($record, $data);
        }

        $record->update($data);
        $this->syncCqParts($record);

        return $record;
    }
}
