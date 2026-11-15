<?php

namespace App\Models;

use App\Casts\Amount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One product an order line needs, and where each unit comes from: the stock (at the average cost of the moment) or
 * a purchase (at the price really paid, else the planned one). The cost of the line is the sum of its needs.
 *
 * @property int $id
 * @property int $order_item_id
 * @property int $product_id
 * @property int $quantity
 * @property int $quantity_from_stock
 * @property int|float|null $stock_unit_cost
 * @property int $quantity_purchased Units of the purchase already received and given to this need.
 * @property int|float|null $purchase_unit_cost
 * @property int|float $planned_unit_price
 * @property int|null $purchase_order_item_id
 * @property Carbon|null $released_at
 */
#[Fillable(['order_item_id', 'product_id', 'quantity', 'quantity_from_stock', 'stock_unit_cost', 'quantity_purchased', 'purchase_unit_cost', 'planned_unit_price', 'purchase_order_item_id', 'released_at'])]
class OrderItemNeed extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_from_stock' => 'integer',
            'quantity_purchased' => 'integer',
            'stock_unit_cost' => Amount::class,
            'purchase_unit_cost' => Amount::class,
            'planned_unit_price' => Amount::class,
            'released_at' => 'datetime',
        ];
    }

    /**
     * What this need costs: the units taken from the stock at their cost, the others at the price paid, else planned.
     */
    public function cost(): int|float
    {
        $fromStock = $this->quantity_from_stock * ($this->stock_unit_cost ?? 0);
        $bought = ($this->quantity - $this->quantity_from_stock) * ($this->purchase_unit_cost ?? $this->planned_unit_price);

        return $fromStock + $bought;
    }

    /**
     * Units still to come from a purchase and not yet received.
     */
    public function unmet(): int
    {
        return max(0, $this->quantity - $this->quantity_from_stock - $this->quantity_purchased);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
