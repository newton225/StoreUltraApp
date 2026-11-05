<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_type_id' => ExpenseType::factory(),
            'amount' => 1000,
            'incurred_on' => today(),
            'scope' => 'week',
            'week' => today()->startOfWeek(),
            'created_by' => null,
        ];
    }
}
