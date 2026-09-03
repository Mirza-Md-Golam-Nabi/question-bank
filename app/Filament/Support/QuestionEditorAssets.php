<?php

namespace App\Filament\Support;

use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

/**
 * Loads the JS this project's question content needs, panel-wide, via a head
 * render hook — split into two Vite entries so a panel only pays for what it
 * uses:
 *  - resources/js/ckeditor-question-editor.js: the full self-hosted
 *    CKEditor 5 build (~1MB) — panels whose QuestionResource offers the
 *    CKEditor `editor_mode` (Admin/Teacher/Staff).
 *  - resources/js/katex-embed-renderer.js: just KaTeX, to render already-
 *    authored `.qb-katex-embed` spans read-only — panels that only display
 *    question content (Student), never edit it.
 */
class QuestionEditorAssets
{
    public static function registerOn(Panel $panel): Panel
    {
        return $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render("@vite(['resources/js/ckeditor-question-editor.js'])"),
        );
    }

    public static function registerKatexRendererOn(Panel $panel): Panel
    {
        return $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render("@vite(['resources/js/katex-embed-renderer.js'])"),
        );
    }
}
