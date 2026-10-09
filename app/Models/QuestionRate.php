<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subject_id', 'rate_amount', 'effective_from'])]
class QuestionRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The per-question rate currently in effect for a subject — the most
     * recent subject-specific rate whose effective_from has passed, falling
     * back to the most recent default (subject_id = null) rate. Centralized
     * here so QuestionObserver's approve-time snapshot and any rate-preview
     * UI always agree on "what rate applies right now."
     */
    public static function rateFor(?int $subjectId): float
    {
        return self::ratesFor([$subjectId])[(int) $subjectId];
    }

    /**
     * The rate in effect for each of several subjects, by the same rule —
     * from one query for all of them, however many there are.
     *
     * @param  iterable<int|null>  $subjectIds
     * @return array<int, float> Keyed by subject id (0 for "no subject").
     */
    public static function ratesFor(iterable $subjectIds): array
    {
        $subjectIds = collect($subjectIds)->map(fn (?int $subjectId): int => (int) $subjectId)->unique()->values();

        // Newest first, so the first match per subject is the one in effect.
        $inEffect = static::query()
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereIn('subject_id', $subjectIds->filter())
                ->orWhereNull('subject_id'))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get(['subject_id', 'rate_amount']);

        $default = (float) ($inEffect->first(fn (self $rate): bool => $rate->subject_id === null)?->rate_amount ?? 0);

        return $subjectIds
            ->mapWithKeys(fn (int $subjectId): array => [
                $subjectId => (float) ($inEffect->first(fn (self $rate): bool => $rate->subject_id === $subjectId)?->rate_amount ?? $default),
            ])
            ->all();
    }
}
