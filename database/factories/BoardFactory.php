<?php

namespace Database\Factories;

use App\Models\Board;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
class BoardFactory extends Factory
{
    protected $model = Board::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Board';

        return [
            'name' => $name,
            'short_name' => strtoupper(substr($name, 0, 3)),
            'order_index' => 0,
        ];
    }
}
