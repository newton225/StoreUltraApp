<?php

use App\Actions\Orders\TransitionOrder;
use App\Actions\Purchases\CancelPurchaseOrder;
use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Actions\Purchases\ReceivePurchaseOrder;
use App\Actions\Purchases\SendPurchaseOrder;
use App\Actions\Stock\CheckDeliveryStock;
use App\Actions\Stock\RecordStockMovement;
use App\Actions\Stock\ReleaseOrderStock;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItemNeed;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockLevels;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->iron = Product::factory()->for($this->shop)->create(['pack_size' => 10]);
    $this->week = '2026-07-13';
});

function inStock(Product $product, int $quantity, int|float $cost): void
{
    app(RecordStockMovement::class)->handle($product, StockMovementType::Receipt, $quantity, $cost);
}

function onHand(Product $product): int
{
    return app(StockLevels::class)->forProduct($product->id)['on_hand'];
}

function prepare(string $week): array
{
    return app(GeneratePurchaseOrders::class)->handle(Carbon::parse($week));
}

test('the stock serves the oldest order first and only the missing units are bought, in whole packages', function () {
    inStock($this->iron, 30, 1000);
    $first = readyOrder($this->week, [[$this->iron, 20, 1000]]);
    $second = readyOrder($this->week, [[$this->iron, 20, 1000]]);

    prepare($this->week);

    $needFirst = OrderItemNeed::where('order_item_id', $first->items()->value('id'))->sole();
    $needSecond = OrderItemNeed::where('order_item_id', $second->items()->value('id'))->sole();

    expect($needFirst->quantity_from_stock)->toBe(20)
        ->and($needSecond->quantity_from_stock)->toBe(10)
        ->and($needSecond->stock_unit_cost)->toBe(1000)
        ->and(PurchaseOrder::sole()->items()->sole()->quantity_ordered)->toBe(10)
        ->and(onHand($this->iron))->toBe(0);
});

test('an order fully covered by the stock creates no purchase order', function () {
    inStock($this->iron, 50, 1000);
    readyOrder($this->week, [[$this->iron, 20, 1000]]);

    $result = prepare($this->week);

    expect($result['created'])->toBe(0)
        ->and(PurchaseOrder::count())->toBe(0)
        ->and(onHand($this->iron))->toBe(30);
});

test('two preparations never serve the same unit twice', function () {
    inStock($this->iron, 1, 1000);
    $first = readyOrder($this->week, [[$this->iron, 5, 1000]]);
    $second = readyOrder($this->week, [[$this->iron, 5, 1000]]);

    prepare($this->week);
    prepare($this->week);

    expect(OrderItemNeed::sum('quantity_from_stock'))->toBe(1)
        ->and(OrderItemNeed::where('order_item_id', $first->items()->value('id'))->value('quantity_from_stock'))->toBe(1)
        ->and(OrderItemNeed::where('order_item_id', $second->items()->value('id'))->value('quantity_from_stock'))->toBe(0)
        ->and(onHand($this->iron))->toBe(0)
        ->and(StockMovement::where('type', 'allocation')->count())->toBe(1);
});

test('a cancelled order gives back what it took, at the cost it left with', function () {
    inStock($this->iron, 10, 1000);
    inStock($this->iron, 10, 2000);
    $order = readyOrder($this->week, [[$this->iron, 5, 1500]]);
    prepare($this->week);

    expect(onHand($this->iron))->toBe(15);

    app(TransitionOrder::class)->handle($order->refresh(), OrderStatus::Cancelled, 'Annulée', User::factory()->create());

    $level = app(StockLevels::class)->forProduct($this->iron->id);

    expect($level['on_hand'])->toBe(20)->and($level['average_cost'])->toBe(1500)
        ->and(OrderItemNeed::sole()->released_at)->not->toBeNull()
        ->and(StockMovement::where('type', 'release')->sole()->unit_cost)->toBe(1500);
});

test('an order cancelled twice gives back once', function () {
    inStock($this->iron, 10, 1000);
    $order = readyOrder($this->week, [[$this->iron, 5, 1000]]);
    prepare($this->week);
    $user = User::factory()->create();

    app(TransitionOrder::class)->handle($order->refresh(), OrderStatus::Cancelled, 'Annulée', $user);
    app(ReleaseOrderStock::class)->forOrder($order->refresh(), $user);

    expect(onHand($this->iron))->toBe(10)->and(StockMovement::where('type', 'release')->count())->toBe(1);
});

test('cancelling a draft purchase order gives the stock back and lets the next preparation start again', function () {
    inStock($this->iron, 30, 1000);
    readyOrder($this->week, [[$this->iron, 20, 1000]]);
    readyOrder($this->week, [[$this->iron, 20, 1000]]);
    prepare($this->week);

    app(CancelPurchaseOrder::class)->handle(PurchaseOrder::sole());

    expect(onHand($this->iron))->toBe(10)
        ->and(OrderItemNeed::count())->toBe(1);

    prepare($this->week);

    expect(PurchaseOrder::where('status', 'draft')->count())->toBe(1)
        ->and(OrderItemNeed::count())->toBe(2);
});

test('goods received for orders serve the needs and the extra units of the package stay in stock at the price paid', function () {
    $order = readyOrder($this->week, [[$this->iron, 17, 1000]]);
    prepare($this->week);
    $purchase = PurchaseOrder::sole();

    expect($purchase->items()->sole()->quantity_ordered)->toBe(20);

    app(SendPurchaseOrder::class)->handle($purchase);
    app(ReceivePurchaseOrder::class)->handle($purchase->refresh(), [['id' => $purchase->items()->value('id'), 'quantity_received' => 20, 'unit_price_actual' => 1100]]);

    $need = OrderItemNeed::sole();
    $level = app(StockLevels::class)->forProduct($this->iron->id);

    expect($need->quantity_purchased)->toBe(17)
        ->and($need->purchase_unit_cost)->toBe(1100)
        ->and($level['on_hand'])->toBe(3)
        ->and($level['average_cost'])->toBe(1100)
        ->and($order->refresh()->status->value)->toBe('advance_received');
});

test('a partial reception then the cumulative one gives the needs what arrived, never twice', function () {
    readyOrder($this->week, [[$this->iron, 17, 1000]]);
    prepare($this->week);
    $purchase = PurchaseOrder::sole();
    app(SendPurchaseOrder::class)->handle($purchase);
    $itemId = $purchase->items()->value('id');
    $receive = fn (int $quantity) => app(ReceivePurchaseOrder::class)->handle($purchase->refresh(), [['id' => $itemId, 'quantity_received' => $quantity, 'unit_price_actual' => null]]);

    $receive(12);

    expect(OrderItemNeed::sole()->quantity_purchased)->toBe(12)->and(onHand($this->iron))->toBe(0);

    $receive(12);
    $receive(20);

    expect(OrderItemNeed::sole()->quantity_purchased)->toBe(17)->and(onHand($this->iron))->toBe(3);
});

test('changing the package size afterwards changes neither the purchase order nor the stock movements', function () {
    readyOrder($this->week, [[$this->iron, 17, 1000]]);
    prepare($this->week);
    $movements = StockMovement::count();

    $this->iron->update(['pack_size' => 25]);

    expect(PurchaseOrder::sole()->items()->sole()->quantity_ordered)->toBe(20)
        ->and(StockMovement::count())->toBe($movements);
});

test('an order delivered by stock alone can still be moved on by hand', function () {
    inStock($this->iron, 50, 1000);
    $order = readyOrder($this->week, [[$this->iron, 20, 1000]]);
    prepare($this->week);

    expect($order->refresh()->status)->toBe(OrderStatus::AdvanceReceived)
        ->and(Order::count())->toBe(1);
});

test('a delivery is refused when the goods for its kits are not there, and keeps no reservation', function () {
    $order = readyOrder($this->week, [[$this->iron, 30, 1000]]);
    $order->forceFill(['kits_count' => 3, 'status' => 'advance_received'])->save();
    inStock($this->iron, 10, 1000);

    $result = fn (int $kits) => $this->actingAs(User::factory()->create())->post(route('orders.deliveries.store', $order), [
        'kits_delivered' => $kits,
        'delivered_on' => today()->toDateString(),
        'received_by' => 'Moussa',
        'idempotency_key' => 'key-'.$kits,
    ]);

    $result(3)->assertSessionHasErrors('kits_delivered');

    expect(Delivery::count())->toBe(0)
        ->and(OrderItemNeed::count())->toBe(0)
        ->and(onHand($this->iron))->toBe(10);

    // 10 units cover one kit of 10: one kit can go, not two.
    $result(2)->assertSessionHasErrors('kits_delivered');
    $result(1)->assertSessionHasNoErrors();

    expect(Delivery::count())->toBe(1)
        ->and(OrderItemNeed::sole()->quantity_from_stock)->toBe(10)
        ->and(onHand($this->iron))->toBe(0);
});

test('the delivery form tells how many kits the goods allow', function () {
    $order = readyOrder($this->week, [[$this->iron, 30, 1000]]);
    $order->forceFill(['kits_count' => 3, 'status' => 'advance_received'])->save();
    inStock($this->iron, 20, 1000);

    $this->actingAs(User::factory()->owner()->create())->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('delivery.max_kits', 2));
});

test('goods received for the order cover its delivery, and a line prepared before the stock is not checked', function () {
    $order = readyOrder($this->week, [[$this->iron, 20, 1000]]);
    $order->forceFill(['kits_count' => 2])->save();
    prepare($this->week);
    $purchase = PurchaseOrder::sole();
    app(SendPurchaseOrder::class)->handle($purchase);
    app(ReceivePurchaseOrder::class)->handle($purchase->refresh(), [['id' => $purchase->items()->value('id'), 'quantity_received' => 20, 'unit_price_actual' => null]]);

    expect(app(CheckDeliveryStock::class)->maxKits($order->refresh()))->toBe(2);

    $legacy = readyOrder($this->week, [[$this->iron, 5, 1000]]);
    $legacy->items()->update(['purchase_order_id' => $purchase->id]);

    expect(app(CheckDeliveryStock::class)->shortages($legacy, 1, null))->toBe([]);
});
