<?php

use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductPrice;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Support\OrderNeeds;
use App\Support\ProductPricing;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->bar = Product::factory()->for($this->shop)->create();
    $this->cement = Product::factory()->for($this->shop)->create();
    ProductPrice::factory()->for($this->bar)->create(['purchase_price' => 1000]);
    ProductPrice::factory()->for($this->cement)->create(['purchase_price' => 4000]);
    // A manufactured panel: 2 bars and half a bag of cement.
    $this->panel = Product::factory()->create(['shop_id' => null]);
    ProductComponent::factory()->create(['product_id' => $this->panel->id, 'component_product_id' => $this->bar->id, 'quantity' => 2]);
    ProductComponent::factory()->create(['product_id' => $this->panel->id, 'component_product_id' => $this->cement->id, 'quantity' => 0.5]);
    $this->week = '2026-07-13';
});

test('the purchase price of a manufactured product is the sum of its components', function () {
    expect(app(ProductPricing::class)->purchasePrice($this->panel))->toBe(4000)
        ->and(app(ProductPricing::class)->purchasePrice($this->bar))->toBe(1000);
});

test('a manufactured product with a component without price has no purchase price', function () {
    $unpriced = Product::factory()->for($this->shop)->create();
    ProductComponent::factory()->create(['product_id' => $this->panel->id, 'component_product_id' => $unpriced->id, 'quantity' => 1]);

    expect(app(ProductPricing::class)->purchasePrice($this->panel->fresh()))->toBeNull();
});

test('the needs of a manufactured line are its components, rounded up to whole units', function () {
    $order = readyOrder($this->week, [[$this->panel, 9, 4000]]);

    $needs = app(OrderNeeds::class)->forItems($order->items()->get())->keyBy('product_id');

    expect($needs)->toHaveCount(2)
        ->and($needs[$this->bar->id]['quantity'])->toBe(18)
        ->and($needs[$this->cement->id]['quantity'])->toBe(5)
        ->and($needs[$this->bar->id]['shop_id'])->toBe($this->shop->id)
        ->and($needs[$this->bar->id]['unit_price'])->toBe(1000);
});

test('the purchase order buys the components, never the manufactured product', function () {
    readyOrder($this->week, [[$this->panel, 9, 4000], [$this->bar, 2, 1000]]);

    $result = app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));

    $lines = PurchaseOrder::sole()->items()->get()->keyBy('product_id');

    expect($result)->toBe(['created' => 1, 'unassigned' => 0])
        ->and($lines->has($this->panel->id))->toBeFalse()
        ->and($lines[$this->bar->id]->quantity_ordered)->toBe(20)
        ->and($lines[$this->cement->id]->quantity_ordered)->toBe(5);
});

test('a component without a shop is counted as unassigned and does not stop the others', function () {
    $loose = Product::factory()->create(['shop_id' => null]);
    ProductComponent::factory()->create(['product_id' => $this->panel->id, 'component_product_id' => $loose->id, 'quantity' => 1]);
    readyOrder($this->week, [[$this->panel->fresh(), 3, 4000]]);

    $result = app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));

    expect($result)->toBe(['created' => 1, 'unassigned' => 1])
        ->and(PurchaseOrder::sole()->items()->count())->toBe(2);
});

test('a manufactured line is taken once even when its components come from two shops', function () {
    $other = Shop::factory()->create();
    $panel = Product::factory()->create(['shop_id' => null]);
    $paint = Product::factory()->for($other)->create();
    ProductPrice::factory()->for($paint)->create(['purchase_price' => 500]);
    ProductComponent::factory()->create(['product_id' => $panel->id, 'component_product_id' => $this->bar->id, 'quantity' => 1]);
    ProductComponent::factory()->create(['product_id' => $panel->id, 'component_product_id' => $paint->id, 'quantity' => 1]);
    readyOrder($this->week, [[$panel, 4, 1500]]);

    $first = app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $second = app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));

    expect($first['created'])->toBe(2)->and($second['created'])->toBe(0);
});
