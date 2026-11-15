<?php

use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Actions\Stock\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\OrderItem;
use App\Models\OrderItemNeed;
use App\Models\Product;
use App\Models\Shop;
use App\Support\OrderProfit;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->iron = Product::factory()->for($this->shop)->create();
    $this->week = '2026-07-13';
});

function needFor(OrderItem $item, array $attributes): OrderItemNeed
{
    return OrderItemNeed::create([
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'quantity' => $item->quantity,
        'planned_unit_price' => $item->unit_purchase_price,
        ...$attributes,
    ]);
}

test('the cost of a line is what the stock units cost plus what was paid for the others', function () {
    $item = readyOrder($this->week, [[$this->iron, 20, 1500]])->items()->sole();
    needFor($item, ['quantity_from_stock' => 12, 'stock_unit_cost' => 1000, 'quantity_purchased' => 8, 'purchase_unit_cost' => 1100]);

    expect(app(OrderProfit::class)->actualCost($item->refresh()))->toBe(12 * 1000 + 8 * 1100);
});

test('units not bought yet are costed at the planned price', function () {
    $item = readyOrder($this->week, [[$this->iron, 20, 1500]])->items()->sole();
    needFor($item, ['quantity_from_stock' => 12, 'stock_unit_cost' => 1000]);

    expect(app(OrderProfit::class)->actualCost($item->refresh()))->toBe(12 * 1000 + 8 * 1500);
});

test('a line without needs keeps the previous calculation', function () {
    $item = readyOrder($this->week, [[$this->iron, 20, 1000]])->items()->sole();

    expect(app(OrderProfit::class)->actualCost($item))->toBe(20000);
});

test('the extra units of a package belong to no order: they are in no cost', function () {
    $this->iron->update(['pack_size' => 10]);
    $order = readyOrder($this->week, [[$this->iron, 17, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));

    // 20 units are bought for 17 needed: the order is costed for its 17 units only.
    expect(app(OrderProfit::class)->actualCost($order->items()->sole()->refresh()))->toBe(17000);
});

test('the stock never changes what the client is charged', function () {
    app(RecordStockMovement::class)->handle($this->iron, StockMovementType::Receipt, 30, 100);
    $order = readyOrder($this->week, [[$this->iron, 20, 1000]]);
    $order->items()->update(['line_sale_total' => 45000]);
    $order->forceFill(['total_sale' => 45000])->save();

    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));

    expect($order->items()->sole()->line_sale_total)->toBe(45000)
        ->and($order->refresh()->total_sale)->toBe(45000)
        ->and(app(OrderProfit::class)->actualCost($order->items()->sole()->refresh()))->toBe(2000);
});
