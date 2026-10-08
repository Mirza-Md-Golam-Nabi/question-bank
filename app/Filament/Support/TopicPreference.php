<?php

namespace App\Filament\Support;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers which topic a user last saved a question under for each
 * chapter, so adding a run of questions to the same topic doesn't mean
 * re-selecting it every time — shared by every panel's question form since
 * the preference is per-user, not per-panel.
 */
class TopicPreference
{
    public static function remember(User $user, int|string $chapterId, int|string|null $topicId): void
    {
        if (blank($topicId)) {
            Cache::forget(static::cacheKey($user, $chapterId));

            return;
        }

        Cache::forever(static::cacheKey($user, $chapterId), (int) $topicId);
    }

    /**
     * Null when nothing is remembered, or when the remembered topic has
     * since been deleted or no longer belongs to this chapter.
     */
    public static function for(User $user, int|string|null $chapterId): ?int
    {
        if (blank($chapterId)) {
            return null;
        }

        $topicId = Cache::get(static::cacheKey($user, $chapterId));

        if (blank($topicId)) {
            return null;
        }

        return Topic::query()
            ->where('chapter_id', $chapterId)
            ->whereKey($topicId)
            ->value('id');
    }

    protected static function cacheKey(User $user, int|string $chapterId): string
    {
        return "question-topic:{$user->id}:{$chapterId}";
    }
}
