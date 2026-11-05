<?php

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Shop;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the edit page shows the price history, newest first', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000, 'effective_from' => '2026-01-01']);
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1200, 'effective_from' => '2026-06-01']);

    $this->actingAs(User::factory()->create())
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/edit')
            ->has('prices', 2)
            ->where('prices.0.purchase_price', 1200)
            ->where('prices.1.purchase_price', 1000));
});

test('a new price is added to the history and the old one is kept', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->for($shop)->create();
    $old = ProductPrice::factory()->for($product)->create(['purchase_price' => 1000, 'effective_from' => '2026-01-01']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('products.prices.store', $product), [
            'purchase_price' => 1300,
            'sale_price' => 1500,
            'effective_from' => '2026-07-01',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('products.edit', $product));

    expect($product->prices()->count())->toBe(2);
    expect($old->refresh()->purchase_price)->toBe(1000);

    $new = $product->prices()->where('purchase_price', 1300)->sole();

    expect($new->sale_price)->toBe(1500);
    expect($new->shop_id)->toBe($shop->id);
    expect($new->created_by)->toBe($user->id);
});

test('a price is rejected for invalid data', function (array $payload, string $field) {
    $product = Product::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('products.prices.store', $product), $payload)
        ->assertSessionHasErrors($field);

    expect($product->prices()->count())->toBe(0);
})->with([
    'missing purchase price' => [['effective_from' => '2026-07-01'], 'purchase_price'],
    'negative purchase price' => [['purchase_price' => -1, 'effective_from' => '2026-07-01'], 'purchase_price'],
    'missing date' => [['purchase_price' => 100], 'effective_from'],
    'invalid date' => [['purchase_price' => 100, 'effective_from' => 'not-a-date'], 'effective_from'],
]);

test('field agents cannot add a price', function () {
    $product = Product::factory()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('products.prices.store', $product), ['purchase_price' => 100, 'effective_from' => '2026-07-01'])
        ->assertForbidden();

    expect($product->prices()->count())->toBe(0);
});

test('the current price is the latest one that is already in effect', function () {
    $this->travelTo('2026-07-15');

    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000, 'effective_from' => '2026-01-01']);
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1200, 'effective_from' => '2026-07-01']);
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1500, 'effective_from' => '2026-09-01']);

    expect($product->currentPrice->purchase_price)->toBe(1200);
});

test('the current price changes when a future price comes into effect', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000, 'effective_from' => '2026-01-01']);
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1500, 'effective_from' => '2026-09-01']);

    $this->travelTo('2026-08-31');
    expect($product->fresh()->currentPrice->purchase_price)->toBe(1000);

    $this->travelTo('2026-09-01');
    expect($product->fresh()->currentPrice->purchase_price)->toBe(1500);
});

test('when two prices share a date the most recently entered one wins', function () {
    $this->travelTo('2026-07-15');

    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000, 'effective_from' => '2026-07-01']);
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1100, 'effective_from' => '2026-07-01']);

    expect($product->currentPrice->purchase_price)->toBe(1100);
});

test('a product with no price in effect has no current price', function () {
    $this->travelTo('2026-07-15');

    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['effective_from' => '2026-09-01']);

    expect($product->currentPrice)->toBeNull();
});
