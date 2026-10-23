<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionTargetRole: string implements HasLabel
{
    case Teacher = 'teacher';
    case Student = 'student';
    case School = 'school';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Teacher => __('Teacher'),
            self::Student => __('Student'),
            self::School => __('School'),
        };
    }
}
