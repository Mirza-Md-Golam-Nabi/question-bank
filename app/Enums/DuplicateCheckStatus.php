<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a question is in the background duplicate check
 * (App\Services\QuestionDuplicateScreening): only a clear one goes into the
 * pool or onto an exam, or is approved.
 */
enum DuplicateCheckStatus: string implements HasLabel
{
    case Checking = 'checking';
    case Clear = 'clear';
    case Suspected = 'suspected';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Checking => __('Checking for duplicates'),
            self::Clear => __('No duplicate found'),
            self::Suspected => __('Possible duplicate — the Admin is checking it'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Checking => 'gray',
            self::Clear => 'success',
            self::Suspected => 'danger',
        };
    }
}
