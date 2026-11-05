<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('FAC-2026-#####'),
            'order_id' => Order::factory(),
            'client_id' => Client::factory(),
            'issued_on' => today(),
            'total' => 0,
            'paid_amount' => 0,
            'fne_status' => 'not_required',
            'fne_number' => null,
            'created_by' => null,
        ];
    }
}
