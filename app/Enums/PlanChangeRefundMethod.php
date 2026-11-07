<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the Admin gave back the money owed for a change to a cheaper plan:
 * into the user's wallet (Teacher/Student only), or sent by bKash/Nagad
 * outside the system.
 */
enum PlanChangeRefundMethod: string implements HasLabel
{
    case Wallet = 'wallet';
    case MobileBanking = 'mobile_banking';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Wallet => __('Into their wallet'),
            self::MobileBanking => __('Sent by bKash/Nagad'),
        };
    }
}
