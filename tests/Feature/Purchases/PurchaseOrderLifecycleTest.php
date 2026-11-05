<?php

use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop1 = Shop::factory()->create();
    $this->shop2 = Shop::factory()->create();
    $this->iron = Product::factory()->for($this->shop1)->create();
    $this->panel = Product::factory()->for($this->shop2)->create();
    $this->week = '2026-07-13';
    $this->employee = User::factory()->create();
});

function purchaseFor(Shop $shop): PurchaseOrder
{
    return PurchaseOrder::where('shop_id', $shop->id)->firstOrFail();
}

/**
 * @param  array<int, int>  $quantities
 * @param  array<int, int>  $prices
 * @return array<string, mixed>
 */
function receivePayload(PurchaseOrder $purchase, array $quantities, array $prices = []): array
{
    return [
        'items' => $purchase->items()->orderBy('id')->get()->map(fn ($item, $index) => [
            'id' => $item->id,
            'quantity_received' => $quantities[$index],
            'unit_price_actual' => $prices[$index] ?? null,
        ])->all(),
    ];
}

test('sending a purchase order moves the orders it fully covers to ordered', function () {
    $single = readyOrder($this->week, [[$this->iron, 4, 1000]]);
    $double = readyOrder($this->week, [[$this->iron, 2, 1000], [$this->panel, 2, 2000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $this->actingAs($this->employee);

    $this->post(route('purchases.send', purchaseFor($this->shop1)))->assertSessionHasNoErrors();

    expect(purchaseFor($this->shop1)->status->value)->toBe('sent');
    expect(purchaseFor($this->shop1)->sent_at)->not->toBeNull();
    expect($single->refresh()->status->value)->toBe('ordered');
    expect($double->refresh()->status->value)->toBe('advance_received');

    $this->post(route('purchases.send', purchaseFor($this->shop2)))->assertSessionHasNoErrors();

    expect($double->refresh()->status->value)->toBe('ordered');
    expect($double->statusChanges()->sole()->user_id)->toBe($this->employee->id);
});

test('a purchase order is sent only once', function () {
    readyOrder($this->week, [[$this->iron, 1, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $this->actingAs($this->employee);

    $this->post(route('purchases.send', purchaseFor($this->shop1)));
    $this->post(route('purchases.send', purchaseFor($this->shop1)))->assertSessionHasErrors('status');
});

test('a partial reception is recorded and the orders keep waiting', function () {
    $order = readyOrder($this->week, [[$this->iron, 6, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = purchaseFor($this->shop1);
    $this->actingAs($this->employee);
    $this->post(route('purchases.send', $purchase));

    $this->post(route('purchases.receive', $purchase), receivePayload($purchase, [4]))->assertSessionHasNoErrors();

    $purchase->refresh();

    expect($purchase->status->value)->toBe('partially_received');
    expect($purchase->items->sole()->quantity_received)->toBe(4);
    expect($purchase->actual_total)->toBe(4000);
    expect($order->refresh()->status->value)->toBe('ordered');
});

test('the full reception moves the orders to received', function () {
    $order = readyOrder($this->week, [[$this->iron, 6, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = purchaseFor($this->shop1);
    $this->actingAs($this->employee);
    $this->post(route('purchases.send', $purchase));
    $this->post(route('purchases.receive', $purchase), receivePayload($purchase, [4]));

    $this->post(route('purchases.receive', $purchase), receivePayload($purchase, [6]))->assertSessionHasNoErrors();

    expect($purchase->refresh()->status->value)->toBe('received');
    expect($order->refresh()->status->value)->toBe('received');
    expect(AuditLog::where('action', 'purchase_order.received')->count())->toBe(2);
});

test('an order waits for every shop before it is received', function () {
    $order = readyOrder($this->week, [[$this->iron, 2, 1000], [$this->panel, 2, 2000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $this->actingAs($this->employee);

    foreach ([$this->shop1, $this->shop2] as $shop) {
        $this->post(route('purchases.send', purchaseFor($shop)));
    }

    $this->post(route('purchases.receive', purchaseFor($this->shop1)), receivePayload(purchaseFor($this->shop1), [2]));
    expect($order->refresh()->status->value)->toBe('ordered');

    $this->post(route('purchases.receive', purchaseFor($this->shop2)), receivePayload(purchaseFor($this->shop2), [2]));
    expect($order->refresh()->status->value)->toBe('received');
});

test('the price really paid replaces the expected one in the actual total', function () {
    readyOrder($this->week, [[$this->iron, 5, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = purchaseFor($this->shop1);
    $this->actingAs($this->employee);
    $this->post(route('purchases.send', $purchase));

    $this->post(route('purchases.receive', $purchase), receivePayload($purchase, [5], [1200]));

    $purchase->refresh();

    expect($purchase->expected_total)->toBe(5000);
    expect($purchase->actual_total)->toBe(6000);
    expect($purchase->items->sole()->unit_price_actual)->toBe(1200);
});

test('a reception is rejected for invalid data', function (Closure $mutate, string $field) {
    readyOrder($this->week, [[$this->iron, 6, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = purchaseFor($this->shop1);
    $this->actingAs($this->employee);
    $this->post(route('purchases.send', $purchase));
    $this->post(route('purchases.receive', $purchase), receivePayload($purchase, [3]));

    $this->post(route('purchases.receive', $purchase), $mutate(receivePayload($purchase, [5])))
        ->assertSessionHasErrors($field);

    expect($purchase->refresh()->items->sole()->quantity_received)->toBe(3);
})->with([
    'more than ordered' => [fn (array $p) => data_set($p, 'items.0.quantity_received', 7), 'items.0.quantity_received'],
    'less than already received' => [fn (array $p) => data_set($p, 'items.0.quantity_received', 2), 'items.0.quantity_received'],
    'negative price' => [fn (array $p) => data_set($p, 'items.0.unit_price_actual', -1), 'items.0.unit_price_actual'],
    'unknown line' => [fn (array $p) => data_set($p, 'items.0.id', 9999), 'items.0.id'],
]);

test('nothing is received on a purchase order that was not sent', function () {
    readyOrder($this->week, [[$this->iron, 2, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = purchaseFor($this->shop1);

    $this->actingAs($this->employee)
        ->post(route('purchases.receive', $purchase), receivePayload($purchase, [1]))
        ->assertSessionHasErrors('status');

    expect($purchase->refresh()->items->sole()->quantity_received)->toBe(0);
});

test('a draft can be cancelled and its lines are free to be bought again', function () {
    readyOrder($this->week, [[$this->iron, 2, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = purchaseFor($this->shop1);

    $this->actingAs($this->employee)->post(route('purchases.cancel', $purchase))->assertSessionHasNoErrors();

    expect($purchase->refresh()->status->value)->toBe('cancelled');
    expect(app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week))['created'])->toBe(1);
});

test('a purchase order that was sent cannot be cancelled', function () {
    readyOrder($this->week, [[$this->iron, 2, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = purchaseFor($this->shop1);
    $this->actingAs($this->employee);
    $this->post(route('purchases.send', $purchase));

    $this->post(route('purchases.cancel', $purchase))->assertSessionHasErrors('status');

    expect($purchase->refresh()->status->value)->toBe('sent');
});

test('a field agent cannot act on purchase orders', function () {
    $purchase = PurchaseOrder::factory()->create();
    $this->actingAs(User::factory()->agent()->create());

    $this->post(route('purchases.send', $purchase))->assertForbidden();
    $this->post(route('purchases.receive', $purchase), [])->assertForbidden();
    $this->post(route('purchases.cancel', $purchase))->assertForbidden();
    $this->get(route('purchases.show', $purchase))->assertForbidden();
});

test('orders of another week are not touched', function () {
    $other = readyOrder('2026-07-20', [[$this->iron, 1, 1000]]);
    readyOrder($this->week, [[$this->iron, 1, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $this->actingAs($this->employee)->post(route('purchases.send', purchaseFor($this->shop1)));

    expect($other->refresh()->status->value)->toBe('advance_received');
    expect(Order::where('status', 'ordered')->count())->toBe(1);
});
