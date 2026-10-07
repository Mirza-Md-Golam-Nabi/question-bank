<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MobileBankingProvider: string implements HasLabel
{
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Bkash => __('bKash'),
            self::Nagad => __('Nagad'),
            self::Rocket => __('Rocket'),
        };
    }
}
