<?php

namespace App\Support;

use App\Models\ProductStock;

/**
 * Read-only view of the stock of a product.
 */
class StockLevels
{
    /**
     * @return array{on_hand: int, average_cost: int|float, value: int}
     */
    public function forProduct(int $productId): array
    {
        $stock = ProductStock::query()->where('product_id', $productId)->first();

        $onHand = $stock?->quantity_on_hand ?? 0;
        $average = $stock?->average_cost ?? 0;

        return ['on_hand' => $onHand, 'average_cost' => $average, 'value' => (int) round($onHand * $average)];
    }
}
