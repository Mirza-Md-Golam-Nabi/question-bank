<?php

namespace App\Observers;

use App\Enums\QuestionStatus;
use App\Models\BoardQuestionPaper;

class BoardQuestionPaperObserver
{
    public function creating(BoardQuestionPaper $paper): void
    {
        if (! $paper->created_by) {
            $paper->created_by = auth()->id();
        }

        $paper->status ??= QuestionStatus::Pending;
    }
}
