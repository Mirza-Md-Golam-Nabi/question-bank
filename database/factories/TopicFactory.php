<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'name' => 'Topic '.fake()->unique()->numberBetween(1, 50).': '.fake()->words(2, true),
            'order_index' => 0,
        ];
    }
}
