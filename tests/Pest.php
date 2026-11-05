<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\Permission;
use App\Models\Client;
use App\Models\KitVersion;
use App\Models\KitVersionItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Build a kit version from lines of [product, quantity per kit, sale price of the line].
 *
 * @param  array<int, array{0: Product, 1: int, 2: int|null}>  $lines
 * @param  array<string, mixed>  $attributes
 */
function makeKitVersion(array $lines, array $attributes = []): KitVersion
{
    $version = KitVersion::factory()->create($attributes);

    foreach ($lines as [$product, $quantity, $salePrice]) {
        KitVersionItem::factory()->create([
            'kit_version_id' => $version->id,
            'product_id' => $product->id,
            'quantity_per_kit' => $quantity,
            'sale_price_per_kit' => $salePrice,
        ]);
    }

    return $version;
}

/**
 * A kit version whose prices are all known: per kit it costs 4 000 F (2 × 1 000 + 1 × 2 000)
 * and sells for 5 500 F (3 000 + 2 500).
 *
 * @param  array<string, mixed>  $attributes
 */
function pricedKit(array $attributes = []): KitVersion
{
    $first = Product::factory()->create();
    ProductPrice::factory()->for($first)->create(['purchase_price' => 1000]);
    $second = Product::factory()->create();
    ProductPrice::factory()->for($second)->create(['purchase_price' => 2000]);

    return makeKitVersion([[$first, 2, 3000], [$second, 1, 2500]], $attributes);
}

/**
 * An order ready to be paid: 100 000 F to sell, 40 000 F of advance due.
 */
function payableOrder(string $status = 'proforma_issued', int $totalSale = 100000, int $advanceDue = 40000): Order
{
    return Order::factory()->create([
        'status' => $status,
        'total_sale' => $totalSale,
        'advance_due' => $advanceDue,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentPayload(PaymentMethod $method, array $overrides = []): array
{
    return [
        'kind' => 'advance',
        'amount' => 40000,
        'payment_method_id' => $method->id,
        'paid_on' => today()->toDateString(),
        ...$overrides,
    ];
}

/**
 * An order of the given week with lines of [product, total quantity, unit purchase price].
 *
 * @param  array<int, array{0: Product, 1: int, 2: int}>  $lines
 */
function readyOrder(string $week, array $lines, string $status = 'advance_received'): Order
{
    $order = Order::factory()->create(['status' => $status, 'delivery_week' => $week]);

    foreach ($lines as [$product, $quantity, $price]) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'quantity' => $quantity,
            'unit_purchase_price' => $price,
        ]);
    }

    return $order;
}

/**
 * A real order of "Aya Kone" made from the priced kit (5 500 F per kit), forced to the given status.
 */
function realOrder(string $status = 'delivered', int $kits = 3, int $advance = 5000): Order
{
    $order = app(CreateOrder::class)->handle(
        client: Client::factory()->create(['name' => 'Aya Kone']),
        version: pricedKit(),
        kitsCount: $kits,
        deliveryWeek: today(),
        advanceAmount: $advance,
    );
    $order->forceFill(['status' => $status])->save();

    return $order;
}

/**
 * Gives a user a custom profile that grants exactly the given permissions.
 *
 * @param  list<Permission>  $permissions
 */
function withProfile(User $user, array $permissions, bool $restrictedToZones = false): User
{
    $profile = Profile::factory()->granting($permissions)->create([
        'restricted_to_zones' => $restrictedToZones,
    ]);

    $user->forceFill(['profile_id' => $profile->id])->save();

    return $user->refresh();
}
