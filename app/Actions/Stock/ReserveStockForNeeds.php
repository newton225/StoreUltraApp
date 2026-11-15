<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\OrderItemNeed;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Serves the needs of order lines from the stock, product by product, the oldest order line first, at the average
 * cost of the product, and records each need with what is still to be bought. Used when the purchases are prepared
 * and when kits are delivered for lines that were never prepared. Runs inside the caller's transaction.
 */
class ReserveStockForNeeds
{
    public function __construct(private RecordStockMovement $stock) {}

    /**
     * @param  Collection<int, array{order_item_id: int, product_id: int, shop_id: int|null, quantity: int, unit_price: int|float}>  $needs
     * @return Collection<int, array{order_item_id: int, product_id: int, shop_id: int|null, quantity: int, unit_price: int|float, need: OrderItemNeed, shortfall: int}>
     */
    public function handle(Collection $needs, ?User $user): Collection
    {
        $rows = new Collection;

        foreach ($needs->groupBy('product_id') as $productId => $productNeeds) {
            $product = Product::query()->findOrFail($productId);
            $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->first();
            $available = $stock->quantity_on_hand ?? 0;
            $average = $stock->average_cost ?? 0;

            foreach ($productNeeds->sortBy('order_item_id') as $need) {
                $take = min($available, $need['quantity']);

                $model = OrderItemNeed::create([
                    'order_item_id' => $need['order_item_id'],
                    'product_id' => $productId,
                    'quantity' => $need['quantity'],
                    'quantity_from_stock' => $take,
                    'stock_unit_cost' => $take > 0 ? $average : null,
                    'planned_unit_price' => $need['unit_price'],
                ]);

                if ($take > 0) {
                    $this->stock->handle($product, StockMovementType::Allocation, -$take, $average, $user, orderItemId: $need['order_item_id']);
                    $available -= $take;
                }

                $rows->push([...$need, 'need' => $model, 'shortfall' => $need['quantity'] - $take]);
            }
        }

        return $rows;
    }
}
