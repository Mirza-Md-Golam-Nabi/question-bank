<?php

namespace App\Models;

use App\Enums\StaffEarningStatus;
use App\Enums\StaffPayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    /**
     * Batch-pays every one of a staff member's still-pending earnings in one
     * shot: creates the payout row, marks each earning `paid` and links it
     * to this payout, and bumps the staff profile's total_paid counter — all
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

            $totalAmount = $pendingEarnings->sum('amount');

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

            $staff->staffProfile?->increment('total_paid', $totalAmount);

            return $payout;
        });
    }
}
