<?php

namespace Database\Factories;

use App\Enums\PlanConditionType;
use App\Enums\PlanDuration;
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
            'duration_unit' => PlanDuration::Unlimited,
            'duration_length' => null,
            'conditions' => [self::condition(PlanConditionType::MonthlyExamLimit, ['limit' => 3])],
            'is_default_free' => false,
            'is_trial' => false,
            'is_active' => true,
        ];
    }

    /**
     * The role's free plan, everyone falls back to.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'duration_unit' => PlanDuration::Unlimited,
            'is_default_free' => true,
        ]);
    }

    /**
     * A paid monthly plan with no conditions at all — every class, subject,
     * difficulty and feature, no limits.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 500,
            'duration_unit' => PlanDuration::Months,
            'duration_length' => 1,
            'conditions' => [],
            'is_default_free' => false,
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'duration_unit' => PlanDuration::Days,
            'duration_length' => 7,
            'conditions' => [],
            'is_trial' => true,
        ]);
    }

    public function forStudents(): static
    {
        return $this->state(fn (array $attributes) => ['target_role' => SubscriptionTargetRole::Student]);
    }

    /**
     * Replaces the plan's conditions.
     *
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $conditions
     */
    public function withConditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => ['conditions' => $conditions]);
    }

    /**
     * Shorthand for a plan whose only condition is a monthly exam limit.
     */
    public function monthlyLimit(?int $limit): static
    {
        return $this->withConditions($limit === null ? [] : [self::condition(PlanConditionType::MonthlyExamLimit, ['limit' => $limit])]);
    }

    /**
     * One condition block, as the plan form stores it.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, data: array<string, mixed>}
     */
    public static function condition(PlanConditionType $type, array $data): array
    {
        return ['type' => $type->value, 'data' => $data];
    }
}
