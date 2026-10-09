<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a wallet ledger row records. Referral rewards are not rows here:
 * they live in `referral_rewards` and count as credit once matured.
 */
enum WalletTransactionType: string implements HasLabel
{
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Purchase => __('Spent on subscription'),
            self::PurchaseReturn => __('Returned from a cancelled purchase'),
        };
    }
}
