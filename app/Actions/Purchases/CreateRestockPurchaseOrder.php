<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\User;
use App\Support\DocumentNumbers;
use App\Support\ProductPricing;
use App\Support\WeekCalendar;

/**
 * A purchase order made by hand to fill the stock: no customer order is behind it. The quantities are asked in whole
 * packages, at the current purchase price of each product. What is received enters the stock (ReceivePurchaseOrder).
 */
class CreateRestockPurchaseOrder
{
    public function __construct(private DocumentNumbers $numbers, private WeekCalendar $calendar, private ProductPricing $pricing) {}

    /**
     * @param  list<array{product_id: int, packs: int}>  $lines
     */
    public function handle(Shop $shop, array $lines, ?User $user = null): PurchaseOrder
    {
        $products = Product::query()->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');

        $purchase = PurchaseOrder::create([
            'reference' => $this->numbers->next('purchase_order'),
            'shop_id' => $shop->id,
            'week' => $this->calendar->startOf(now())->toDateString(),
            'status' => PurchaseOrderStatus::Draft,
            'kind' => PurchaseOrder::KIND_RESTOCK,
            'created_by' => $user?->id,
        ]);

        $expected = 0;

        foreach ($lines as $line) {
            $product = $products[$line['product_id']];
            $quantity = $line['packs'] * $product->pack_size;
            $price = $this->pricing->purchasePrice($product) ?? 0;

            $purchase->items()->create([
                'product_id' => $product->id,
                'quantity_ordered' => $quantity,
                'unit_price_expected' => $price,
            ]);

            $expected += $quantity * $price;
        }

        $expected = (int) round($expected);

        $purchase->forceFill(['expected_total' => $expected])->save();

        AuditLog::record('purchase_order.created', $purchase, [], [
            'reference' => $purchase->reference,
            'shop_id' => $shop->id,
            'kind' => PurchaseOrder::KIND_RESTOCK,
            'expected_total' => $expected,
        ], $user);

        return $purchase;
    }
}
