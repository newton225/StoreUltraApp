<?php

namespace Database\Factories;

use App\Models\CashMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direction' => CashMovement::IN,
            'amount' => 10000,
            'occurred_on' => today(),
            'label' => fake()->sentence(3),
            'counterparty' => null,
            'document_reference' => null,
            'notes' => null,
            'idempotency_key' => null,
            'recorded_by' => null,
        ];
    }

    /**
     * Money taken out of the cash box.
     */
    public function out(): static
    {
        return $this->state(['direction' => CashMovement::OUT]);
    }
}
