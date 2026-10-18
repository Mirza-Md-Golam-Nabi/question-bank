<?php

namespace Database\Factories;

use App\Enums\EditorMode;
use App\Models\Chapter;
use App\Models\QuestionStimulus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionStimulus>
 */
class QuestionStimulusFactory extends Factory
{
    protected $model = QuestionStimulus::class;

    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'text' => '<p>'.fake()->sentence().'</p>',
            'editor_mode' => EditorMode::CkEditor,
            'created_by' => User::factory()->teacher(),
        ];
    }
}
