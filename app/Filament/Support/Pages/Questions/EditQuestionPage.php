<?php

namespace App\Filament\Support\Pages\Questions;

use App\Filament\Support\Concerns\HandlesQuestionForm;
use App\Models\Question;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
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

    /**
     * A question under a shared stimulus is only edited with its group (from
     * the question list), never on its own here.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Question $question */
        $question = $this->getRecord();

        if ($question->isInStimulusGroup()) {
            Notification::make()->title(__('Edit this question together with its stimulus, from the question list.'))->warning()->send();

            $this->redirect(static::getResource()::getUrl('list', ['chapter' => $question->chapter_id]));
        }
    }

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
        if ($record->editsMakeNewVersion()) {
            return $this->handleEditAsNewVersion($record, $data);
        }

        $record->update($data);
        $this->syncCqParts($record);

        return $record;
    }
}
