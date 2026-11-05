<?php

use App\Models\Kit;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Shop;
use App\Support\KitPricing;
use App\Support\KitSummary;
use Database\Seeders\InitialCatalogSeeder;
use Database\Seeders\InitialKitSeeder;

function workbookSummary(): KitSummary
{
    test()->seed([InitialCatalogSeeder::class, InitialKitSeeder::class]);

    return app(KitPricing::class)->summary(Kit::firstOrFail()->currentVersion);
}

test('a kit of the workbook costs 91 525 F, sells for 112 200 F and earns 20 675 F', function () {
    $summary = workbookSummary();

    expect($summary->purchase)->toBe(91525);
    expect($summary->sale)->toBe(112200);
    expect($summary->margin)->toBe(20675);
    expect($summary->isComplete())->toBeTrue();
});

test('the totals follow the number of kits: one client, then fifty-six clients', function () {
    $summary = workbookSummary();

    expect($summary->forKits(3))->toBe(['purchase' => 274575, 'sale' => 336600, 'margin' => 62025]);
    expect($summary->forKits(3 * 56))->toBe(['purchase' => 15376200, 'sale' => 18849600, 'margin' => 3473400]);
});

test('the amounts are split between the two shops as in the workbook', function () {
    $summary = workbookSummary();
    $shop1 = Shop::where('name', 'Boutique 1')->value('id');
    $shop2 = Shop::where('name', 'Boutique 2')->value('id');

    expect($summary->byShop[$shop1])->toBe(['purchase' => 86525, 'sale' => 105700]);
    expect($summary->byShop[$shop2])->toBe(['purchase' => 5000, 'sale' => 6500]);
});

test('the purchase price follows the price history', function () {
    $summary = workbookSummary();
    $cement = Product::where('name', 'Ciment CPA 42,5 (sac de 50 kg)')->firstOrFail();

    ProductPrice::factory()->for($cement)->create(['purchase_price' => 5000, 'effective_from' => today()]);

    $updated = app(KitPricing::class)->summary(Kit::firstOrFail()->currentVersion()->first());

    expect($updated->purchase)->toBe($summary->purchase + 7 * (5000 - 4600));
});

test('a product without a price in effect makes the purchase price incomplete', function () {
    $priced = Product::factory()->create();
    ProductPrice::factory()->for($priced)->create(['purchase_price' => 1000]);
    $unpriced = Product::factory()->create();

    $version = makeKitVersion([[$priced, 2, 3000], [$unpriced, 1, 500]]);
    $summary = app(KitPricing::class)->summary($version);

    expect($summary->purchase)->toBeNull();
    expect($summary->margin)->toBeNull();
    expect($summary->missingPurchasePrices)->toBe([$unpriced->id]);
    expect($summary->isComplete())->toBeFalse();
    expect($summary->sale)->toBe(3500);
});

test('a line without a sale price leaves the sale price incomplete', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000]);

    $summary = app(KitPricing::class)->summary(makeKitVersion([[$product, 1, null]]));

    expect($summary->purchase)->toBe(1000);
    expect($summary->sale)->toBeNull();
    expect($summary->margin)->toBeNull();
});

test('a fixed kit price ignores the sale price of the lines', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000]);

    $version = makeKitVersion([[$product, 2, 9999]], [
        'sale_price_mode' => 'fixed',
        'fixed_sale_price' => 4000,
    ]);

    $summary = app(KitPricing::class)->summary($version);

    expect($summary->purchase)->toBe(2000);
    expect($summary->sale)->toBe(4000);
    expect($summary->margin)->toBe(2000);
});
