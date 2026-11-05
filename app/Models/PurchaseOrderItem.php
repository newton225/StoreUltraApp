<?php

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product of a purchase order: how many were asked for, how many came, and at which price.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property int $unit_price_expected
 * @property int|null $unit_price_actual
 */
#[Fillable(['purchase_order_id', 'product_id', 'quantity_ordered', 'quantity_received', 'unit_price_expected', 'unit_price_actual'])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_price_expected' => 'integer',
            'unit_price_actual' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The price really paid, or else the expected one.
     */
    public function effectivePrice(): int
    {
        return $this->unit_price_actual ?? $this->unit_price_expected;
    }
}
