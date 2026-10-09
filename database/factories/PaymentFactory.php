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

    /**
     * Approved, and still inside its refund period.
     */
    public function refundable(): static
    {
        return $this->state(fn (array $attributes) => ['refundable_until' => now()->addDays(7)]);
    }

    /**
     * A customer's manual payment the Admin hasn't checked yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
        ]);
    }
}
