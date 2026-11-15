<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItemNeed;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives back to the stock what an order had taken (from the stock, or from the purchases already received), each unit
 * at the cost it left with. A need is released once. Used when an order is cancelled before its delivery.
 */
class ReleaseOrderStock
{
    public function __construct(private RecordStockMovement $stock) {}

    /**
     * @return int Units given back.
     */
    public function forOrder(Order $order, ?User $user = null): int
    {
        return DB::transaction(function () use ($order, $user): int {
            $needs = OrderItemNeed::query()
                ->whereHas('orderItem', fn ($items) => $items->where('order_id', $order->id))
                ->whereNull('released_at')
                ->with('product')
                ->lockForUpdate()
                ->get();

            return $this->release($needs, $user);
        });
    }

    /**
     * A draft purchase order is cancelled: what its needs took from the stock goes back and the needs are removed,
     * so that the next preparation of the purchases starts again from the stock as it is.
     *
     * @return int Units given back.
     */
    public function forPurchaseOrder(PurchaseOrder $purchase, ?User $user = null): int
    {
        return DB::transaction(function () use ($purchase, $user): int {
            $needs = OrderItemNeed::query()
                ->whereIn('purchase_order_item_id', $purchase->items()->select('id'))
                ->whereNull('released_at')
                ->with('product')
                ->lockForUpdate()
                ->get();

            $units = $this->release($needs, $user);

            OrderItemNeed::query()->whereIn('id', $needs->pluck('id'))->delete();

            return $units;
        });
    }

    /**
     * @param  Collection<int, OrderItemNeed>  $needs
     */
    private function release(Collection $needs, ?User $user): int
    {
        $units = 0;

        foreach ($needs as $need) {
            if ($need->quantity_from_stock > 0) {
                $this->stock->handle($need->product, StockMovementType::Release, $need->quantity_from_stock, $need->stock_unit_cost ?? 0, $user, orderItemId: $need->order_item_id);
                $units += $need->quantity_from_stock;
            }

            if ($need->quantity_purchased > 0) {
                $this->stock->handle($need->product, StockMovementType::Release, $need->quantity_purchased, $need->purchase_unit_cost ?? 0, $user, orderItemId: $need->order_item_id);
                $units += $need->quantity_purchased;
            }

            $need->forceFill(['released_at' => now()])->save();
        }

        return $units;
    }
}
