<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'amount' => 500,
            'gateway' => PaymentGateway::Manual,
            'status' => PaymentStatus::Success,
            'paid_at' => now(),
        ];
    }
}
