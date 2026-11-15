<?php

use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->week = '2026-07-13';
});

function generateRounded(string $week): array
{
    return app(GeneratePurchaseOrders::class)->handle(Carbon::parse($week));
}

test('a product sold by package is bought in whole packages', function () {
    $iron = Product::factory()->for($this->shop)->create(['pack_size' => 10]);
    readyOrder($this->week, [[$iron, 50, 1000]]);
    readyOrder($this->week, [[$iron, 49, 1000]]);

    generateRounded($this->week);

    $line = PurchaseOrder::sole()->items()->sole();

    expect($line->quantity_ordered)->toBe(100)
        ->and(PurchaseOrder::sole()->expected_total)->toBe(100000);
});

test('a need that is exactly whole packages is not rounded further', function () {
    $iron = Product::factory()->for($this->shop)->create(['pack_size' => 10]);
    readyOrder($this->week, [[$iron, 90, 1000]]);

    generateRounded($this->week);

    expect(PurchaseOrder::sole()->items()->sole()->quantity_ordered)->toBe(90);
});

test('a package is bought at the price of the biggest part of the need', function () {
    $iron = Product::factory()->for($this->shop)->create(['pack_size' => 10]);
    readyOrder($this->week, [[$iron, 30, 1000]]);
    readyOrder($this->week, [[$iron, 5, 1200]]);

    generateRounded($this->week);

    $line = PurchaseOrder::sole()->items()->sole();

    expect($line->quantity_ordered)->toBe(40)
        ->and($line->unit_price_expected)->toBe(1000);
});

test('a product sold one by one keeps one line per frozen price', function () {
    $cement = Product::factory()->for($this->shop)->create();
    readyOrder($this->week, [[$cement, 6, 1000]]);
    readyOrder($this->week, [[$cement, 3, 1200]]);

    generateRounded($this->week);

    expect(PurchaseOrder::sole()->items()->count())->toBe(2)
        ->and(PurchaseOrder::sole()->items()->sum('quantity_ordered'))->toBe(9);
});
