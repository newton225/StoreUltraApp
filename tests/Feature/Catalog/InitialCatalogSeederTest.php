<?php

use App\Models\Product;
use App\Models\Shop;
use Database\Seeders\InitialCatalogSeeder;

test('the seeder imports the two shops and the twelve products of the workbook', function () {
    $this->seed(InitialCatalogSeeder::class);

    expect(Shop::count())->toBe(2);
    expect(Product::count())->toBe(12);
    expect(Product::whereDoesntHave('prices')->count())->toBe(0);
});

test('each product goes to the right shop with its purchase price', function () {
    $this->seed(InitialCatalogSeeder::class);

    $cement = Product::where('name', 'Ciment CPA 42,5 (sac de 50 kg)')->firstOrFail();
    $satopan = Product::where('name', 'SATOPAN simple')->firstOrFail();
    $door = Product::where('name', 'Porte métallique')->firstOrFail();

    expect($cement->shop->name)->toBe('Boutique 1');
    expect($cement->currentPrice->purchase_price)->toBe(4600);
    expect($satopan->shop->name)->toBe('Boutique 2');
    expect($satopan->currentPrice->purchase_price)->toBe(5000);
    expect($door->currentPrice->purchase_price)->toBe(22000);
});

test('the shop 1 products 1 to 11 add up to the unit prices of the workbook', function () {
    $this->seed(InitialCatalogSeeder::class);

    $sum = Product::whereHas('shop', fn ($query) => $query->where('name', 'Boutique 1'))
        ->with('currentPrice')
        ->get()
        ->sum(fn (Product $product) => $product->currentPrice->purchase_price);

    expect($sum)->toBe(1775 + 2800 + 2300 + 4600 + 1300 + 6000 + 2000 + 800 + 800 + 800 + 22000);
});

test('running the seeder twice does not duplicate anything', function () {
    $this->seed(InitialCatalogSeeder::class);
    $this->seed(InitialCatalogSeeder::class);

    expect(Shop::count())->toBe(2);
    expect(Product::count())->toBe(12);
    expect(Product::firstOrFail()->prices()->count())->toBe(1);
});
