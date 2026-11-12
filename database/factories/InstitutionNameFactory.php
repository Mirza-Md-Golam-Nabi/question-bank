<?php

namespace Database\Factories;

use App\Models\InstitutionName;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitutionName>
 */
class InstitutionNameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'name' => fake()->company().' School',
            'is_free' => false,
            'paid_until' => null,
        ];
    }

    /**
     * The first name, which never needs paying for.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => ['is_free' => true]);
    }

    /**
     * An extra name paid for until some days from now.
     */
    public function paid(int $days = 10): static
    {
        return $this->state(fn (array $attributes) => ['paid_until' => now()->addDays($days)]);
    }

    /**
     * An extra name whose paid days are over.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['paid_until' => now()->subDay()]);
    }
}
