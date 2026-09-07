<?php

namespace App\Models;

use App\Enums\MobileBankingProvider;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'payment_method', 'bank_account_number', 'bank_name', 'branch_name',
    'account_holder_name', 'mobile_banking_number', 'mobile_banking_provider',
    'total_questions_approved', 'total_earned', 'total_paid',
])]
class StaffProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'bank_account_number' => 'encrypted',
            'mobile_banking_number' => 'encrypted',
            'mobile_banking_provider' => MobileBankingProvider::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A staff member only gets a StaffProfile row once they save their bank
     * info — but their first question can be approved (or paid out) before
     * that ever happens. Reaching for `$staff->staffProfile` directly at
     * those points and null-safe-chaining the increment silently drops it,
     * leaving total_questions_approved/total_earned/total_paid stuck at
     * zero forever. This guarantees the row exists first.
     */
    public static function firstOrCreateFor(User $staff): self
    {
        return self::firstOrCreate(['user_id' => $staff->id]);
    }

    public function incrementEarnings(float $amount): void
    {
        $this->increment('total_questions_approved');
        $this->increment('total_earned', $amount);
    }
}
