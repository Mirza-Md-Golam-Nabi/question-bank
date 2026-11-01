<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an exam made of a past paper marks a question the paper itself says
 * has no correct answer (CLAUDE.md rule 5.7): the teacher setting the exam
 * chooses; a student's own practice always leaves it out.
 */
enum NoAnswerMarking: string implements HasLabel
{
    case Excluded = 'excluded';
    case FullMarks = 'full_marks';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Excluded => __('Leave them out of the total marks'),
            self::FullMarks => __('Give everyone full marks for them'),
        };
    }
}
