<?php

namespace App\Models;

use Database\Factories\ExpenseAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The part of an expense carried by one order.
 *
 * @property int $id
 * @property int $expense_id
 * @property int $order_id
 * @property int $amount
 */
#[Fillable(['expense_id', 'order_id', 'amount'])]
class ExpenseAllocation extends Model
{
    /** @use HasFactory<ExpenseAllocationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
