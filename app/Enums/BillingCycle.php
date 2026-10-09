<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum BillingCycle: string implements HasLabel
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Free = 'free';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Yearly => __('Yearly'),
            self::Free => __('Free'),
        };
    }

    /**
     * When a subscription period that begins at `$start` runs out; null
     * for a cycle that never does.
     */
    public function periodEndFrom(CarbonInterface $start): ?CarbonInterface
    {
        return match ($this) {
            self::Monthly => $start->copy()->addMonthNoOverflow(),
            self::Yearly => $start->copy()->addYearNoOverflow(),
            self::Free => null,
        };
    }
}
