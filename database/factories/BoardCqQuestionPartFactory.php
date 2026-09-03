<?php

namespace Database\Factories;

use App\Enums\CqPartType;
use App\Models\BoardCqQuestion;
use App\Models\BoardCqQuestionPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardCqQuestionPart>
 */
class BoardCqQuestionPartFactory extends Factory
{
    protected $model = BoardCqQuestionPart::class;

    public function definition(): array
    {
        $partType = fake()->randomElement(CqPartType::ordered());

        return [
            'board_cq_question_id' => BoardCqQuestion::factory(),
            'part_type' => $partType,
            'part_order' => $partType->order(),
            'part_text' => '<p>'.fake()->sentence().'</p>',
            'marks' => 1,
        ];
    }
}
