<?php

namespace App\Models\Concerns;

use App\Enums\EditorMode;

/**
 * What a stimulus is, wherever it lives (the question bank's
 * QuestionStimulus, a past paper's BoardMcqStimulus): a text written in
 * one of the question editors, and an optional image — shown once above
 * the MCQs that share it (`questions`, in their order).
 */
trait IsStimulus
{
    protected function casts(): array
    {
        return [
            'editor_mode' => EditorMode::class,
        ];
    }
}
