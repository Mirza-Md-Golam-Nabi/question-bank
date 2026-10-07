<?php

namespace App\Filament\Support\Concerns;

use Illuminate\Support\Str;

use function Filament\Support\get_model_label;

/**
 * Runs a Resource's model/navigation labels through the translator. The
 * English label is always the translation key (e.g. "subject", "subjects",
 * "Subjects"), so pluralisation happens in English before translating —
 * Filament's own Str::plural() would otherwise mangle a Bangla label.
 */
trait TranslatesResourceLabels
{
    public static function getModelLabel(): string
    {
        return __(static::getEnglishModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::getEnglishPluralModelLabel());
    }

    public static function getNavigationLabel(): string
    {
        return __(static::$navigationLabel ?? Str::ucwords(static::getEnglishPluralModelLabel()));
    }

    protected static function getEnglishModelLabel(): string
    {
        return static::$modelLabel ?? get_model_label(static::getModel());
    }

    protected static function getEnglishPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? Str::plural(static::getEnglishModelLabel());
    }
}
