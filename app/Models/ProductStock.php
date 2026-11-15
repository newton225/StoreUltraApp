<?php

namespace App\Models;

use App\Casts\Amount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What is in stock for a product and what a unit of it costs on average. Written only by RecordStockMovement,
 * under a lock, in the same transaction as the movement.
 *
 * @property int $id
 * @property int $product_id
 * @property int $quantity_on_hand
 * @property int|float $average_cost
 */
#[Fillable(['product_id', 'quantity_on_hand', 'average_cost'])]
class ProductStock extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['quantity_on_hand' => 'integer', 'average_cost' => Amount::class];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
