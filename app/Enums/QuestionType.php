<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasLabel
{
    case Mcq = 'mcq';
    case Cq = 'cq';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Mcq => 'MCQ',
            self::Cq => 'CQ (সৃজনশীল)',
        };
    }
}
