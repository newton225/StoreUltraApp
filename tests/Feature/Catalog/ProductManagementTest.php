<?php

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Shop;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validProductPayload(array $overrides = []): array
{
    return [
        'name' => 'Ciment CPA 42,5',
        'unit' => 'u',
        'purchase_price' => 4600,
        ...$overrides,
    ];
}

test('guests are sent to the login page', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('field agents cannot reach the catalog', function () {
    $this->actingAs(User::factory()->agent()->create());
    $product = Product::factory()->create();

    $this->get(route('products.index'))->assertForbidden();
    $this->post(route('products.store'), validProductPayload())->assertForbidden();
    $this->patch(route('products.update', $product), ['name' => 'Changed', 'unit' => 'u'])->assertForbidden();
    $this->patch(route('products.archive', $product))->assertForbidden();

    expect($product->refresh()->archived_at)->toBeNull();
});

test('the list shows each product with its shop and current price and hides archived ones', function () {
    $shop = Shop::factory()->create(['name' => 'Boutique 1']);
    $product = Product::factory()->for($shop)->create(['name' => 'Ciment']);
    ProductPrice::factory()->for($product)->create(['purchase_price' => 4600, 'sale_price' => null, 'effective_from' => today()]);
    Product::factory()->archived()->create(['name' => 'Ancien produit']);

    $this->actingAs(User::factory()->create())
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Ciment')
            ->where('products.data.0.shop.name', 'Boutique 1')
            ->where('products.data.0.current_price.purchase_price', 4600));
});

test('archived products can be listed on request', function () {
    Product::factory()->create();
    Product::factory()->archived()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('products.index', ['archived' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
});

test('creating a product records its first price in the history', function () {
    $shop = Shop::factory()->create();
    $employee = User::factory()->create();

    $response = $this->actingAs($employee)
        ->post(route('products.store'), validProductPayload([
            'shop_id' => $shop->id,
            'sale_price' => 5742,
            'effective_from' => '2026-11-02',
        ]))
        ->assertSessionHasNoErrors();

    $product = Product::where('name', 'Ciment CPA 42,5')->firstOrFail();
    $price = $product->prices()->sole();

    $response->assertRedirect(route('products.edit', $product));
    expect($product->shop_id)->toBe($shop->id);
    expect($price->purchase_price)->toBe(4600);
    expect($price->sale_price)->toBe(5742);
    expect($price->effective_from->toDateString())->toBe('2026-11-02');
    expect($price->shop_id)->toBe($shop->id);
    expect($price->created_by)->toBe($employee->id);
});

test('a product without a unit price for sale is accepted', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('products.store'), validProductPayload())
        ->assertSessionHasNoErrors();

    expect(Product::firstOrFail()->prices()->sole()->sale_price)->toBeNull();
});

test('a product is rejected for invalid data', function (array $overrides, string $field) {
    Product::factory()->create(['name' => 'Ciment CPA 42,5']);

    $this->actingAs(User::factory()->create())
        ->post(route('products.store'), validProductPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Product::count())->toBe(1);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'duplicate name' => [[], 'name'],
    'missing unit' => [['name' => 'Autre', 'unit' => ''], 'unit'],
    'unknown shop' => [['name' => 'Autre', 'shop_id' => 999], 'shop_id'],
    'missing purchase price' => [['name' => 'Autre', 'purchase_price' => null], 'purchase_price'],
    'negative purchase price' => [['name' => 'Autre', 'purchase_price' => -1], 'purchase_price'],
    'fractional purchase price' => [['name' => 'Autre', 'purchase_price' => 10.5], 'purchase_price'],
    'negative sale price' => [['name' => 'Autre', 'sale_price' => -5], 'sale_price'],
]);

test('updating a product never touches its price history', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->create(['name' => 'Ciment']);
    ProductPrice::factory()->for($product)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('products.update', $product), ['name' => 'Ciment CPA', 'unit' => 'sac', 'shop_id' => $shop->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('products.index'));

    $product->refresh();

    expect($product->name)->toBe('Ciment CPA');
    expect($product->unit)->toBe('sac');
    expect($product->shop_id)->toBe($shop->id);
    expect($product->prices()->count())->toBe(1);
});

test('a product keeps its own name when it is updated', function () {
    $product = Product::factory()->create(['name' => 'Ciment']);

    $this->actingAs(User::factory()->create())
        ->patch(route('products.update', $product), ['name' => 'Ciment', 'unit' => 'u'])
        ->assertSessionHasNoErrors();
});

test('a product is archived and restored without losing its history', function () {
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create();

    $this->actingAs(User::factory()->create());

    $this->patch(route('products.archive', $product))->assertRedirect();
    expect($product->refresh()->archived_at)->not->toBeNull();

    $this->patch(route('products.restore', $product))->assertRedirect();
    expect($product->refresh()->archived_at)->toBeNull();
    expect($product->prices()->count())->toBe(1);
});
