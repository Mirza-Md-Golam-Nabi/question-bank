<?php

namespace Database\Factories;

use App\Enums\PaperSourceType;
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
            'name_bn' => $name.' (bn)',
            'short_name' => strtoupper(substr($name, 0, 3)),
            'short_name_bn' => mb_substr($name, 0, 3).'-bn',
            'order_index' => 0,
        ];
    }

    public function ofType(PaperSourceType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
