<?php

namespace App\Services;

use App\Enums\DuplicateCheckStatus;
use App\Enums\UserRole;
use App\Jobs\ScreenChapterForDuplicates;
use App\Jobs\ScreenQuestionForDuplicates;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The background duplicate check of the question bank — the one place it
 * is decided. Every new or changed question of a chapter is compared, on
 * the queue, with every settled question of that chapter (all of them, as
 * an Admin sees them — not only the ones its writer can see). One that
 * looks like a question already there waits as "suspected" — out of the
 * pool, off every exam, not approvable — until the Admin keeps it or
 * throws it away. How alike two questions are is DuplicateQuestionFinder's.
 *
 * Only the few questions that can be a duplicate are read: the same
 * chapter and type, the same guard, a size in range (one index). A School's
 * own questions and past papers are never checked.
 */
final class QuestionDuplicateScreening
{
    /**
     * How many of the questions a question looks like are kept for the
     * Admin to compare, the most alike first.
     */
    public const MAX_MATCHES = 5;

    public function __construct(private readonly DuplicateQuestionFinder $finder) {}

    /**
     * At the Admin's percentage.
     */
    public static function make(): self
    {
        return new self(DuplicateQuestionFinder::make());
    }

    /**
     * Puts a question back to "checking" and has it checked on the queue,
     * once whatever it is being saved with is saved.
     */
    public static function queue(Question $question): void
    {
        if (! self::isChecked($question)) {
            return;
        }

        if ($question->duplicate_status !== DuplicateCheckStatus::Checking) {
            $question->newQuery()->whereKey($question->id)->update(['duplicate_status' => DuplicateCheckStatus::Checking]);
            $question->forceFill(['duplicate_status' => DuplicateCheckStatus::Checking])->syncOriginalAttribute('duplicate_status');
        }

        ScreenQuestionForDuplicates::dispatch($question->id)->afterCommit();
    }

    /**
     * Whether what this user writes is checked here — everyone's but a
     * School's, whose questions are its own (CLAUDE.md rule 12). Such a
     * writer's JSON is added whole: whatever looks like a question already
     * there waits for the Admin's word instead of being left out.
     */
    public static function screensQuestionsBy(User $writer): bool
    {
        return $writer->role !== UserRole::School;
    }

    /**
     * Whether a question goes through the check at all: the current
     * version of a question bank question, never a School's own.
     */
    public static function isChecked(Question $question): bool
    {
        return $question->is_latest && ! $question->is_private;
    }

    /**
     * Checks one question — a new one waiting, or one already there that
     * was clear — against its chapter's settled questions, and keeps what
     * it looks like. One already suspected waits for the Admin.
     */
    public function screen(int $questionId): void
    {
        $question = Question::query()->with(['cqParts', 'stimulus'])->find($questionId);

        if (! $question || ! self::isChecked($question) || $question->duplicate_status === DuplicateCheckStatus::Suspected) {
            return;
        }

        $fingerprint = $this->finder->fingerprint($question);
        $candidates = Question::query()
            ->duplicateScreeningCandidatesFor($question, $fingerprint['guard'], $this->finder->sizeRange($fingerprint['size']))
            ->get();
        $matches = $this->finder->matches($question, $candidates, self::MAX_MATCHES);

        DB::transaction(function () use ($question, $fingerprint, $matches): void {
            $question->duplicateMatches()->delete();

            foreach ($matches as $match) {
                $question->duplicateMatches()->create([
                    'matched_question_id' => $match->question->getKey(),
                    'percent' => $match->percent,
                    'is_exact' => $match->isExact,
                ]);
            }

            $question->newQuery()->whereKey($question->id)->update([
                'duplicate_guard' => $fingerprint['guard'],
                'duplicate_size' => $fingerprint['size'],
                'duplicate_status' => $matches === [] ? DuplicateCheckStatus::Clear : DuplicateCheckStatus::Suspected,
            ]);
        });
    }

    /**
     * Looks for duplicates among a chapter's questions already there: each
     * is first given its fingerprint, then checked newest first — so of two
     * alike, the later one is the one held for the Admin.
     */
    public function screenChapter(int $chapterId): void
    {
        $current = fn () => Question::query()
            ->where('chapter_id', $chapterId)
            ->where('is_latest', true)
            ->where('is_private', false);

        $current()->whereNull('duplicate_guard')->with(['cqParts', 'stimulus'])->chunkById(200, function ($questions): void {
            foreach ($questions as $question) {
                $question->newQuery()->whereKey($question->id)->update(array_combine(
                    ['duplicate_guard', 'duplicate_size'],
                    array_values($this->finder->fingerprint($question)),
                ));
            }
        });

        $current()->where('duplicate_status', DuplicateCheckStatus::Clear)
            ->orderByDesc('id')
            ->pluck('id')
            ->each(fn (int $id) => $this->screen($id));
    }

    /**
     * Has every chapter's questions already there looked through on the
     * queue, a chapter per job.
     *
     * @return int How many chapters are to be looked through.
     */
    public static function queueEveryChapter(User $admin): int
    {
        $chapterIds = Question::query()
            ->where('is_latest', true)
            ->where('is_private', false)
            ->distinct()
            ->pluck('chapter_id');

        foreach ($chapterIds as $chapterId) {
            ScreenChapterForDuplicates::dispatch((int) $chapterId);
        }

        Log::info('Duplicate check of the questions already there started.', ['by' => $admin->id, 'chapters' => $chapterIds->count()]);

        return $chapterIds->count();
    }

    /**
     * The Admin finds a suspected question to be a question of its own: it
     * is cleared, and goes on to be approved (or used) as any other.
     */
    public static function keep(Question $question, User $admin): void
    {
        DB::transaction(function () use ($question): void {
            $question->duplicateMatches()->delete();
            $question->newQuery()->whereKey($question->id)->update(['duplicate_status' => DuplicateCheckStatus::Clear]);
        });

        $question->forceFill(['duplicate_status' => DuplicateCheckStatus::Clear])->syncOriginalAttribute('duplicate_status');

        Log::info('Possible duplicate question kept.', ['question_id' => $question->id, 'by' => $admin->id]);
    }

    /**
     * The Admin finds a suspected question to be a duplicate. It is removed
     * for good — unless an exam already has it: then it is rejected
     * instead, staying on that exam but leaving the list and the pool.
     * When it was a new version, the version before it is the current one
     * again.
     *
     * @return bool Whether it was removed for good (rather than rejected).
     */
    public static function discard(Question $question, User $admin): bool
    {
        if ($question->isOnAnExam()) {
            DB::transaction(function () use ($question, $admin): void {
                $question->reject($admin, __('A duplicate of a question already in the chapter.'));
                $question->duplicateMatches()->delete();
                $question->newQuery()->whereKey($question->id)->update(['duplicate_status' => DuplicateCheckStatus::Clear]);
            });

            Log::info('Duplicate question rejected (it is on an exam).', ['question_id' => $question->id, 'by' => $admin->id]);

            return false;
        }

        DB::transaction(function () use ($question): void {
            if ($question->parent_id !== null) {
                $previousId = Question::query()
                    ->where(fn ($versions) => $versions->whereKey($question->parent_id)->orWhere('parent_id', $question->parent_id))
                    ->whereKeyNot($question->id)
                    ->orderByDesc('version')
                    ->value('id');

                Question::query()->whereKey($previousId)->update(['is_latest' => true]);
            }

            $question->forceDelete();

            if ($question->stimulus_id !== null && ! Question::withTrashed()->where('stimulus_id', $question->stimulus_id)->exists()) {
                QuestionStimulus::query()->whereKey($question->stimulus_id)->delete();
            }
        });

        Log::info('Duplicate question deleted for good.', [
            'question_id' => $question->id,
            'chapter_id' => $question->chapter_id,
            'created_by' => $question->created_by,
            'by' => $admin->id,
        ]);

        return true;
    }
}
