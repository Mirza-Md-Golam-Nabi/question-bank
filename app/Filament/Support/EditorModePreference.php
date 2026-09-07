<?php

namespace App\Filament\Support;

use App\Enums\EditorMode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers which editor (Rich Text vs CKEditor) a user picked the last
 * time they saved a question, so their next Create Question form starts on
 * that same editor instead of always defaulting back to Rich Text — shared
 * by every panel's QuestionFormSchema/HandlesQuestionForm since the
 * preference is per-user, not per-panel.
 */
class EditorModePreference
{
    public static function remember(User $user, EditorMode $mode): void
    {
        Cache::forever(static::cacheKey($user), $mode->value);
    }

    public static function for(User $user): EditorMode
    {
        return EditorMode::tryFrom(Cache::get(static::cacheKey($user)) ?? '') ?? EditorMode::RichText;
    }

    protected static function cacheKey(User $user): string
    {
        return "question-editor-mode:{$user->id}";
    }
}
