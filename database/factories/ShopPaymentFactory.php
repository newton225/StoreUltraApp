<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Shop;
use App\Models\ShopPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopPayment>
 */
class ShopPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'purchase_order_id' => null,
            'kind' => 'payment',
            'amount' => 1000,
            'payment_method_id' => PaymentMethod::factory(),
            'reference' => null,
            'paid_on' => today(),
            'paid_by' => null,
            'notes' => null,
        ];
    }
}
