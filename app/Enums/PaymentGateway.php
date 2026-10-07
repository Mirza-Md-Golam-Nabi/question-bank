<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentGateway: string implements HasLabel
{
    case Sslcommerz = 'sslcommerz';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Manual = 'manual';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Sslcommerz => __('SSLCommerz'),
            self::Bkash => __('bKash'),
            self::Nagad => __('Nagad'),
            self::Manual => __('Manual'),
        };
    }
}
