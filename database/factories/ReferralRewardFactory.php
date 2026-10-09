<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralReward>
 */
class ReferralRewardFactory extends Factory
{
    protected $model = ReferralReward::class;

    /**
     * A matured reward: its payment's refund period is over.
     */
    public function definition(): array
    {
        return [
            'referrer_id' => User::factory()->teacher(),
            'payment_id' => Payment::factory(),
            'amount' => 50,
            'matures_at' => now()->subDay(),
            'created_at' => now()->subDays(8),
        ];
    }

    /**
     * Still inside its payment's refund period.
     */
    public function maturing(): static
    {
        return $this->state(fn (array $attributes) => [
            'matures_at' => now()->addDays(7),
            'created_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['cancelled_at' => now()]);
    }
}
