<?php

namespace Database\Factories;

use App\Models\KitVersion;
use App\Models\KitVersionItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KitVersionItem>
 */
class KitVersionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kit_version_id' => KitVersion::factory(),
            'product_id' => Product::factory(),
            'quantity_per_kit' => 1,
            'sale_price_per_kit' => null,
        ];
    }
}
