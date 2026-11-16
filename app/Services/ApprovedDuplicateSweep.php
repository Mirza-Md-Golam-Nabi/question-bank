<?php

namespace App\Services;

use App\Enums\DuplicateCheckStatus;
use App\Enums\QuestionStatus;
use App\Jobs\ScreenApprovedChapterForDuplicates;
use App\Models\ApprovedDuplicateSet;
use App\Models\ApprovedDuplicateSetMember;
use App\Models\Question;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The duplicate check of the approved questions — the one place it is
 * decided (CLAUDE.md rule 3.7.2). On the queue, a chapter per job, the
 * approved questions of a chapter are compared with each other just as a
 * new question is (DuplicateQuestionFinder, through the same index); the
 * alike ones are gathered into sets — A like B and B like C make one set
 * of three — for the Admin to keep some and delete the rest. Until then
 * they stay in the pool. A question the Admin keeps here is never looked
 * through again (`duplicate_verified_at`), though a new question is still
 * compared with it; its next version is looked through anew.
 */
final class ApprovedDuplicateSweep
{
    /**
     * Where the last sweep's batch is remembered, for its progress.
     */
    private const BATCH_CACHE_KEY = 'approved-duplicate-sweep-batch';

    public function __construct(private readonly DuplicateQuestionFinder $finder) {}

    /**
     * At the Admin's percentage.
     */
    public static function make(): self
    {
        return new self(DuplicateQuestionFinder::make());
    }

    /**
     * The approved questions this looks through — the current version of a
     * question bank question, never a School's own.
     *
     * @return Builder<Question>
     */
    public static function approvedQuestions(): Builder
    {
        return Question::query()
            ->where('status', QuestionStatus::Approved)
            ->where('is_latest', true)
            ->where('is_private', false);
    }

    /**
     * Has the chapters looked through on the queue, a chapter per job — the
     * whole question bank, a class's subject, or one chapter.
     *
     * @return int How many chapters are to be looked through.
     */
    public static function queue(User $admin, ?int $classSubjectId = null, ?int $chapterId = null): int
    {
        $chapterIds = self::approvedQuestions()
            ->whereNull('duplicate_verified_at')
            ->when($chapterId, fn (Builder $query) => $query->where('chapter_id', $chapterId))
            ->when($classSubjectId && ! $chapterId, fn (Builder $query) => $query->whereHas('chapter', fn (Builder $chapter) => $chapter->where('class_subject_id', $classSubjectId)))
            ->distinct()
            ->pluck('chapter_id');

        if ($chapterIds->isEmpty()) {
            return 0;
        }

        $batch = Bus::batch($chapterIds->map(fn ($id) => new ScreenApprovedChapterForDuplicates((int) $id))->all())
            ->name('Approved question duplicate check')
            ->allowFailures()
            ->dispatch();

        Cache::forever(self::BATCH_CACHE_KEY, $batch->id);

        Log::info('Duplicate check of the approved questions started.', [
            'by' => $admin->id,
            'class_subject_id' => $classSubjectId,
            'chapter_id' => $chapterId,
            'chapters' => $chapterIds->count(),
        ]);

        return $chapterIds->count();
    }

    /**
     * The last sweep, while it runs — null once it is over (or never ran).
     *
     * @return array{done: int, total: int}|null
     */
    public static function progress(): ?array
    {
        $batchId = Cache::get(self::BATCH_CACHE_KEY);
        $batch = $batchId ? Bus::findBatch($batchId) : null;

        if (! $batch instanceof Batch || $batch->finished() || $batch->cancelled()) {
            return null;
        }

        return ['done' => $batch->processedJobs() + $batch->failedJobs, 'total' => $batch->totalJobs];
    }

    /**
     * Looks through one chapter's approved questions: each not yet kept by
     * the Admin is compared with the chapter's approved questions, and the
     * sets of alike ones replace the chapter's sets still waiting.
     */
    public function sweepChapter(int $chapterId): void
    {
        QuestionDuplicateScreening::fingerprintChapter($this->finder, $chapterId);

        $alike = [];

        $this->inChapter($chapterId)
            ->whereNull('duplicate_verified_at')
            ->with(['cqParts', 'stimulus'])
            ->chunkById(200, function ($questions) use (&$alike): void {
                foreach ($questions as $question) {
                    $fingerprint = $this->finder->fingerprint($question);
                    $candidates = Question::query()
                        ->duplicateScreeningCandidatesFor($question, $fingerprint['guard'], $this->finder->sizeRange($fingerprint['size']))
                        ->where('status', QuestionStatus::Approved)
                        ->get();

                    foreach ($this->finder->matches($question, $candidates, PHP_INT_MAX) as $match) {
                        $alike[] = [$question->id, $match->question->getKey(), $match->percent, $match->isExact];
                    }
                }
            });

        $sets = self::gather($alike);

        DB::transaction(function () use ($chapterId, $sets): void {
            ApprovedDuplicateSet::query()->where('chapter_id', $chapterId)->whereNull('resolved_at')->delete();

            foreach ($sets as $members) {
                $set = ApprovedDuplicateSet::query()->create(['chapter_id' => $chapterId]);

                foreach ($members as $questionId => [$percent, $isExact]) {
                    $set->members()->create(['question_id' => $questionId, 'percent' => $percent, 'is_exact' => $isExact]);
                }
            }
        });
    }

    /**
     * The Admin's decision on a set: the questions kept are never looked
     * through again; every other one is deleted (QuestionDuplicateScreening
     * keeps whatever exams and money already have it). At least one stays.
     *
     * @param  list<int>  $keptQuestionIds
     * @return array{kept: int, deleted: int, rejected: int}
     */
    public static function resolve(ApprovedDuplicateSet $set, array $keptQuestionIds, User $admin): array
    {
        if ($set->resolved_at !== null) {
            throw ValidationException::withMessages(['set' => __('This set has been decided already.')]);
        }

        $questions = Question::query()->whereKey($set->members()->pluck('question_id'))->get();
        $kept = $questions->filter(fn (Question $question): bool => in_array($question->id, $keptQuestionIds, true));

        if ($kept->isEmpty()) {
            throw ValidationException::withMessages(['kept' => __('Keep at least one of the questions.')]);
        }

        $counts = ['kept' => $kept->count(), 'deleted' => 0, 'rejected' => 0];

        DB::transaction(function () use ($set, $questions, $kept, $admin, &$counts): void {
            Question::query()->whereKey($kept->modelKeys())->update(['duplicate_verified_at' => now()]);

            foreach ($questions->diff($kept) as $question) {
                QuestionDuplicateScreening::removeApproved($question, $admin) ? $counts['deleted']++ : $counts['rejected']++;
            }

            $set->forceFill(['resolved_by' => $admin->id, 'resolved_at' => now()])->save();
        });

        Log::info('Set of alike approved questions decided.', [
            'set_id' => $set->id,
            'kept' => $kept->modelKeys(),
            'removed' => $questions->diff($kept)->modelKeys(),
            'by' => $admin->id,
        ]);

        return $counts;
    }

    /**
     * The question of a set to keep unless the Admin picks otherwise: the
     * one on the most exams, the oldest of those.
     *
     * @param  Collection<int, ApprovedDuplicateSetMember>  $members  With `question` loaded, its `exams_count` too.
     */
    public static function suggestedToKeep(Collection $members): ?int
    {
        return $members
            ->sortBy([fn (ApprovedDuplicateSetMember $one, ApprovedDuplicateSetMember $two): int => [$two->question->exams_count, $one->question_id] <=> [$one->question->exams_count, $two->question_id]])
            ->first()
            ?->question_id;
    }

    /**
     * The chapter's approved questions as the check sees them — one the
     * background check of new questions still holds is decided there.
     *
     * @return Builder<Question>
     */
    private function inChapter(int $chapterId): Builder
    {
        return self::approvedQuestions()
            ->where('chapter_id', $chapterId)
            ->where('duplicate_status', DuplicateCheckStatus::Clear);
    }

    /**
     * The alike pairs gathered into sets: a question joins every set it is
     * alike to one question of. Each question keeps how alike it is to the
     * one it is most like.
     *
     * @param  list<array{int, int, int, bool}>  $alike
     * @return list<array<int, array{int, bool}>> Per set, question id => [percent, is exact].
     */
    private static function gather(array $alike): array
    {
        $parent = [];
        $best = [];

        $root = function (int $id) use (&$parent, &$root): int {
            $parent[$id] ??= $id;

            return $parent[$id] === $id ? $id : $parent[$id] = $root($parent[$id]);
        };

        foreach ($alike as [$one, $two, $percent, $isExact]) {
            $parent[$root($one)] = $root($two);

            foreach ([$one, $two] as $id) {
                $best[$id] = self::better($best[$id] ?? null, [$percent, $isExact]);
            }
        }

        $sets = [];

        foreach (array_keys($best) as $id) {
            $sets[$root($id)][$id] = $best[$id];
        }

        foreach ($sets as &$members) {
            ksort($members);
        }

        return array_values(array_filter($sets, fn (array $members): bool => count($members) >= 2));
    }

    /**
     * The closer of two likenesses — the very same question first.
     *
     * @param  array{int, bool}|null  $current
     * @param  array{int, bool}  $found
     * @return array{int, bool}
     */
    private static function better(?array $current, array $found): array
    {
        if ($current === null) {
            return $found;
        }

        return [$found[1], $found[0]] > [$current[1], $current[0]] ? $found : $current;
    }
}
