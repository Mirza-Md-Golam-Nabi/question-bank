<?php

namespace App\Models;

use App\Enums\PlanDuration;
use App\Enums\SubscriptionTargetRole;
use App\Services\PlanConditions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plan on sale (or the role's free plan): its price, how long it lasts,
 * and the conditions the Admin added to it — read through planConditions().
 */
#[Fillable([
    'name', 'target_role', 'price', 'duration_unit', 'duration_length', 'ends_on', 'conditions',
    'is_default_free', 'is_trial', 'is_active',
])]
class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $attributes = [
        'duration_unit' => 'unlimited',
        'is_trial' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'target_role' => SubscriptionTargetRole::class,
            'duration_unit' => PlanDuration::class,
            'duration_length' => 'integer',
            'ends_on' => 'date',
            'conditions' => 'array',
            'is_default_free' => 'boolean',
            'is_trial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function planConditions(): PlanConditions
    {
        return PlanConditions::fromArray($this->conditions);
    }

    /**
     * When a subscription to this plan that starts at `$start` ends.
     */
    public function periodEndFrom(CarbonInterface $start): ?CarbonInterface
    {
        return $this->duration_unit->periodEndFrom($start, $this->duration_length, $this->ends_on);
    }

    /**
     * How long the plan lasts, for a plan card: "7 days", "Until 1 Mar 2027"…
     */
    public function durationLabel(): string
    {
        $length = $this->duration_length ?? 1;

        return match ($this->duration_unit) {
            PlanDuration::Days => trans_choice(':count day|:count days', $length),
            PlanDuration::Months => trans_choice(':count month|:count months', $length),
            PlanDuration::Years => trans_choice(':count year|:count years', $length),
            PlanDuration::UntilDate => __('Until :date', ['date' => $this->ends_on?->format('M j, Y')]),
            PlanDuration::Unlimited => __('No end'),
        };
    }

    /**
     * The plans a user of this role can buy: active paid ones and the
     * trial, but not one whose fixed end date has already passed (the free
     * plan is what they fall back to, not something to buy).
     */
    public function scopePurchasableFor(Builder $query, SubscriptionTargetRole $role): Builder
    {
        return $query->where('target_role', $role)
            ->where('is_active', true)
            ->where('is_default_free', false)
            ->where(fn (Builder $q) => $q->where('price', '>', 0)->orWhere('is_trial', true))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()))
            ->orderByDesc('is_trial')
            ->orderBy('price');
    }

    public function scopeDefaultFreeFor(Builder $query, SubscriptionTargetRole $role): Builder
    {
        return $query->where('target_role', $role)->where('is_default_free', true);
    }
}
