<?php

namespace App\Filament\Support\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Runs a custom Page's title and navigation label through the translator,
 * using the English text (the static property or the class-name default) as
 * the translation key.
 */
trait TranslatesPageLabels
{
    public function getTitle(): string|Htmlable
    {
        $title = parent::getTitle();

        return is_string($title) ? __($title) : $title;
    }

    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }
}
