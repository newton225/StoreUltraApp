<?php

namespace App\Support;

use App\Models\KitVersion;

class KitPricing
{
    /**
     * What one kit of this version costs and sells for, at the prices in effect today.
     */
    public function summary(KitVersion $version): KitSummary
    {
        $version->loadMissing('items.product.currentPrice');

        $purchase = 0;
        $lineSales = 0;
        $missingPurchasePrices = [];
        $missingLineSale = false;
        $byShop = [];

        foreach ($version->items as $item) {
            $product = $item->product;
            $price = $product->currentPrice;

            $linePurchase = null;

            if ($price === null) {
                $missingPurchasePrices[] = $product->id;
            } else {
                $linePurchase = $item->quantity_per_kit * $price->purchase_price;
                $purchase += $linePurchase;
            }

            if ($item->sale_price_per_kit === null) {
                $missingLineSale = true;
            } else {
                $lineSales += $item->sale_price_per_kit;
            }

            if ($product->shop_id !== null) {
                $byShop[$product->shop_id] ??= ['purchase' => 0, 'sale' => 0];
                $byShop[$product->shop_id]['purchase'] += $linePurchase ?? 0;
                $byShop[$product->shop_id]['sale'] += $item->sale_price_per_kit ?? 0;
            }
        }

        $sale = $version->sale_price_mode === KitVersion::MODE_FIXED
            ? $version->fixed_sale_price
            : ($missingLineSale ? null : $lineSales);

        return new KitSummary(
            purchase: $missingPurchasePrices === [] ? $purchase : null,
            sale: $sale,
            missingPurchasePrices: $missingPurchasePrices,
            byShop: $byShop,
        );
    }
}
