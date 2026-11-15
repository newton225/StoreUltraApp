<?php

namespace App\Support;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * What has to be bought to deliver order lines. A line of a product bought as it is needs that product
 * (at the shop and price frozen on the line); a line of a manufactured product needs its components, in
 * quantity rounded up to whole units, at the shop and the current price of each component.
 */
class OrderNeeds
{
    /**
     * @param  EloquentCollection<int, OrderItem>  $items
     * @return Collection<int, array{order_item_id: int, product_id: int, shop_id: int|null, quantity: int, unit_price: int|float}>
     */
    public function forItems(EloquentCollection $items): Collection
    {
        $items->loadMissing('product.components.component.currentPrice');

        $needs = new Collection;

        foreach ($items as $item) {
            if (! $item->product->isManufactured()) {
                $needs->push([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'shop_id' => $item->shop_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_purchase_price,
                ]);

                continue;
            }

            foreach ($item->product->components as $component) {
                $needs->push([
                    'order_item_id' => $item->id,
                    'product_id' => $component->component_product_id,
                    'shop_id' => $component->component->shop_id,
                    'quantity' => (int) ceil($item->quantity * $component->quantity),
                    'unit_price' => $component->component->currentPrice?->purchase_price ?? 0,
                ]);
            }
        }

        return $needs;
    }
}
