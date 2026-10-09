<?php

namespace Database\Factories;

use App\Enums\ReactivationRequestStatus;
use App\Models\ReactivationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReactivationRequest>
 */
class ReactivationRequestFactory extends Factory
{
    protected $model = ReactivationRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->staff()->suspended(),
            'message' => fake()->sentence(),
            'status' => ReactivationRequestStatus::Pending,
        ];
    }
}
