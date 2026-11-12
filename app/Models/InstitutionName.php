<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InstitutionNameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school/coaching name a Teacher or School prints at the top of a
 * question paper. The first one is free and always usable; every other
 * one only while it is paid for (`paid_until`). Changed, added and paid
 * for only through InstitutionNameService.
 */
#[Fillable(['user_id', 'name', 'is_free', 'paid_until', 'renamed_at'])]
class InstitutionName extends Model
{
    /** @use HasFactory<InstitutionNameFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'paid_until' => 'datetime',
            'renamed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Whether it can be printed now: the free one always, any other while
     * it is paid for.
     */
    public function isUsable(?CarbonInterface $at = null): bool
    {
        return $this->is_free || ($this->paid_until !== null && $this->paid_until->greaterThan($at ?? now()));
    }

    /**
     * The SQL form of isUsable().
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where(fn (Builder $usable) => $usable
            ->where('is_free', true)
            ->orWhere('paid_until', '>', now()));
    }

    /**
     * The free one first, then the rest in the order they were added.
     */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_free')->orderBy('id');
    }
}
