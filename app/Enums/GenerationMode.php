<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GenerationMode: string implements HasLabel
{
    case Manual = 'manual';
    case Auto = 'auto';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Manual => 'Manual Selection',
            self::Auto => 'Auto-Generate',
        };
    }
}
