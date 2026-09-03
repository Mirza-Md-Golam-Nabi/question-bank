<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\StaffEarning;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffEarning>
 */
class StaffEarningFactory extends Factory
{
    protected $model = StaffEarning::class;

    public function definition(): array
    {
        return [
            'staff_id' => User::factory()->staff(),
            'question_id' => Question::factory(),
            'amount' => 10,
        ];
    }
}
