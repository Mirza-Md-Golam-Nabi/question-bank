<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

/**
 * How long a subscription to a plan lasts: a number of days, months or
 * years from the purchase, until a fixed date the Admin sets (an "until the
 * board exam" plan), or for good (the free plan).
 */
enum PlanDuration: string implements HasLabel
{
    case Days = 'days';
    case Months = 'months';
    case Years = 'years';
    case UntilDate = 'until_date';
    case Unlimited = 'unlimited';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Days => __('Days'),
            self::Months => __('Months'),
            self::Years => __('Years'),
            self::UntilDate => __('Until a fixed date'),
            self::Unlimited => __('No end'),
        };
    }

    public function needsLength(): bool
    {
        return in_array($this, [self::Days, self::Months, self::Years], true);
    }

    /**
     * When a period of `$length` units beginning at `$start` runs out; null
     * for one that never does.
     */
    public function periodEndFrom(CarbonInterface $start, ?int $length, ?CarbonInterface $endsOn): ?CarbonInterface
    {
        return match ($this) {
            self::Days => $start->copy()->addDays($length ?? 1),
            self::Months => $start->copy()->addMonthsNoOverflow($length ?? 1),
            self::Years => $start->copy()->addYearsNoOverflow($length ?? 1),
            self::UntilDate => $endsOn?->copy()->endOfDay(),
            self::Unlimited => null,
        };
    }
}
