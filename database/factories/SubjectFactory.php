<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'name_bn' => 'বিষয় '.fake()->unique()->numberBetween(1, 100000),
            'short_name' => fake()->unique()->bothify('???-###'),
        ];
    }
}
