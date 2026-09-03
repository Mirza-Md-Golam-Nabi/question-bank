<?php

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardQuestionPaper;
use App\Models\ClassSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardQuestionPaper>
 */
class BoardQuestionPaperFactory extends Factory
{
    protected $model = BoardQuestionPaper::class;

    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'class_subject_id' => ClassSubject::factory(),
            'year' => fake()->unique()->numberBetween(2015, 2025),
            'created_by' => User::factory()->admin(),
        ];
    }
}
