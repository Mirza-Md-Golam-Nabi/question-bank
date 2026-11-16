<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approved questions of one chapter found alike by the duplicate check of
 * the approved questions (ApprovedDuplicateSweep) — waiting for the Admin
 * to keep some and delete the rest, until resolved.
 */
#[Fillable(['chapter_id', 'resolved_by', 'resolved_at'])]
class ApprovedDuplicateSet extends Model
{
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * The questions of the set still in the pool, the first found first —
     * one deleted or rejected elsewhere meanwhile has left it.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ApprovedDuplicateSetMember::class)
            ->whereHas('question', fn (Builder $question) => $question
                ->where('status', QuestionStatus::Approved)
                ->where('is_latest', true))
            ->orderByDesc('is_exact')
            ->orderByDesc('percent')
            ->orderBy('question_id');
    }

    /**
     * Sets waiting for the Admin that still have two questions or more to
     * compare.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAwaitingAdmin(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')->has('members', '>=', 2);
    }
}
