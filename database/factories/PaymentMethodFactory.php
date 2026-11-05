<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('????-####'),
            'label_fr' => fake()->words(2, true),
            'label_en' => fake()->words(2, true),
            'requires_reference' => false,
            'active' => true,
        ];
    }
}
