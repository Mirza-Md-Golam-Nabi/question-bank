<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Services\PlanConditions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One period of access bought from a plan. `conditions` is the plan's list
 * of conditions as it stood at purchase and `selections` the classes and
 * subjects the buyer picked, so editing the plan later never changes what
 * was already paid for.
 */
#[Fillable(['user_id', 'plan_id', 'conditions', 'selections', 'status', 'starts_at', 'ends_at', 'auto_renew'])]
class Subscription extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'conditions' => 'array',
            'selections' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * The people the buyer shares a group plan with.
     */
    public function members(): HasMany
    {
        return $this->hasMany(SubscriptionMember::class);
    }

    /**
     * How many people may use it, the buyer included: the plan's seats as
     * bought, or just the buyer.
     */
    public function seats(): int
    {
        return PlanConditions::fromArray($this->conditions)->seats ?? 1;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
