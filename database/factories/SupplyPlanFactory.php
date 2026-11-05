<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Kit;
use App\Models\SupplyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyPlan>
 */
class SupplyPlanFactory extends Factory
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
            'kit_id' => Kit::factory(),
            'kits_per_cycle' => 3,
            'advance_amount' => 200000,
            'frequency' => 'weekly',
            'starts_on' => today(),
            'ends_on' => null,
            'cycles_count' => null,
            'status' => 'active',
            'created_by' => null,
        ];
    }
}
