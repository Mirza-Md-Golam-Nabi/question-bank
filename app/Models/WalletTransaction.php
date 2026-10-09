<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a user's wallet ledger. Rows are only ever added — a mistake
 * or a refund is corrected by a further row, never by editing one — and the
 * balance is worked out from them by WalletService.
 */
#[Fillable(['user_id', 'type', 'amount', 'payment_id', 'expires_at'])]
class WalletTransaction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'float',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
