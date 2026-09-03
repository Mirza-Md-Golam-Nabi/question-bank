<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'bank_account_number', 'bank_name', 'branch_name',
    'account_holder_name', 'mobile_banking_number',
    'total_questions_approved', 'total_earned', 'total_paid',
])]
class StaffProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'bank_account_number' => 'encrypted',
            'mobile_banking_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incrementEarnings(float $amount): void
    {
        $this->increment('total_questions_approved');
        $this->increment('total_earned', $amount);
    }
}
