<?php

namespace App\Filament\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Small presentation helpers shared by every read-only rendering of a
 * question (the Teacher's question picker and the printable question
 * paper), so option lettering and image URLs are decided in one place.
 */
class QuestionDisplay
{
    private const BANGLA_LETTERS = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ'];

    /**
     * The letter in front of an MCQ option or CQ sub-question: ক, খ, গ… in
     * Bangla, A, B, C… otherwise.
     */
    public static function letter(int $index): string
    {
        if (app()->getLocale() === 'bn') {
            return self::BANGLA_LETTERS[$index] ?? (string) ($index + 1);
        }

        return $index < 26 ? chr(ord('A') + $index) : (string) ($index + 1);
    }

    public static function imageUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Whole marks read better without a trailing ".00" (5, not 5.00), while
     * a half mark still shows (2.5).
     */
    public static function marks(float|int|string|null $marks): string
    {
        return rtrim(rtrim(number_format((float) $marks, 2, '.', ''), '0'), '.');
    }
}
