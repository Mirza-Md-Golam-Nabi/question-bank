<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a payment is for: buying a new subscription, changing a running
 * one to another plan (paying the difference, or getting some back), or
 * paying for an extra institution name printed on question papers.
 */
enum PaymentType: string implements HasLabel
{
    case Purchase = 'purchase';
    case PlanChange = 'plan_change';
    case InstitutionName = 'institution_name';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Purchase => __('Purchase'),
            self::PlanChange => __('Plan change'),
            self::InstitutionName => __('Institution name'),
        };
    }
}
