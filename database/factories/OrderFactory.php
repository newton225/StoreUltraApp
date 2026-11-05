<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\KitVersion;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'supply_plan_id' => null,
            'kit_version_id' => KitVersion::factory(),
            'kits_count' => 3,
            'delivery_week' => today()->startOfWeek(),
            'status' => 'draft',
            'advance_due' => 0,
            'total_sale' => 0,
            'total_purchase_expected' => 0,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
