<?php

namespace App\Models;

use App\Enums\MobileBankingProvider;
use App\Enums\WithdrawalStatus;
use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Teacher's or Student's request to take referral earnings out as cash.
 * The amount leaves their wallet the moment it is asked for (so it can't
 * also be spent or asked for twice) and comes back if the Admin rejects
 * the request. Only WithdrawalService changes its status.
 *
 * The number the money goes to is stored encrypted, like a Staff member's
 * bank details, and is never logged.
 */
#[Fillable([
    'user_id', 'amount', 'provider', 'account_number', 'number_used_by_referred_buyer', 'status',
    'processed_by', 'processed_at', 'transaction_id', 'note',
])]
#[Hidden(['account_number'])]
class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'provider' => MobileBankingProvider::class,
            'account_number' => 'encrypted',
            'number_used_by_referred_buyer' => 'boolean',
            'status' => WithdrawalStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isRequested(): bool
    {
        return $this->status === WithdrawalStatus::Requested;
    }
}
