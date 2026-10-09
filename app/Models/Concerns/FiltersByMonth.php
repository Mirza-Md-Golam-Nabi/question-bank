<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Rows of one calendar month" as a date range on the column itself —
 * which an index on that column can serve, unlike wrapping the column in
 * MONTH()/YEAR() (what whereMonth/whereYear do), which makes the database
 * read every row to compute them.
 */
trait FiltersByMonth
{
    /**
     * @param  ?CarbonInterface  $month  Any moment inside the month wanted; this month when left out.
     */
    public function scopeInMonth(Builder $query, ?CarbonInterface $month = null, string $column = 'created_at'): Builder
    {
        $month ??= now();

        return $query->whereBetween($query->qualifyColumn($column), [
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        ]);
    }
}
