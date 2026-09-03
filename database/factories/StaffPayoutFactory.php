<?php

namespace Database\Factories;

use App\Models\StaffPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffPayout>
 */
class StaffPayoutFactory extends Factory
{
    protected $model = StaffPayout::class;

    public function definition(): array
    {
        return [
            'staff_id' => User::factory()->staff(),
            'total_amount' => 10,
            'paid_by' => User::factory()->admin(),
        ];
    }
}
