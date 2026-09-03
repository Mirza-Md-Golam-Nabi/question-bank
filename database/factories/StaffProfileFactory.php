<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->staff(),
            'bank_account_number' => fake()->bankAccountNumber(),
            'bank_name' => fake()->company().' Bank',
            'account_holder_name' => fake()->name(),
        ];
    }
}
