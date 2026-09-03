<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionTargetRole;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'target_role' => SubscriptionTargetRole::Teacher,
            'price' => 0,
            'billing_cycle' => BillingCycle::Free,
            'monthly_exam_limit' => 3,
            'is_default_free' => false,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'billing_cycle' => BillingCycle::Free,
            'is_default_free' => true,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 500,
            'billing_cycle' => BillingCycle::Monthly,
            'monthly_exam_limit' => null,
            'is_default_free' => false,
        ]);
    }

    public function forStudents(): static
    {
        return $this->state(fn (array $attributes) => ['target_role' => SubscriptionTargetRole::Student]);
    }
}
