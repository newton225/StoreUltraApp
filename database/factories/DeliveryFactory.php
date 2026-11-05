<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => fake()->unique()->numerify('LIV-######'),
            'order_id' => Order::factory(),
            'kits_delivered' => 1,
            'delivered_on' => today(),
            'agent_id' => null,
            'received_by' => fake()->name(),
            'signature_path' => null,
            'signed_at' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
