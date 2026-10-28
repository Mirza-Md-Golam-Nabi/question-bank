<?php

namespace App\Filament\Support;

use App\Enums\EditorMode;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers which editor (Rich Text vs CKEditor) a user picked the last
 * time they saved a question — for its text and, separately, for an MCQ's
 * explanation — so their next Create Question form starts on those same
 * editors instead of always defaulting back to Rich Text. Shared by every
 * panel's QuestionFormSchema/HandlesQuestionForm since the preference is
 * per-user, not per-panel.
 */
class EditorModePreference
{
    /**
     * Remembers the editors a saved form used: its `editor_mode` as the form
     * hands it over (the enum, or its value), and the explanation's editor
     * of the last question in it whose explanation is written — the form's
     * own, or one of a stimulus group's `questions`. Nothing is remembered
     * for what the form leaves out or leaves empty.
     *
     * @param  array<string, mixed>  $data
     */
    public static function rememberFromForm(User $user, array $data): void
    {
        static::remember(static::cacheKey($user), $data['editor_mode'] ?? null);

        $explained = collect([$data, ...($data['questions'] ?? [])])
            ->last(fn (array $item): bool => is_string($item['explanation'] ?? null) && Question::explanationOrNull($item['explanation']) !== null);

        if ($explained) {
            static::remember(static::explanationCacheKey($user), $explained['explanation_editor_mode'] ?? EditorMode::RichText);
        }
    }

    public static function for(User $user): EditorMode
    {
        return static::recalled(static::cacheKey($user));
    }

    public static function forExplanation(User $user): EditorMode
    {
        return static::recalled(static::explanationCacheKey($user));
    }

    protected static function remember(string $cacheKey, mixed $editorMode): void
    {
        $editorMode = $editorMode instanceof EditorMode ? $editorMode : EditorMode::tryFrom((string) $editorMode);

        if ($editorMode) {
            Cache::forever($cacheKey, $editorMode->value);
        }
    }

    protected static function recalled(string $cacheKey): EditorMode
    {
        return EditorMode::tryFrom(Cache::get($cacheKey) ?? '') ?? EditorMode::RichText;
    }

    protected static function cacheKey(User $user): string
    {
        return "question-editor-mode:{$user->id}";
    }

    protected static function explanationCacheKey(User $user): string
    {
        return "question-editor-mode:explanation:{$user->id}";
    }
}
