<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'created_by' => User::factory()->teacher(),
            'exam_type' => ExamType::TeacherExam,
            'subject_id' => Subject::factory(),
            'duration_minutes' => 30,
            'total_marks' => 0,
            'status' => ExamStatus::Draft,
        ];
    }

    public function selfPractice(): static
    {
        return $this->state(fn (array $attributes) => [
            'exam_type' => ExamType::SelfPractice,
            'created_by' => User::factory()->student(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamStatus::Published,
            'share_token' => str()->random(32),
        ]);
    }
}
