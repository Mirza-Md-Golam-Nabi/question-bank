<?php

namespace Database\Factories;

use App\Enums\EditorMode;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMcqStimulus>
 */
class BoardMcqStimulusFactory extends Factory
{
    protected $model = BoardMcqStimulus::class;

    public function definition(): array
    {
        return [
            'board_question_paper_id' => BoardQuestionPaper::factory(),
            'text' => '<p>'.fake()->sentence().'</p>',
            'editor_mode' => EditorMode::CkEditor,
        ];
    }
}
