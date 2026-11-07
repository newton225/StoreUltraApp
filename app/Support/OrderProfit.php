<?php

namespace App\Support;

use App\Models\ExpenseAllocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;

class OrderProfit
{
    /**
     * The figures of one order. See forOrders().
     *
     * @return array<string, int>
     */
    public function forOrder(Order $order): array
    {
        return $this->forOrders(new Collection([$order]))[$order->id];
    }

    /**
     * What each order should have earned and what it really earned.
     *
     * - theoretical margin: the sale price minus the purchase price planned when the order was made;
     * - real margin: what the client really paid, minus what the goods really cost, minus the logistics;
     * - projected margin: the same with the whole sale price, as if the client had paid everything;
     * - gap: projected minus theoretical, i.e. what the real costs took away.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<string, int>>
     */
    public function forOrders(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $ids = $orders->pluck('id')->all();
        $orders->loadMissing('items.purchaseOrder.items');

        $logistics = ExpenseAllocation::query()
            ->selectRaw('order_id, sum(amount) as total')
            ->whereIn('order_id', $ids)
            ->whereHas('expense', fn ($expenses) => $expenses->whereNull('cancelled_at'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $collected = Payment::query()
            ->selectRaw("order_id, coalesce(sum(case when kind = 'refund' then -amount else amount end), 0) as total")
            ->whereIn('order_id', $ids)
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $profits = [];

        foreach ($orders as $order) {
            $actual = $this->actualPurchase($order);
            $orderLogistics = (int) ($logistics[$order->id] ?? 0);
            $paid = (int) ($collected[$order->id] ?? 0);
            $theoretical = $order->total_sale - $order->total_purchase_expected;
            $projected = $order->total_sale - $actual - $orderLogistics;

            $profits[$order->id] = [
                'sale' => $order->total_sale,
                'collected' => $paid,
                'theoretical_purchase' => $order->total_purchase_expected,
                'theoretical_margin' => $theoretical,
                'actual_purchase' => $actual,
                'logistics' => $orderLogistics,
                'real_margin' => $paid - $actual - $orderLogistics,
                'projected_margin' => $projected,
                'gap' => $projected - $theoretical,
            ];
        }

        return $profits;
    }

    /**
     * What the goods of the order really cost: the price paid to the shop when it is known, else the planned one.
     */
    private function actualPurchase(Order $order): int
    {
        $total = 0;

        foreach ($order->items as $item) {
            $total += $this->actualCost($item);
        }

        return $total;
    }

    /**
     * What the goods of one line really cost: the price paid to the shop when it is known, else the planned one.
     * The purchase order of the line (and its items) must be loaded.
     */
    public function actualCost(OrderItem $item): int
    {
        $unitPrice = $item->unit_purchase_price;

        $purchaseItems = $item->purchase_order_id === null
            ? []
            : $item->purchaseOrder->items;

        foreach ($purchaseItems as $purchaseItem) {
            if ($purchaseItem->product_id === $item->product_id
                && $purchaseItem->unit_price_expected === $item->unit_purchase_price
                && $purchaseItem->unit_price_actual !== null) {
                $unitPrice = $purchaseItem->unit_price_actual;

                break;
            }
        }

        return $item->quantity * $unitPrice;
    }
}
