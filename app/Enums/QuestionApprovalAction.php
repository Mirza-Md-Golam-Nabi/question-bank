<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum QuestionApprovalAction: string implements HasLabel
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Resubmitted = 'resubmitted';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Resubmitted => __('Resubmitted'),
        };
    }
}
