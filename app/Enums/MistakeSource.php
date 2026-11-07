<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which of a subject's mistakes the student practises: all of them, only
 * the question bank's (which go by chapter and topic) or only the past
 * papers' (which go by year).
 */
enum MistakeSource: string implements HasLabel
{
    case All = 'all';
    case QuestionBank = 'question_bank';
    case PastPapers = 'past_papers';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::All => __('All mistakes'),
            self::QuestionBank => __('Question bank'),
            self::PastPapers => __('Past papers'),
        };
    }
}
