<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a referral reward stands. Never stored — ReferralReward::status()
 * works it out from the reward's dates, so nothing has to run on a
 * schedule to move a reward from one state to the next.
 */
enum ReferralRewardStatus: string implements HasLabel
{
    case Maturing = 'maturing';
    case Available = 'available';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Maturing => __('Waiting for the refund period to end'),
            self::Available => __('Available'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled (payment refunded)'),
        };
    }
}
