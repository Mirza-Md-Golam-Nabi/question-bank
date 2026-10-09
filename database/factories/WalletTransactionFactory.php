<?php

namespace Database\Factories;

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'type' => WalletTransactionType::PurchaseReturn,
            'amount' => 50,
            'created_at' => now(),
        ];
    }

    public function spent(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WalletTransactionType::Purchase,
            'amount' => -abs($amount),
        ]);
    }
}
