<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StaffPayoutStatus: string implements HasLabel
{
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }
}
