<?php

namespace Database\Factories;

use App\Enums\MobileBankingProvider;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'amount' => 500,
            'provider' => MobileBankingProvider::Bkash,
            'account_number' => '01712345678',
            'status' => WithdrawalStatus::Requested,
        ];
    }
}
