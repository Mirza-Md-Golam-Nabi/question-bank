<?php

namespace App\Models;

use Database\Factories\SubscriptionMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone the buyer of a group plan shares it with, known by their Google
 * email — so they can be added before they have ever signed in. Removing
 * one only marks it; the rules are SubscriptionMemberService's.
 */
#[Fillable(['subscription_id', 'email', 'removed_at'])]
class SubscriptionMember extends Model
{
    /** @use HasFactory<SubscriptionMemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'removed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The account the email belongs to, once it has signed in.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }
}
