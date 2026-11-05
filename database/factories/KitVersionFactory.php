<?php

namespace Database\Factories;

use App\Models\Kit;
use App\Models\KitVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitVersion>
 */
class KitVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kit_id' => Kit::factory(),
            'version' => 1,
            'sale_price_mode' => 'lines',
            'fixed_sale_price' => null,
            'valid_from' => today(),
            'created_by' => null,
        ];
    }
}
