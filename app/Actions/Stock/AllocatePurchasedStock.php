<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\OrderItemNeed;
use App\Models\PurchaseOrderItem;
use App\Models\User;

/**
 * Goods received for customer orders are given to the needs waiting on that purchase line, the oldest order first,
 * at the price paid. What is received beyond the needs (the extra units of a whole package) stays in the stock.
 * Called after the receipt itself has entered the stock.
 */
class AllocatePurchasedStock
{
    public function __construct(private RecordStockMovement $stock) {}

    /**
     * @param  int  $delta  Units received since the last time.
     * @param  int|float  $unitCost  Price really paid for the line, else the planned one.
     */
    public function handle(PurchaseOrderItem $item, int $delta, int|float $unitCost, ?User $user = null): void
    {
        $remaining = $delta;

        $needs = OrderItemNeed::query()
            ->where('purchase_order_item_id', $item->id)
            ->whereNull('released_at')
            ->orderBy('order_item_id')
            ->lockForUpdate()
            ->get();

        foreach ($needs as $need) {
            $take = min($remaining, $need->unmet());

            if ($take <= 0) {
                continue;
            }

            $this->stock->handle($item->product, StockMovementType::Allocation, -$take, $unitCost, $user, orderItemId: $need->order_item_id, purchaseOrderItemId: $item->id);

            $already = $need->quantity_purchased;
            $need->forceFill([
                'quantity_purchased' => $already + $take,
                'purchase_unit_cost' => round(($already * ($need->purchase_unit_cost ?? 0) + $take * $unitCost) / ($already + $take), 2),
            ])->save();

            $remaining -= $take;
        }
    }
}
