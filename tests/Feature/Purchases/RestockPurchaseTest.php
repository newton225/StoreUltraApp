<?php

use App\Actions\Purchases\CreateRestockPurchaseOrder;
use App\Actions\Purchases\ReceivePurchaseOrder;
use App\Actions\Purchases\SendPurchaseOrder;
use App\Enums\StockMovementType;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductPrice;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ShopAccounts;
use App\Support\StockLevels;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->shop = Shop::factory()->create();
    $this->bar = Product::factory()->for($this->shop)->create(['pack_size' => 10]);
    ProductPrice::factory()->for($this->bar)->create(['purchase_price' => 1000]);
});

function restockPayload(Shop $shop, array $lines): array
{
    return ['shop_id' => $shop->id, 'lines' => array_map(fn (array $line): array => ['product_id' => $line[0]->id, 'packs' => $line[1]], $lines)];
}

function receiveRestock(PurchaseOrder $purchase, int $quantity, int|float|null $price = null): void
{
    app(SendPurchaseOrder::class)->handle($purchase->refresh(), null);
    app(ReceivePurchaseOrder::class)->handle($purchase->refresh(), [[
        'id' => $purchase->items()->value('id'),
        'quantity_received' => $quantity,
        'unit_price_actual' => $price,
    ]]);
}

test('a restock purchase order is made in whole packages at the current price, without any customer order', function () {
    $this->actingAs($this->owner)->post(route('purchases.restock.store'), restockPayload($this->shop, [[$this->bar, 3]]))
        ->assertSessionHasNoErrors();

    $purchase = PurchaseOrder::sole();
    $line = $purchase->items()->sole();

    expect($purchase->kind)->toBe('restock')
        ->and($purchase->status->value)->toBe('draft')
        ->and($line->quantity_ordered)->toBe(30)
        ->and($line->unit_price_expected)->toBe(1000)
        ->and($purchase->expected_total)->toBe(30000)
        ->and(OrderItem::whereNotNull('purchase_order_id')->count())->toBe(0);
});

test('a restock order is refused for a product of another shop, a manufactured one, an archived one or no packages', function (Closure $payload) {
    $other = Product::factory()->for(Shop::factory()->create())->create();
    $panel = Product::factory()->for($this->shop)->create();
    ProductComponent::factory()->create(['product_id' => $panel->id, 'component_product_id' => $this->bar->id]);
    $archived = Product::factory()->for($this->shop)->archived()->create();

    $this->actingAs($this->owner)->post(route('purchases.restock.store'), $payload($this, $other, $panel, $archived))->assertSessionHasErrors();

    expect(PurchaseOrder::count())->toBe(0);
})->with([
    'another shop' => [fn ($t, $other) => restockPayload($t->shop, [[$other, 1]])],
    'a manufactured product' => [fn ($t, $other, $panel) => restockPayload($t->shop, [[$panel, 1]])],
    'an archived product' => [fn ($t, $other, $panel, $archived) => restockPayload($t->shop, [[$archived, 1]])],
    'zero package' => [fn ($t) => restockPayload($t->shop, [[$t->bar, 0]])],
    'no line' => [fn ($t) => ['shop_id' => $t->shop->id, 'lines' => []]],
]);

test('receiving a restock order puts everything received into the stock at the price paid', function () {
    $purchase = app(CreateRestockPurchaseOrder::class)->handle($this->shop, [['product_id' => $this->bar->id, 'packs' => 3]], $this->owner);

    receiveRestock($purchase, 30, 1100);

    expect(app(StockLevels::class)->forProduct($this->bar->id))->toBe(['on_hand' => 30, 'average_cost' => 1100, 'value' => 33000])
        ->and(StockMovement::sole()->type)->toBe(StockMovementType::Receipt)
        ->and($purchase->refresh()->status->value)->toBe('received');
});

test('a partial reception then the cumulative one enters only the difference, and the same figure again nothing', function () {
    $purchase = app(CreateRestockPurchaseOrder::class)->handle($this->shop, [['product_id' => $this->bar->id, 'packs' => 3]], $this->owner);
    app(SendPurchaseOrder::class)->handle($purchase->refresh(), null);
    $itemId = $purchase->items()->value('id');
    $receive = fn (int $quantity) => app(ReceivePurchaseOrder::class)->handle($purchase->refresh(), [['id' => $itemId, 'quantity_received' => $quantity, 'unit_price_actual' => null]]);

    $receive(12);
    $receive(12);
    $receive(30);

    expect(StockMovement::orderBy('id')->pluck('quantity')->all())->toBe([12, 18])
        ->and(app(StockLevels::class)->forProduct($this->bar->id)['on_hand'])->toBe(30);
});

test('a purchase order made for customer orders does not touch the stock when received', function () {
    $purchase = PurchaseOrder::factory()->create(['shop_id' => $this->shop->id, 'status' => 'sent', 'kind' => 'orders']);
    $purchase->items()->create(['product_id' => $this->bar->id, 'quantity_ordered' => 10, 'unit_price_expected' => 1000]);

    app(ReceivePurchaseOrder::class)->handle($purchase, [['id' => $purchase->items()->value('id'), 'quantity_received' => 10, 'unit_price_actual' => null]]);

    expect(StockMovement::count())->toBe(0);
});

test('a restock order received and not paid is owed to the shop, and cancelling a draft leaves the stock alone', function () {
    $purchase = app(CreateRestockPurchaseOrder::class)->handle($this->shop, [['product_id' => $this->bar->id, 'packs' => 2]], $this->owner);
    $draft = app(CreateRestockPurchaseOrder::class)->handle($this->shop, [['product_id' => $this->bar->id, 'packs' => 1]], $this->owner);

    receiveRestock($purchase, 20);

    $account = collect(app(ShopAccounts::class)->all())->firstWhere('shop_id', $this->shop->id);

    expect($account['debt'])->toBe(20000)
        ->and(app(StockLevels::class)->forProduct($this->bar->id)['on_hand'])->toBe(20)
        ->and($draft->refresh()->status->value)->toBe('draft');
});
