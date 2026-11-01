<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Locale: string implements HasLabel
{
    case Bn = 'bn';
    case En = 'en';

    public static function current(): self
    {
        return self::tryFrom(app()->getLocale()) ?? self::En;
    }

    /**
     * The text with its digits written the way this language writes them —
     * Carbon translates a date's month and day names, but not its numbers.
     */
    public function writeDigits(string $text): string
    {
        return match ($this) {
            self::Bn => strtr($text, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']),
            self::En => $text,
        };
    }

    /**
     * What someone typed, with Bangla digits as the English ones the
     * database holds.
     */
    public static function toEnglishDigits(string $text): string
    {
        return strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    /**
     * Each language is always shown in its own script, whatever the active
     * locale is, so a visitor can find their language without reading the
     * other one.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Bn => 'বাংলা',
            self::En => 'English',
        };
    }
}
