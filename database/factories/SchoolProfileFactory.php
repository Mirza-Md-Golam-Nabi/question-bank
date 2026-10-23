<?php

namespace Database\Factories;

use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolProfile>
 */
class SchoolProfileFactory extends Factory
{
    protected $model = SchoolProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->school(),
            'name' => fake()->company().' High School',
            'eiin' => (string) fake()->numberBetween(100000, 999999),
            'address' => fake()->city(),
            'phone' => '017'.fake()->numerify('########'),
        ];
    }

    public function connected(string $installationUrl = 'https://school.example.test'): static
    {
        return $this->state(fn (array $attributes) => [
            'installation_url' => $installationUrl,
            'connected_at' => now(),
        ]);
    }
}
