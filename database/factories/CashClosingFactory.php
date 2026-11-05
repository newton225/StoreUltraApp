<?php

namespace Database\Factories;

use App\Models\CashClosing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashClosing>
 */
class CashClosingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closed_on' => today(),
            'expected_amount' => 0,
            'counted_amount' => 0,
            'difference' => 0,
            'notes' => null,
            'closed_by' => null,
        ];
    }
}
