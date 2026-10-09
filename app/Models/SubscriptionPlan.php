<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionTargetRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'target_role', 'price', 'billing_cycle', 'monthly_exam_limit', 'is_default_free'])]
class SubscriptionPlan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'target_role' => SubscriptionTargetRole::class,
            'billing_cycle' => BillingCycle::class,
            'is_default_free' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * The plans a user of this role can buy: paid ones only (the free plan
     * is what they fall back to, not something to purchase).
     */
    public function scopePurchasableFor(Builder $query, SubscriptionTargetRole $role): Builder
    {
        return $query->where('target_role', $role)->where('price', '>', 0)->orderBy('price');
    }

    public function scopeDefaultFreeFor(Builder $query, SubscriptionTargetRole $role): Builder
    {
        return $query->where('target_role', $role)->where('is_default_free', true);
    }
}
