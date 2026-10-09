<?php

namespace Database\Factories;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'student_id' => User::factory()->student(),
            'is_guest' => false,
            'started_at' => now(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => null,
            'is_guest' => true,
            'guest_name' => fake()->name(),
        ]);
    }
}
