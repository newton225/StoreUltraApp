<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\OrderStatus;
use App\Exceptions\OrderPricesMissing;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\KitVersion;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Support\Carbon;

function createOrderFor(Client $client, KitVersion $version, int $kits = 3, ?User $author = null): Order
{
    return app(CreateOrder::class)->handle(
        client: $client,
        version: $version,
        kitsCount: $kits,
        deliveryWeek: Carbon::parse('2026-07-13'),
        advanceAmount: 200000,
        author: $author,
    );
}

test('an order freezes the quantities, the prices and the totals', function () {
    $order = createOrderFor(Client::factory()->create(), pricedKit(), 3);

    expect($order->status)->toBe(OrderStatus::Draft);
    expect($order->kits_count)->toBe(3);
    expect($order->advance_due)->toBe(200000);
    expect($order->total_purchase_expected)->toBe(12000);
    expect($order->total_sale)->toBe(16500);
    expect($order->delivery_week->toDateString())->toBe('2026-07-13');
    expect($order->reference)->toBe(sprintf('ORD-%06d', $order->id));

    $items = $order->items()->orderBy('id')->get();

    expect($items)->toHaveCount(2);
    expect($items[0]->quantity)->toBe(6);
    expect($items[0]->unit_purchase_price)->toBe(1000);
    expect($items[0]->line_sale_total)->toBe(9000);
    expect($items[1]->quantity)->toBe(3);
    expect($items[1]->unit_purchase_price)->toBe(2000);
    expect($items[1]->line_sale_total)->toBe(7500);
});

test('a price change after the order does not change the order', function () {
    $version = pricedKit();
    $order = createOrderFor(Client::factory()->create(), $version, 2);

    $product = $version->items->first()->product;
    ProductPrice::factory()->for($product)->create(['purchase_price' => 5000, 'effective_from' => today()]);

    $order->refresh();

    expect($order->total_purchase_expected)->toBe(8000);
    expect($order->items()->orderBy('id')->first()->unit_purchase_price)->toBe(1000);
});

test('a fixed price kit sells at its fixed price for each kit', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000]);
    $version = makeKitVersion([[$product, 1, null]], ['sale_price_mode' => 'fixed', 'fixed_sale_price' => 7000]);

    $order = createOrderFor(Client::factory()->create(), $version, 3);

    expect($order->total_sale)->toBe(21000);
    expect($order->total_purchase_expected)->toBe(3000);
});

test('an order is refused when a price is missing and nothing is saved', function () {
    $product = Product::factory()->create();
    $version = makeKitVersion([[$product, 1, 1000]]);

    createOrderFor(Client::factory()->create(), $version);
})->throws(OrderPricesMissing::class);

test('a refused order leaves no trace', function () {
    $version = makeKitVersion([[Product::factory()->create(), 1, 1000]]);

    try {
        createOrderFor(Client::factory()->create(), $version);
    } catch (OrderPricesMissing) {
        // expected
    }

    expect(Order::count())->toBe(0);
    expect(AuditLog::count())->toBe(0);
});

test('the creation of an order is recorded in the journal', function () {
    $author = User::factory()->create();
    $order = createOrderFor(Client::factory()->create(), pricedKit(), 1, $author);

    $entry = AuditLog::where('action', 'order.created')->sole();

    expect($entry->user_id)->toBe($author->id);
    expect($entry->subject_type)->toBe(Order::class);
    expect($entry->subject_id)->toBe($order->id);
    expect($entry->after['total_sale'])->toBe(5500);
});
