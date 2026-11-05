<?php

use App\Models\Kit;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Database\Seeders\InitialCatalogSeeder;
use Database\Seeders\InitialKitSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function validKitPayload(array $overrides = []): array
{
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => 1000]);

    return [
        'name_fr' => 'Kit standard',
        'name_en' => 'Standard kit',
        'sale_price_mode' => 'lines',
        'valid_from' => '2026-11-02',
        'items' => [
            ['product_id' => $product->id, 'quantity_per_kit' => 2, 'sale_price_per_kit' => 3000],
        ],
        ...$overrides,
    ];
}

test('guests are sent to the login page', function () {
    $this->get(route('kits.index'))->assertRedirect(route('login'));
});

test('field agents cannot reach the kits', function () {
    $kit = Kit::factory()->create();
    $this->actingAs(User::factory()->agent()->create());

    $this->get(route('kits.index'))->assertForbidden();
    $this->get(route('kits.create'))->assertForbidden();
    $this->post(route('kits.store'), validKitPayload())->assertForbidden();
    $this->get(route('kits.show', $kit))->assertForbidden();
    $this->patch(route('kits.update', $kit), ['name_fr' => 'X', 'name_en' => 'X'])->assertForbidden();

    expect(Kit::count())->toBe(1);
});

test('the list shows each kit with the price of its current version', function () {
    $this->seed([InitialCatalogSeeder::class, InitialKitSeeder::class]);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('kits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kits/index')
            ->has('kits', 1)
            ->where('kits.0.current_version.version', 1)
            ->where('kits.0.current_version.summary.purchase', 91525)
            ->where('kits.0.current_version.summary.sale', 112200)
            ->where('kits.0.current_version.summary.margin', 20675));
});

test('creating a kit records its first version with its lines', function () {
    $payload = validKitPayload();

    $response = $this->actingAs(User::factory()->create())
        ->post(route('kits.store'), $payload)
        ->assertSessionHasNoErrors();

    $kit = Kit::where('name_fr', 'Kit standard')->firstOrFail();
    $version = $kit->versions()->sole();

    $response->assertRedirect(route('kits.show', $kit));
    expect($kit->active)->toBeTrue();
    expect($version->version)->toBe(1);
    expect($version->valid_from->toDateString())->toBe('2026-11-02');
    expect($version->items)->toHaveCount(1);
    expect($version->items->first()->quantity_per_kit)->toBe(2);
    expect($version->items->first()->sale_price_per_kit)->toBe(3000);
});

test('a kit is rejected for invalid data', function (Closure $mutate, string $field) {
    $archived = Product::factory()->archived()->create();
    Kit::factory()->create(['name_fr' => 'Kit pris']);

    $payload = $mutate(validKitPayload(), $archived);

    $this->actingAs(User::factory()->create())
        ->post(route('kits.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(Kit::count())->toBe(1);
})->with([
    'missing french name' => [fn (array $p) => [...$p, 'name_fr' => ''], 'name_fr'],
    'missing english name' => [fn (array $p) => [...$p, 'name_en' => ''], 'name_en'],
    'duplicate name' => [fn (array $p) => [...$p, 'name_fr' => 'Kit pris'], 'name_fr'],
    'unknown mode' => [fn (array $p) => [...$p, 'sale_price_mode' => 'sum'], 'sale_price_mode'],
    'missing date' => [fn (array $p) => [...$p, 'valid_from' => ''], 'valid_from'],
    'no lines' => [fn (array $p) => [...$p, 'items' => []], 'items'],
    'unknown product' => [fn (array $p) => [...$p, 'items' => [['product_id' => 9999, 'quantity_per_kit' => 1, 'sale_price_per_kit' => 10]]], 'items.0.product_id'],
    'archived product' => [fn (array $p, Product $archived) => [...$p, 'items' => [['product_id' => $archived->id, 'quantity_per_kit' => 1, 'sale_price_per_kit' => 10]]], 'items.0.product_id'],
    'zero quantity' => [fn (array $p) => [...$p, 'items' => [['product_id' => $p['items'][0]['product_id'], 'quantity_per_kit' => 0, 'sale_price_per_kit' => 10]]], 'items.0.quantity_per_kit'],
    'same product twice' => [fn (array $p) => [...$p, 'items' => [$p['items'][0], $p['items'][0]]], 'items.0.product_id'],
    'line without sale price in lines mode' => [fn (array $p) => [...$p, 'items' => [['product_id' => $p['items'][0]['product_id'], 'quantity_per_kit' => 1, 'sale_price_per_kit' => null]]], 'items.0.sale_price_per_kit'],
    'fixed mode without fixed price' => [fn (array $p) => [...$p, 'sale_price_mode' => 'fixed'], 'fixed_sale_price'],
]);

test('a fixed price kit does not need a sale price on each line', function () {
    $payload = validKitPayload(['sale_price_mode' => 'fixed', 'fixed_sale_price' => 5000]);
    $payload['items'][0]['sale_price_per_kit'] = null;

    $this->actingAs(User::factory()->create())
        ->post(route('kits.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(Kit::firstOrFail()->versions()->sole()->fixed_sale_price)->toBe(5000);
});

test('the kit page shows the versions, the current one and the products to choose from', function () {
    $this->seed([InitialCatalogSeeder::class, InitialKitSeeder::class]);
    Product::factory()->archived()->create(['name' => 'Produit archive']);
    $kit = Kit::firstOrFail();

    $this->actingAs(User::factory()->create())
        ->get(route('kits.show', $kit))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kits/show')
            ->has('versions', 1)
            ->where('currentVersionId', $kit->currentVersion->id)
            ->where('versions.0.summary.sale', 112200)
            ->has('versions.0.items', 12)
            ->has('products', 12)
            ->where('kitsPerClient', 3));
});

test('the names and the status of a kit can be changed', function () {
    $kit = Kit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('kits.update', $kit), ['name_fr' => 'Nouveau nom', 'name_en' => 'New name', 'active' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kits.show', $kit));

    $kit->refresh();

    expect($kit->name_fr)->toBe('Nouveau nom');
    expect($kit->name_en)->toBe('New name');
    expect($kit->active)->toBeFalse();
});

test('the purchase cost and the margin of a kit go only to who may see the profit', function () {
    $version = pricedKit();

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('kits.show', $version->kit_id))
        ->assertInertia(fn (Assert $page) => $page->where('seesCosts', true)->where('versions.0.summary.purchase', 4000)->where('versions.0.items.0.unit_purchase_price', 1000));

    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->get(route('kits.show', $version->kit_id))
        ->assertInertia(fn (Assert $page) => $page->where('seesCosts', false)->where('versions.0.summary.purchase', null)->where('versions.0.summary.margin', null)->where('versions.0.items.0.unit_purchase_price', null)->where('versions.0.summary.sale', 5500));

    $this->actingAs($employee)
        ->get(route('kits.index'))
        ->assertInertia(fn (Assert $page) => $page->where('seesCosts', false)->where('kits.0.current_version.summary.margin', null));
});
