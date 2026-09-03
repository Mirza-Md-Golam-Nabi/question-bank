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

    public function getLabel(): ?string
    {
        return $this->value;
    }
}
