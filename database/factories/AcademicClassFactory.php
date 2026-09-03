<?php

namespace Database\Factories;

use App\Models\AcademicClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicClass>
 */
class AcademicClassFactory extends Factory
{
    protected $model = AcademicClass::class;

    public function definition(): array
    {
        return [
            'name' => 'Class '.fake()->unique()->numberBetween(1, 12),
            'order_index' => 0,
        ];
    }
}
