<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => fake()->unique()->numerify('BC-2026-#####'),
            'shop_id' => Shop::factory(),
            'week' => today()->startOfWeek(),
            'status' => 'draft',
            'expected_total' => 0,
            'actual_total' => 0,
            'notes' => null,
            'created_by' => null,
            'sent_at' => null,
        ];
    }
}
