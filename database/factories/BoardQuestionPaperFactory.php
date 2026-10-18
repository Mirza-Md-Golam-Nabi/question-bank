<?php

namespace Database\Factories;

use App\Enums\PaperSourceType;
use App\Enums\QuestionStatus;
use App\Models\Board;
use App\Models\BoardMcqQuestion;
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
            'year' => fake()->unique()->numberBetween(2000, 2025),
            'created_by' => User::factory()->admin(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => QuestionStatus::Approved]);
    }

    /**
     * An admission test's paper: every subject together, no class + subject.
     */
    public function admission(PaperSourceType $type = PaperSourceType::Medical): static
    {
        return $this->state([
            'board_id' => Board::factory()->ofType($type),
            'class_subject_id' => null,
        ]);
    }

    /**
     * With this many MCQs, the first option of each correct.
     */
    public function withMcqs(int $count = 3): static
    {
        return $this->afterCreating(function (BoardQuestionPaper $paper) use ($count): void {
            foreach (range(1, $count) as $index) {
                BoardMcqQuestion::factory()->create(['board_question_paper_id' => $paper->id, 'order_index' => $index]);
            }
        });
    }
}
