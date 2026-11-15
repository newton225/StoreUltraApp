<?php

namespace App\Support;

use App\Models\Product;

/**
 * What a unit of a product costs to buy today. A product bought as it is costs its current purchase price;
 * a manufactured product costs the sum of its components (quantity x current purchase price of each).
 */
class ProductPricing
{
    /**
     * Null when a price is missing (the product, or one of its components, has none in effect).
     */
    public function purchasePrice(Product $product): int|float|null
    {
        $product->loadMissing('components.component.currentPrice', 'currentPrice');

        if (! $product->isManufactured()) {
            return $product->currentPrice?->purchase_price;
        }

        $total = 0.0;

        foreach ($product->components as $component) {
            $price = $component->component->currentPrice?->purchase_price;

            if ($price === null) {
                return null;
            }

            $total += $component->quantity * $price;
        }

        return KitSummary::tidy($total);
    }
}
