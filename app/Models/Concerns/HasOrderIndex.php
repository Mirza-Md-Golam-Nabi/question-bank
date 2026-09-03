<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared by every reference-data model that carries an `order_index` column
 * (academic_classes, class_subjects, chapters, ...), so the same "display in
 * configured order" query never needs to be written out more than once.
 */
trait HasOrderIndex
{
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_index');
    }

    /**
     * Make room for a record at `$newOrder` by shifting every other record
     * in the same sequence out of the way, so setting an order never
     * collides with (or silently sits on top of) an existing one — the rest
     * of the sequence renumbers around it instead.
     *
     * @param  Builder  $scope  Query already narrowed to the records sharing
     *                          this order sequence (e.g. one class's
     *                          class_subjects), excluding the record being
     *                          placed itself when moving an existing one.
     * @param  int|null  $oldOrder  Null when inserting a new record; the
     *                              record's current order_index when moving
     *                              an existing one.
     */
    public static function reorder(Builder $scope, int $newOrder, ?int $oldOrder = null): void
    {
        if ($oldOrder === null) {
            $scope->where('order_index', '>=', $newOrder)->increment('order_index');

            return;
        }

        if ($newOrder < $oldOrder) {
            $scope->whereBetween('order_index', [$newOrder, $oldOrder - 1])->increment('order_index');
        } elseif ($newOrder > $oldOrder) {
            $scope->whereBetween('order_index', [$oldOrder + 1, $newOrder])->decrement('order_index');
        }
    }
}
