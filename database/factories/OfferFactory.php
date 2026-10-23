<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(12),
            'target_roles' => [UserRole::Student->value, UserRole::Teacher->value],
            'is_active' => true,
        ];
    }

    /**
     * @param  array<int, UserRole>  $roles
     */
    public function forRoles(array|UserRole $roles): static
    {
        return $this->state(fn (array $attributes) => [
            'target_roles' => array_map(fn (UserRole $role): string => $role->value, is_array($roles) ? $roles : [$roles]),
        ]);
    }
}
