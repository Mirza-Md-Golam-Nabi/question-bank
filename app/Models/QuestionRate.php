<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
        $today = now()->toDateString();

        $rate = $subjectId
            ? static::query()
                ->where('subject_id', $subjectId)
                ->where('effective_from', '<=', $today)
                ->orderByDesc('effective_from')
                ->first()
            : null;

        $rate ??= static::query()
            ->whereNull('subject_id')
            ->where('effective_from', '<=', $today)
            ->orderByDesc('effective_from')
            ->first();

        return (float) ($rate->rate_amount ?? 0);
    }
}
