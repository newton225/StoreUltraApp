<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'payment_terms' => null,
            'active' => true,
        ];
    }
}
