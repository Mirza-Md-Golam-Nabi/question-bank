<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What kind of past paper a source (a `boards` row) gives: an education
 * board's, an admission test's (medical, university), a job exam's, or a
 * school/college's own test exam.
 */
enum PaperSourceType: string implements HasColor, HasLabel
{
    case Board = 'board';
    case Medical = 'medical';
    case University = 'university';
    case Job = 'job';
    case SchoolTest = 'school_test';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Board => __('Education board'),
            self::Medical => __('Medical admission'),
            self::University => __('University admission'),
            self::Job => __('Job exam'),
            self::SchoolTest => __('School/college test exam'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Board => 'primary',
            self::Medical => 'danger',
            self::University => 'info',
            self::Job => 'warning',
            self::SchoolTest => 'gray',
        };
    }
}
