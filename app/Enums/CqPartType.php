<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CqPartType: string implements HasLabel
{
    case Knowledge = 'knowledge';
    case Comprehension = 'comprehension';
    case Application = 'application';
    case HigherApplication = 'higher_application';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Knowledge => 'জ্ঞানমূলক',
            self::Comprehension => 'অনুধাবনমূলক',
            self::Application => 'প্রয়োগ',
            self::HigherApplication => 'উচ্চতর দক্ষতা',
        };
    }

    /**
     * Fixed display/entry order for the 4 CQ sub-questions.
     *
     * @return array<self>
     */
    public static function ordered(): array
    {
        return [self::Knowledge, self::Comprehension, self::Application, self::HigherApplication];
    }

    public function order(): int
    {
        return array_search($this, self::ordered(), strict: true) + 1;
    }
}
