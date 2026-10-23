<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The sections of the panels' "Help" page a guide is filed under, in the
 * order they are shown.
 */
enum GuideCategory: string implements HasLabel
{
    case GettingStarted = 'getting_started';
    case Subscription = 'subscription';
    case Questions = 'questions';
    case Exams = 'exams';
    case Practice = 'practice';
    case Earnings = 'earnings';
    case School = 'school';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::GettingStarted => __('Getting started'),
            self::Subscription => __('Plans and payment'),
            self::Questions => __('Writing questions'),
            self::Exams => __('Exams'),
            self::Practice => __('Practice'),
            self::Earnings => __('Earnings and withdrawals'),
            self::School => __('School and school software'),
        };
    }
}
