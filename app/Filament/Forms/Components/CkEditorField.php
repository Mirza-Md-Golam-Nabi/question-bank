<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Concerns\CanBeCompact;

/**
 * Wraps the self-hosted CKEditor 5 build (resources/js/ckeditor-question-editor.js)
 * — math-equation widget included — as a Filament field. Alternative to the
 * native RichEditor, toggled per-question via `editor_mode`; stores plain
 * HTML with `<span class="qb-katex-embed">latex</span>` for equations.
 *
 * ->compact() (Filament's own Section/Grid trait, reused here) trims the
 * toolbar to bold/italic/math and shrinks the editing area — for spots like
 * an MCQ option where a full toolbar would overwhelm a one-line field.
 */
class CkEditorField extends Field
{
    use CanBeCompact;

    protected string $view = 'filament.forms.components.ck-editor-field';
}
