<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => fake()->unique()->numerify('REC-2026-#####'),
            'order_id' => Order::factory(),
            'kind' => 'advance',
            'amount' => 1000,
            'payment_method_id' => PaymentMethod::factory(),
            'reference' => null,
            'paid_on' => today(),
            'collected_by' => null,
            'reverses_payment_id' => null,
            'notes' => null,
        ];
    }
}
