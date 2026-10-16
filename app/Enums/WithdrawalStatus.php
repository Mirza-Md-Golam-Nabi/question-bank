<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a request to take referral earnings out as cash stands.
 */
enum WithdrawalStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Requested => __('Waiting for payment'),
            self::Paid => __('Paid'),
            self::Rejected => __('Rejected'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Paid => 'success',
            self::Rejected => 'danger',
        };
    }
}
