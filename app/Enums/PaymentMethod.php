<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case MobileBanking = 'mobile_banking';
    case Bank = 'bank';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MobileBanking => 'Mobile Banking',
            self::Bank => 'Bank',
        };
    }
}
