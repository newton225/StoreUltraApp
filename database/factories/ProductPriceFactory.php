<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'shop_id' => null,
            'purchase_price' => fake()->numberBetween(500, 50000),
            'sale_price' => null,
            'effective_from' => today(),
            'created_by' => null,
        ];
    }
}
