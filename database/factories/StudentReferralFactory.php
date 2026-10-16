<?php

namespace Database\Factories;

use App\Enums\ReferralSource;
use App\Models\StudentReferral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentReferral>
 */
class StudentReferralFactory extends Factory
{
    protected $model = StudentReferral::class;

    /**
     * A student under a teacher's referral since now, for 90 days, fixed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'referrer_id' => User::factory()->teacher(),
            'source' => ReferralSource::Exam,
            'exam_id' => null,
            'starts_at' => now(),
            'changeable_until' => null,
            'ends_at' => now()->addDays(90),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(100),
            'ends_at' => now()->subDays(10),
        ]);
    }
}
