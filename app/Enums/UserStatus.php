<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Suspended = 'suspended';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}
