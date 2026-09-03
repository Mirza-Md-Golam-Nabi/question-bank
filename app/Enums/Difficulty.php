<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Difficulty: string implements HasLabel
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Easy => 'Easy',
            self::Medium => 'Medium',
            self::Hard => 'Hard',
        };
    }
}
