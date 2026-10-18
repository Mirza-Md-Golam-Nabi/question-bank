<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GenerationMode: string implements HasLabel
{
    case Manual = 'manual';
    case Auto = 'auto';
    case Mistakes = 'mistakes';
    case PastPaper = 'past_paper';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Manual => __('Manual Selection'),
            self::Auto => __('Auto-Generate'),
            self::Mistakes => __('Practise my mistakes'),
            self::PastPaper => __('Past paper'),
        };
    }
}
