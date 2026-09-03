<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EditorMode: string implements HasLabel
{
    case RichText = 'richtext';
    case CkEditor = 'ckeditor';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RichText => 'Rich Text',
            self::CkEditor => 'CKEditor (গণিত সহ)',
        };
    }
}
