<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExamAttemptStatus: string implements HasLabel
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case AutoSubmitted = 'auto_submitted';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::InProgress => __('In Progress'),
            self::Submitted => __('Submitted'),
            self::AutoSubmitted => __('Auto Submitted'),
        };
    }
}
