<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of bell notifications Admins get — for each the Admin settings
 * say which Admins (BillingSetting::adminsToNotify()). A new kind of Admin
 * notification is one more case here.
 */
enum AdminNotificationTopic: string implements HasDescription, HasLabel
{
    case Purchases = 'purchases';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Purchases => __('Subscription purchases'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Purchases => __('A Teacher or Student asks for a plan (to check their payment), or buys one with wallet credit.'),
        };
    }
}
