<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a payment is for: buying a new subscription, or changing a running
 * one to another plan (paying the difference, or getting some back).
 */
enum PaymentType: string implements HasLabel
{
    case Purchase = 'purchase';
    case PlanChange = 'plan_change';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Purchase => __('Purchase'),
            self::PlanChange => __('Plan change'),
        };
    }
}
