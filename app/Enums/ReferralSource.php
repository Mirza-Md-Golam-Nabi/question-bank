<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a student came to be under a referrer. A code the student typed (or
 * a referral link they signed up through) can still be swapped for a short
 * while; one set by opening a teacher's exam link is fixed at once.
 */
enum ReferralSource: string implements HasLabel
{
    case Code = 'code';
    case Link = 'link';
    case Exam = 'exam';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Code => __('Referral code'),
            self::Link => __('Referral link'),
            self::Exam => __('Exam link'),
        };
    }
}
