<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionTargetRole: string implements HasLabel
{
    case Teacher = 'teacher';
    case Student = 'student';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Teacher => 'Teacher',
            self::Student => 'Student',
        };
    }
}
