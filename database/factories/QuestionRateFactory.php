<?php

namespace Database\Factories;

use App\Models\QuestionRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionRate>
 */
class QuestionRateFactory extends Factory
{
    protected $model = QuestionRate::class;

    public function definition(): array
    {
        return [
            'subject_id' => null,
            'rate_amount' => 10,
            'effective_from' => now()->subDay()->toDateString(),
        ];
    }
}
