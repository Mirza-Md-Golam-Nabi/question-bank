<?php

namespace App\Filament\Support\Pages\Questions;

use App\Filament\Support\Concerns\HandlesQuestionForm;
use App\Filament\Support\Concerns\PrefillsChapterFromQuery;
use App\Models\Question;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * The "create question" page of every panel's QuestionResource. Creating a
 * question works the same for an Admin, a Teacher and a Staff member — what
 * differs per panel (who may create, and the status the question starts in)
 * is decided by QuestionPolicy and QuestionObserver, not here — so each
 * panel's page only names its resource.
 */
abstract class CreateQuestionPage extends CreateRecord
{
    use HandlesQuestionForm, PrefillsChapterFromQuery;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->cqPartsData = $this->extractCqPartsData($data);
        $this->normalizeMcqOptions($data);
        $this->rememberEditorModePreference($data);
        $this->rememberTopicPreference($data);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Question $record */
        $record = $this->getModel()::create($data);

        $this->syncCqParts($record);

        return $record;
    }
}
