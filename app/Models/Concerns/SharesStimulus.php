<?php

namespace App\Models\Concerns;

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
}
