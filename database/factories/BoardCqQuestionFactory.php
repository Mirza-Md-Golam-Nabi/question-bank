<?php

namespace Database\Factories;

use App\Models\BoardCqQuestion;
use App\Models\BoardQuestionPaper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardCqQuestion>
 */
class BoardCqQuestionFactory extends Factory
{
    protected $model = BoardCqQuestion::class;

    public function definition(): array
    {
        return [
            'board_question_paper_id' => BoardQuestionPaper::factory(),
            'question_text' => '<p>'.fake()->sentence().'</p>',
            'marks' => 0,
            'order_index' => 0,
        ];
    }
}
