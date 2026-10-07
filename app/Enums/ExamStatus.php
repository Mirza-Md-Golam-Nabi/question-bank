<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExamStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Ongoing = 'ongoing';
    case Completed = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Ongoing => __('Ongoing'),
            self::Completed => __('Completed'),
        };
    }
}
