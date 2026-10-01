<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
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
            'user_id' => User::factory(),
            'meeting_pack_id' => MeetingPack::factory(),
            'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->uuid(),
            'stripe_payment_intent_id' => null,
            'amount' => fake()->numberBetween(1000, 50000),
            'quantity' => fake()->numberBetween(1, 10),
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
            'completed_at' => null,
            'failed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Completed,
            'stripe_payment_intent_id' => 'pi_test_'.fake()->unique()->uuid(),
            'completed_at' => now(),
            'failed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'completed_at' => null,
            'failed_at' => now(),
        ]);
    }
}
