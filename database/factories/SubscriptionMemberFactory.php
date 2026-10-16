<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionMember>
 */
class SubscriptionMemberFactory extends Factory
{
    protected $model = SubscriptionMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'email' => fake()->unique()->safeEmail(),
            'removed_at' => null,
        ];
    }
}
