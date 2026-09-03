<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared by every "sub-question part" model (question_cq_parts,
 * board_cq_question_parts, ...): whenever a part is saved or deleted, the
 * parent's `marks` column is recalculated as the sum of all its parts. This
 * keeps the "CQ marks = sum of its 4 parts" rule centralized in one place
 * instead of duplicated per parent/part model pair.
 */
trait SyncsMarksFromParts
{
    protected static function bootSyncsMarksFromParts(): void
    {
        static::saved(fn (self $part) => $part->syncParentMarksFromParts());
        static::deleted(fn (self $part) => $part->syncParentMarksFromParts());
    }

    public function syncParentMarksFromParts(): void
    {
        $parent = $this->parentForMarksSync();

        if (! $parent) {
            return;
        }

        $parent->newQuery()->whereKey($parent->getKey())->update([
            'marks' => $this->partsForMarksSync()->sum('marks'),
        ]);
    }

    abstract protected function parentForMarksSync(): ?Model;

    abstract protected function partsForMarksSync(): HasMany;
}
