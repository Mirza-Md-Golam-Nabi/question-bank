<?php

namespace App\Models;

use App\Enums\StaffEarningStatus;
use App\Enums\StaffPayoutStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['staff_id', 'total_amount', 'status', 'paid_at', 'reference_note', 'paid_by'])]
class StaffPayout extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => StaffPayoutStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(StaffEarning::class, 'payout_id');
    }

    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'payout_id');
    }

    /**
     * Everything a staff member is owed right now: approved questions not
     * yet paid for, plus matured referral rewards.
     */
    public static function amountDueTo(User $staff): float
    {
        $staffOwed = self::staffOwedMoney()->whereKey($staff->id)->first();

        return $staffOwed ? self::amountDueOn($staffOwed) : 0.0;
    }

    /**
     * The total owed to one of the rows staffOwedMoney() returns.
     */
    public static function amountDueOn(User $staffOwed): float
    {
        return round($staffOwed->earnings_due + $staffOwed->rewards_due, 2);
    }

    /**
     * The staff members who are owed something, each carrying what they
     * are owed (`earnings_due`, `rewards_due`) — worked out in the query,
     * so listing them costs one query however many there are.
     *
     * @return Builder<User>
     */
    public static function staffOwedMoney(): Builder
    {
        $pendingEarnings = fn (Builder $earnings) => $earnings->where('status', StaffEarningStatus::PendingPayout);
        $maturedRewards = fn (Builder $rewards) => $rewards->awaitingPayout();

        return User::query()
            ->where('role', UserRole::Staff)
            ->where(fn (Builder $owed) => $owed
                ->whereHas('staffEarnings', $pendingEarnings)
                ->orWhereHas('referralRewards', $maturedRewards))
            ->withSum(['staffEarnings as earnings_due' => $pendingEarnings], 'amount')
            ->withSum(['referralRewards as rewards_due' => $maturedRewards], 'amount')
            ->withCasts(['earnings_due' => 'float', 'rewards_due' => 'float']);
    }

    /**
     * Batch-pays every one of a staff member's still-pending earnings and
     * matured referral rewards in one shot: creates the payout row, marks
     * each earning `paid` and links it and each reward to this payout, and bumps the staff profile's total_paid counter — all
     * inside one transaction so a partial failure can't double-pay or
     * silently drop earnings.
     */
    public static function createFor(User $staff, User $paidBy, ?string $referenceNote = null): self
    {
        return DB::transaction(function () use ($staff, $paidBy, $referenceNote) {
            $pendingEarnings = StaffEarning::query()
                ->where('staff_id', $staff->id)
                ->where('status', StaffEarningStatus::PendingPayout)
                ->lockForUpdate()
                ->get();

            // Referral rewards are paid in the same batch, once they have
            // matured (their payment can no longer be refunded).
            $maturedRewards = $staff->referralRewards()->awaitingPayout()->lockForUpdate()->get();

            $totalAmount = $pendingEarnings->sum('amount') + $maturedRewards->sum('amount');

            $payout = self::create([
                'staff_id' => $staff->id,
                'total_amount' => $totalAmount,
                'status' => StaffPayoutStatus::Paid,
                'paid_at' => now(),
                'reference_note' => $referenceNote,
                'paid_by' => $paidBy->id,
            ]);

            StaffEarning::query()
                ->whereKey($pendingEarnings->pluck('id'))
                ->update(['status' => StaffEarningStatus::Paid, 'payout_id' => $payout->id]);

            ReferralReward::query()
                ->whereKey($maturedRewards->pluck('id'))
                ->update(['payout_id' => $payout->id]);

            StaffProfile::firstOrCreateFor($staff)->increment('total_paid', $totalAmount);

            return $payout;
        });
    }
}
