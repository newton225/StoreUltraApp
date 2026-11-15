<?php

namespace App\Actions\Stock;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemNeed;
use App\Models\ProductStock;
use App\Models\User;
use App\Support\OrderNeeds;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Whether the goods for the kits about to be delivered are there. What an order needs is served from the stock (taken
 * when the purchases are prepared) and from the goods received for it; a delivery cannot go beyond what is covered.
 * A line never prepared yet (no purchase, no need) is served from the stock on the spot, which is kept only if the
 * delivery goes through. A line prepared before the stock existed (on a purchase order, with no need) is not checked.
 */
class CheckDeliveryStock
{
    public function __construct(private OrderNeeds $needs, private ReserveStockForNeeds $reserve) {}

    /**
     * Reserve what the unprepared lines can take from the stock, then list what is missing to deliver these kits on
     * top of those already delivered. Runs inside the delivery's transaction: a refused delivery keeps no reservation.
     *
     * @return list<array{product: string, missing: int}>
     */
    public function shortages(Order $order, int $kits, ?User $user): array
    {
        $items = $this->items($order);
        $unprepared = $this->unprepared($items);

        if ($unprepared->isNotEmpty()) {
            $this->reserve->handle($this->needs->forItems($unprepared), $user);
        }

        $needs = OrderItemNeed::query()
            ->whereIn('order_item_id', $items->pluck('id'))
            ->whereNull('released_at')
            ->with('product:id,name')
            ->get();

        $shortages = [];

        foreach ($needs as $need) {
            $missing = $this->required($need->quantity, $order, $kits) - ($need->quantity_from_stock + $need->quantity_purchased);

            if ($missing > 0) {
                $shortages[$need->product_id] = [
                    'product' => $need->product->name,
                    'missing' => ($shortages[$need->product_id]['missing'] ?? 0) + $missing,
                ];
            }
        }

        return array_values($shortages);
    }

    /**
     * How many kits could be delivered now, from what is covered and the free stock (nothing is reserved here).
     * Used to tell the person, before they try, how far they can go.
     */
    public function maxKits(Order $order): int
    {
        $remaining = $order->remainingKits();
        $items = $this->items($order);
        $covered = [];

        foreach (OrderItemNeed::query()->whereIn('order_item_id', $items->pluck('id'))->whereNull('released_at')->get() as $need) {
            $covered[] = ['quantity' => $need->quantity, 'covered' => $need->quantity_from_stock + $need->quantity_purchased];
        }

        $free = [];

        foreach ($this->needs->forItems($this->unprepared($items))->sortBy('order_item_id') as $need) {
            $free[$need['product_id']] ??= ProductStock::query()->where('product_id', $need['product_id'])->value('quantity_on_hand') ?? 0;
            $take = min($free[$need['product_id']], $need['quantity']);
            $free[$need['product_id']] -= $take;
            $covered[] = ['quantity' => $need['quantity'], 'covered' => $take];
        }

        for ($kits = $remaining; $kits >= 1; $kits--) {
            if (collect($covered)->every(fn (array $need): bool => $this->required($need['quantity'], $order, $kits) <= $need['covered'])) {
                return $kits;
            }
        }

        return 0;
    }

    /**
     * What a need must have covered for the kits already delivered plus these ones.
     */
    private function required(int $quantity, Order $order, int $kits): int
    {
        $delivered = $order->kits_count - $order->remainingKits();

        return (int) ceil($quantity * ($delivered + $kits) / max(1, $order->kits_count));
    }

    /**
     * @return EloquentCollection<int, OrderItem>
     */
    private function items(Order $order): EloquentCollection
    {
        return $order->items()->with('product.components.component.currentPrice')->get();
    }

    /**
     * The lines that were never prepared: no need, and not on a purchase order made before the stock existed.
     *
     * @param  EloquentCollection<int, OrderItem>  $items
     * @return EloquentCollection<int, OrderItem>
     */
    private function unprepared(EloquentCollection $items): EloquentCollection
    {
        $prepared = OrderItemNeed::query()->whereIn('order_item_id', $items->pluck('id'))->pluck('order_item_id')->flip();

        return $items->filter(fn (OrderItem $item): bool => ! $prepared->has($item->id) && $item->purchase_order_id === null)->values();
    }
}
