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
            self::Knowledge => __('Knowledge'),
            self::Comprehension => __('Comprehension'),
            self::Application => __('Application'),
            self::HigherApplication => __('Higher Ability'),
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
