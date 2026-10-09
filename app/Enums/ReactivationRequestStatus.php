<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReactivationRequestStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Declined => __('Declined'),
        };
    }
}
