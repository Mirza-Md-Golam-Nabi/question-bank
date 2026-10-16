<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a plan can switch on beyond which questions it reaches: taking a
 * teacher's exam online through a share link, and the printable paper.
 */
enum PlanFeature: string implements HasLabel
{
    case OnlineExam = 'online_exam';
    case Print = 'print';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OnlineExam => __('Online exam (share link)'),
            self::Print => __('Print / PDF'),
        };
    }
}
