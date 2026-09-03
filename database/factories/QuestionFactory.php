<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        $correctOption = fake()->unique()->word();

        return [
            'chapter_id' => Chapter::factory(),
            'question_type' => QuestionType::Mcq,
            'question_text' => '<p>'.fake()->sentence().'?</p>',
            'options' => [
                ['option' => $correctOption, 'image' => null],
                ['option' => fake()->unique()->word(), 'image' => null],
                ['option' => fake()->unique()->word(), 'image' => null],
                ['option' => fake()->unique()->word(), 'image' => null],
            ],
            'correct_answer' => $correctOption,
            'marks' => 1,
            'difficulty' => Difficulty::Easy,
            // No explicit 'status' here on purpose — QuestionObserver decides
            // it from the currently-authenticated user's role (Admin/Super
            // Admin auto-approve, everyone else defaults to pending), same
            // as the real Filament create flow. Use ->approved()/->rejected()
            // states to force a status regardless of the acting user.
            'created_by' => User::factory()->teacher(),
        ];
    }

    public function cq(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => QuestionType::Cq,
            'options' => null,
            'correct_answer' => null,
            'marks' => 0,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuestionStatus::Approved,
            'approved_by' => User::factory()->admin(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuestionStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
