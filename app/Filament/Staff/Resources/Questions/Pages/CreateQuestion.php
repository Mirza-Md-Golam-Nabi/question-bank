<?php

namespace App\Filament\Staff\Resources\Questions\Pages;

use App\Filament\Staff\Resources\Questions\QuestionResource;
use App\Filament\Support\Concerns\HandlesQuestionForm;
use App\Filament\Support\Concerns\PrefillsChapterFromQuery;
use App\Models\Question;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuestion extends CreateRecord
{
    use HandlesQuestionForm, PrefillsChapterFromQuery;

    protected static string $resource = QuestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->cqPartsData = $this->extractCqPartsData($data);
        $this->normalizeMcqOptions($data);
        $this->rememberEditorModePreference($data);

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
