<?php

namespace Database\Factories;

use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'provider_order_id' => 'ORD-'.strtoupper(bin2hex(random_bytes(12))),
            'payment_url' => fake()->url(),
            'usage_limit' => 1,
            'status' => 'created',
            'discount_amount' => 0,
            'payment_method' => 'qris',
            'fee_amount' => 0,
            'fee_percentage' => 0,
            'provider_response' => null,
        ];
    }
}
