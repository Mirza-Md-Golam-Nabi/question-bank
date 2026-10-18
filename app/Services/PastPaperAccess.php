<?php

namespace App\Services;

use App\Enums\PaperSourceType;
use App\Models\BoardQuestionPaper;

/**
 * What a plan's "Past papers" condition says, read into one object: which
 * kinds of paper (board, medical, university…) and which sources it reaches,
 * how old a paper has to be, and how many papers may be sat (or, for a
 * teacher, set) in how many days. Anything left out is not limited. The
 * only place that knows what that condition's data means.
 *
 * The count is a rolling one — "3 papers in any 7 days" — so a week, a
 * fortnight or a month is just a number of days the Admin picks.
 */
final class PastPaperAccess
{
    /**
     * @param  array<int, PaperSourceType>|null  $sourceTypes  Null = every kind.
     * @param  array<int, int>  $sourceIds  Empty = every source.
     * @param  int|null  $minAgeYears  Only papers at least this many years old; null = any year.
     * @param  int|null  $limit  How many papers in $limitDays days; null = no limit.
     */
    public function __construct(
        public readonly ?array $sourceTypes = null,
        public readonly array $sourceIds = [],
        public readonly ?int $minAgeYears = null,
        public readonly ?int $limit = null,
        public readonly ?int $limitDays = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  The condition block's data.
     */
    public static function fromArray(array $data): self
    {
        $sourceTypes = array_values(array_filter(array_map(
            fn (mixed $value): ?PaperSourceType => $value instanceof PaperSourceType ? $value : PaperSourceType::tryFrom((string) $value),
            (array) ($data['source_types'] ?? []),
        )));

        $limit = self::wholeNumberOrNull($data['limit'] ?? null);
        $limitDays = self::wholeNumberOrNull($data['per_days'] ?? null);

        return new self(
            sourceTypes: $sourceTypes === [] ? null : $sourceTypes,
            sourceIds: collect((array) ($data['board_ids'] ?? []))->filter(fn (mixed $id) => is_numeric($id))->map(fn (mixed $id) => (int) $id)->unique()->values()->all(),
            minAgeYears: self::wholeNumberOrNull($data['min_age_years'] ?? null) ?: null,
            // A count needs a period to be counted over.
            limit: $limitDays ? $limit : null,
            limitDays: $limit !== null ? ($limitDays ?: null) : null,
        );
    }

    public function hasLimit(): bool
    {
        return $this->limit !== null && $this->limitDays !== null;
    }

    /**
     * Whether a paper is one this access reaches: of an allowed kind, from
     * an allowed source, and old enough. (Whether its class + subject is
     * covered, and whether any are left to sit, is the grant's to say.)
     */
    public function reaches(BoardQuestionPaper $paper): bool
    {
        return ($this->sourceTypes === null || in_array($paper->board->type, $this->sourceTypes, true))
            && ($this->sourceIds === [] || in_array((int) $paper->board_id, $this->sourceIds, true))
            && ($this->newestYear() === null || $paper->year <= $this->newestYear());
    }

    /**
     * The most recent year a paper may be from; null when any year will do.
     */
    public function newestYear(): ?int
    {
        return $this->minAgeYears === null ? null : now()->year - $this->minAgeYears;
    }

    /**
     * One line per thing this access limits, for a plan's description.
     *
     * @param  array<int, string>  $sourceNames  Every source's short name, keyed by id.
     * @return array<int, string>
     */
    public function descriptions(array $sourceNames): array
    {
        return array_values(array_filter([
            $this->sourceTypes === null ? null : __('Past papers: :kinds', [
                'kinds' => collect($this->sourceTypes)->map(fn (PaperSourceType $type) => $type->getLabel())->join(', '),
            ]),
            $this->sourceIds === [] ? null : __('Past papers from: :sources', [
                'sources' => collect($this->sourceIds)->map(fn (int $id) => $sourceNames[$id] ?? '?')->join(', '),
            ]),
            $this->minAgeYears === null ? null : trans_choice('Past papers at least :count year old|Past papers at least :count years old', $this->minAgeYears),
            ! $this->hasLimit() ? null : trans_choice(':count past paper|:count past papers', $this->limit).' '.trans_choice('every :count day|every :count days', $this->limitDays),
        ]));
    }

    private static function wholeNumberOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }
}
