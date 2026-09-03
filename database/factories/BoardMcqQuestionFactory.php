<?php

namespace Database\Factories;

use App\Models\BoardMcqQuestion;
use App\Models\BoardQuestionPaper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMcqQuestion>
 */
class BoardMcqQuestionFactory extends Factory
{
    protected $model = BoardMcqQuestion::class;

    public function definition(): array
    {
        $correctOption = fake()->unique()->word();

        return [
            'board_question_paper_id' => BoardQuestionPaper::factory(),
            'question_text' => '<p>'.fake()->sentence().'?</p>',
            'options' => [
                ['option' => $correctOption, 'image' => null],
                ['option' => fake()->unique()->word(), 'image' => null],
            ],
            'correct_answer' => $correctOption,
            'marks' => 1,
            'order_index' => 0,
        ];
    }
}
