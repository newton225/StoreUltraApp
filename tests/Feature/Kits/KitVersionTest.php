<?php

use App\Models\Kit;
use App\Models\KitVersion;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;

function productWithPrice(int $purchasePrice = 1000): Product
{
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['purchase_price' => $purchasePrice]);

    return $product;
}

/**
 * @return array<string, mixed>
 */
function versionPayload(Product $product, array $overrides = []): array
{
    return [
        'sale_price_mode' => 'lines',
        'valid_from' => '2026-12-01',
        'items' => [
            ['product_id' => $product->id, 'quantity_per_kit' => 4, 'sale_price_per_kit' => 6000],
        ],
        ...$overrides,
    ];
}

test('a new version is added after the existing ones and the old one is untouched', function () {
    $old = productWithPrice();
    $new = productWithPrice(2000);
    $first = makeKitVersion([[$old, 3, 9000]], ['version' => 1, 'valid_from' => '2026-01-01']);
    $kit = $first->kit;
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('kits.versions.store', $kit), versionPayload($new))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kits.show', $kit));

    expect($kit->versions()->count())->toBe(2);

    $second = $kit->versions()->where('version', 2)->sole();

    expect($second->created_by)->toBe($user->id);
    expect($second->items->sole()->product_id)->toBe($new->id);
    expect($second->items->sole()->quantity_per_kit)->toBe(4);

    $first->refresh();
    expect($first->items->sole()->product_id)->toBe($old->id);
    expect($first->items->sole()->quantity_per_kit)->toBe(3);
});

test('version numbers follow each other for a kit and start again for another kit', function () {
    $product = productWithPrice();
    $kit = Kit::factory()->create();
    $other = Kit::factory()->create();

    $this->actingAs(User::factory()->create());
    $this->post(route('kits.versions.store', $kit), versionPayload($product));
    $this->post(route('kits.versions.store', $kit), versionPayload($product));
    $this->post(route('kits.versions.store', $other), versionPayload($product));

    expect($kit->versions()->reorder('version')->pluck('version')->all())->toBe([1, 2]);
    expect($other->versions()->pluck('version')->all())->toBe([1]);
});

test('the current version is the latest one whose date has come', function () {
    $this->travelTo('2026-07-15');

    $product = productWithPrice();
    $kit = Kit::factory()->create();
    makeKitVersion([[$product, 1, 100]], ['kit_id' => $kit->id, 'version' => 1, 'valid_from' => '2026-01-01']);
    $second = makeKitVersion([[$product, 1, 100]], ['kit_id' => $kit->id, 'version' => 2, 'valid_from' => '2026-07-01']);
    makeKitVersion([[$product, 1, 100]], ['kit_id' => $kit->id, 'version' => 3, 'valid_from' => '2026-09-01']);

    expect($kit->currentVersion->id)->toBe($second->id);
});

test('a kit with no version in effect has no current version', function () {
    $this->travelTo('2026-07-15');

    $kit = Kit::factory()->create();
    makeKitVersion([], ['kit_id' => $kit->id, 'valid_from' => '2026-09-01']);

    expect($kit->currentVersion)->toBeNull();
});

test('a version is rejected for invalid data and nothing is saved', function (Closure $mutate, string $field) {
    $kit = Kit::factory()->create();
    $product = productWithPrice();

    $this->actingAs(User::factory()->create())
        ->post(route('kits.versions.store', $kit), $mutate(versionPayload($product), $product))
        ->assertSessionHasErrors($field);

    expect(KitVersion::count())->toBe(0);
})->with([
    'no lines' => [fn (array $p) => [...$p, 'items' => []], 'items'],
    'zero quantity' => [fn (array $p, Product $product) => [...$p, 'items' => [['product_id' => $product->id, 'quantity_per_kit' => 0, 'sale_price_per_kit' => 1]]], 'items.0.quantity_per_kit'],
    'same product twice' => [fn (array $p) => [...$p, 'items' => [$p['items'][0], $p['items'][0]]], 'items.0.product_id'],
    'missing date' => [fn (array $p) => [...$p, 'valid_from' => ''], 'valid_from'],
]);

test('field agents cannot add a version', function () {
    $kit = Kit::factory()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('kits.versions.store', $kit), versionPayload(productWithPrice()))
        ->assertForbidden();

    expect(KitVersion::count())->toBe(0);
});
