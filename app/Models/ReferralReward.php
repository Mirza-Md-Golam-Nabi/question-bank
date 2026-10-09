<?php

namespace App\Models;

use App\Enums\ReferralRewardStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a referrer earned from one payment — the same record whether the
 * referrer is a Teacher or Student (it becomes wallet credit) or a Staff
 * member (it is paid out with their earnings).
 *
 * A reward waits until its payment can no longer be refunded
 * (`matures_at`); refunded before then, it is cancelled and was never
 * anyone's to spend. So no reward ever has to be taken back after the
 * fact. Its state is read off its dates, never stored.
 */
#[Fillable(['referrer_id', 'payment_id', 'amount', 'matures_at', 'expires_at', 'cancelled_at', 'payout_id'])]
class ReferralReward extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'matures_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(StaffPayout::class);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    /**
     * Still inside its payment's refund period.
     */
    public function scopeMaturing(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')->where('matures_at', '>', now());
    }

    /**
     * Past the refund period: the referrer's for good.
     */
    public function scopeMatured(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')->where('matures_at', '<=', now());
    }

    /**
     * Matured and not yet included in a Staff payout.
     */
    public function scopeAwaitingPayout(Builder $query): Builder
    {
        return $query->matured()->whereNull('payout_id');
    }

    /**
     * The rewards a query selects, added up by where they stand — every
     * state from one query. The states are the same ones the scopes above
     * and status() define; `paid` is the part of `matured` already
     * included in a Staff payout.
     *
     * @param  Builder<self>  $rewards
     * @return array{maturing: float, matured: float, paid: float, cancelled: float}
     */
    public static function totalsByState(Builder $rewards): array
    {
        $now = now();

        $totals = $rewards
            ->selectRaw('coalesce(sum(case when cancelled_at is null and matures_at > ? then amount else 0 end), 0) as maturing', [$now])
            ->selectRaw('coalesce(sum(case when cancelled_at is null and matures_at <= ? then amount else 0 end), 0) as matured', [$now])
            ->selectRaw('coalesce(sum(case when cancelled_at is null and matures_at <= ? and payout_id is not null then amount else 0 end), 0) as paid', [$now])
            ->selectRaw('coalesce(sum(case when cancelled_at is not null then amount else 0 end), 0) as cancelled')
            ->toBase()
            ->first();

        return [
            'maturing' => round((float) $totals->maturing, 2),
            'matured' => round((float) $totals->matured, 2),
            'paid' => round((float) $totals->paid, 2),
            'cancelled' => round((float) $totals->cancelled, 2),
        ];
    }

    public function status(): ReferralRewardStatus
    {
        return match (true) {
            $this->cancelled_at !== null => ReferralRewardStatus::Cancelled,
            $this->payout_id !== null => ReferralRewardStatus::Paid,
            $this->matures_at->isFuture() => ReferralRewardStatus::Maturing,
            default => ReferralRewardStatus::Available,
        };
    }
}
