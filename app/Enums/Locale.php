<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Locale: string implements HasLabel
{
    case Bn = 'bn';
    case En = 'en';

    /**
     * Each language is always shown in its own script, whatever the active
     * locale is, so a visitor can find their language without reading the
     * other one.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Bn => 'বাংলা',
            self::En => 'English',
        };
    }
}
