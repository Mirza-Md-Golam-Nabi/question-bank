<?php

namespace App\Filament\Support;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case QuestionBank = 'Question Bank';
    case People = 'People';
    case Payroll = 'Payroll';
    case Billing = 'Billing';
    case Exams = 'Exams';

    /**
     * The menu heading in the active language. Each label is written out
     * here so the translation check (LocaleSwitchTest) can see it.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::QuestionBank => __('Question Bank'),
            self::People => __('People'),
            self::Payroll => __('Payroll'),
            self::Billing => __('Billing'),
            self::Exams => __('Exams'),
        };
    }
}
