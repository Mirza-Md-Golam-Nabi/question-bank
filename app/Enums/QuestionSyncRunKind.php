<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What one fetch from the live server brings: a chapter's questions, or a
 * whole past paper.
 */
enum QuestionSyncRunKind: string implements HasLabel
{
    case Chapter = 'chapter';
    case Paper = 'paper';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Chapter => __('Chapter'),
            self::Paper => __('Past paper'),
        };
    }
}
