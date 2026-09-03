<?php

namespace Database\Factories;

use App\Enums\CqPartType;
use App\Models\Question;
use App\Models\QuestionCqPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionCqPart>
 */
class QuestionCqPartFactory extends Factory
{
    protected $model = QuestionCqPart::class;

    public function definition(): array
    {
        $partType = fake()->randomElement(CqPartType::ordered());

        return [
            'question_id' => Question::factory()->cq(),
            'part_type' => $partType,
            'part_order' => $partType->order(),
            'part_text' => '<p>'.fake()->sentence().'</p>',
            'marks' => 1,
        ];
    }
}
