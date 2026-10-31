<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * An MCQ that may stand under a shared stimulus (`stimulus_id`, and its
 * place under it, `stimulus_order`) — the question bank's Question and a
 * past paper's BoardMcqQuestion alike, so the views and the exam treat
 * both the same way (App\Filament\Support\QuestionBlocks). The model
 * itself declares its `stimulus()` relation, to its own kind of stimulus.
 */
trait SharesStimulus
{
    public function isInStimulusGroup(): bool
    {
        return $this->stimulus_id !== null;
    }

    /**
     * The order QuestionBlocks lays questions out in, in the database, for a
     * list that pages through them: by the given column, a stimulus's
     * questions together, in their own order, at the place the first of them
     * comes.
     */
    public function scopeStimulusTogether(Builder $query, string $column, string $direction = 'asc'): Builder
    {
        $table = $this->getTable();
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw("COALESCE((SELECT MIN(siblings.{$column}) FROM {$table} AS siblings WHERE siblings.stimulus_id = {$table}.stimulus_id), {$table}.{$column}) {$direction}")
            ->orderBy("{$table}.stimulus_id")
            ->orderBy("{$table}.stimulus_order")
            ->orderBy("{$table}.id");
    }
}
