<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Concerns\HandlesQuestionForm;
use App\Models\Chapter;
use App\Models\Question;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuestion extends CreateRecord
{
    use HandlesQuestionForm;

    protected static string $resource = QuestionResource::class;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        // `fill()` only applies component ->default() values when given no
        // explicit state (or null) — handing it the chapter chain directly
        // would skip every other field's default (question_type, difficulty,
        // marks). So fill defaults normally first, then overlay just the
        // chapter-derived selection with fillPartially(), which merges
        // instead of replacing the whole form state.
        $this->form->fill();

        if ($defaults = $this->chapterDefaults()) {
            $this->form->fillPartially($defaults, array_keys($defaults));
        }

        $this->callHook('afterFill');
    }

    /**
     * When arriving from a Chapter card's "Add question" link (`?chapter=`),
     * pre-selects the Class → Subject → Chapter chain instead of leaving the
     * cascading selects empty.
     *
     * @return array<string, mixed>|null
     */
    protected function chapterDefaults(): ?array
    {
        $chapter = Chapter::with('classSubject')->find(request()->query('chapter'));

        if (! $chapter) {
            return null;
        }

        return [
            'academic_class_id' => $chapter->classSubject->academic_class_id,
            'class_subject_id' => $chapter->classSubject->id,
            'chapter_id' => $chapter->id,
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->cqPartsData = $this->extractCqPartsData($data);
        $this->normalizeMcqOptions($data);

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
