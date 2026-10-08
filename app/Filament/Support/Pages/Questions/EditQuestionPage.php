<?php

namespace App\Filament\Support\Pages\Questions;

use App\Filament\Support\Concerns\HandlesQuestionForm;
use App\Models\Question;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * The "edit question" page of every panel's QuestionResource — see
 * {@see CreateQuestionPage}. Editing an approved question never changes it
 * in place: it is routed to a new pending revision (CLAUDE.md rule 4).
 */
abstract class EditQuestionPage extends EditRecord
{
    use HandlesQuestionForm;

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
        $this->rememberEditorModePreference($data);

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
