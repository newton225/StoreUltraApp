<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseAllocation>
 */
class ExpenseAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'order_id' => Order::factory(),
            'amount' => 1000,
        ];
    }
}
