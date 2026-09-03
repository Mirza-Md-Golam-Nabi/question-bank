<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\ClassSubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    public function definition(): array
    {
        return [
            'class_subject_id' => ClassSubject::factory(),
            'name' => 'Chapter '.fake()->unique()->numberBetween(1, 20).': '.fake()->words(2, true),
            'order_index' => 0,
        ];
    }
}
