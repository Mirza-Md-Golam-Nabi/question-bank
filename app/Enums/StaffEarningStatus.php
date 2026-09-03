<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StaffEarningStatus: string implements HasLabel
{
    case PendingPayout = 'pending_payout';
    case Paid = 'paid';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingPayout => 'Pending Payout',
            self::Paid => 'Paid',
        };
    }
}
