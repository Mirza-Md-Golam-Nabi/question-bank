<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case PermanentSuspend = 'permanent_suspend';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::PermanentSuspend => 'Permanent Suspend',
        };
    }
}
