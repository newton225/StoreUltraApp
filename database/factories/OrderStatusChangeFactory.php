<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusChange>
 */
class OrderStatusChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from' => 'draft',
            'to' => 'proforma_issued',
            'reason' => null,
            'user_id' => null,
        ];
    }
}
