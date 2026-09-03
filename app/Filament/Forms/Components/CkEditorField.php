<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Wraps the self-hosted CKEditor 5 build (resources/js/ckeditor-question-editor.js)
 * — math-equation widget included — as a Filament field. Alternative to the
 * native RichEditor, toggled per-question via `editor_mode`; stores plain
 * HTML with `<span class="qb-katex-embed">latex</span>` for equations.
 */
class CkEditorField extends Field
{
    protected string $view = 'filament.forms.components.ck-editor-field';
}
