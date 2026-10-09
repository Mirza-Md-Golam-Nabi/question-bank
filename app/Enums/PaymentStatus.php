<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Success => __('Success'),
            self::Failed => __('Failed'),
            self::Rejected => __('Rejected'),
            self::Refunded => __('Refunded'),
        };
    }
}
