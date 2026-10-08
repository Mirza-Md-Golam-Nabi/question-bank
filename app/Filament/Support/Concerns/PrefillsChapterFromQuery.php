<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\TopicPreference;
use App\Models\Chapter;

/**
 * Shared by every panel's CreateQuestion page. When arriving from a
 * Chapter card's "Add question" link (`?chapter=`), pre-selects the
 * Class → Subject → Chapter chain (plus the topic this user last used in
 * that chapter) instead of leaving the cascading selects empty.
 */
trait PrefillsChapterFromQuery
{
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
            'topic_id' => TopicPreference::for(auth()->user(), $chapter->id),
        ];
    }
}
