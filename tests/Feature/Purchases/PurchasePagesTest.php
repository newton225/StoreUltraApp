<?php

use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->shop1 = Shop::factory()->create(['name' => 'Boutique 1']);
    $this->shop2 = Shop::factory()->create(['name' => 'Boutique 2']);
    $this->iron = Product::factory()->for($this->shop1)->create();
    $this->cement = Product::factory()->for($this->shop1)->create();
    $this->panel = Product::factory()->for($this->shop2)->create();
    $this->week = '2026-07-13';
    $this->cash = PaymentMethod::factory()->create(['code' => 'cash']);
});

test('guests are sent to the login page and agents are refused', function () {
    $this->get(route('purchases.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->agent()->create());
    $this->get(route('purchases.index'))->assertForbidden();
});

test('the list shows the purchase orders of the week and can be filtered', function () {
    readyOrder($this->week, [[$this->iron, 2, 1000], [$this->panel, 1, 2000]]);
    readyOrder('2026-07-20', [[$this->iron, 1, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse('2026-07-20'));
    $this->actingAs(User::factory()->create());

    $this->get(route('purchases.index', ['week' => $this->week]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/index')
            ->where('purchaseOrders.total', 2)
            ->where('week', $this->week));

    $this->get(route('purchases.index', ['week' => $this->week, 'shop' => $this->shop2->id]))
        ->assertInertia(fn (Assert $page) => $page->where('purchaseOrders.total', 1));

    $this->get(route('purchases.index', ['week' => $this->week, 'status' => 'sent']))
        ->assertInertia(fn (Assert $page) => $page->where('purchaseOrders.total', 0));
});

test('the week summary splits the advances received between the shops', function () {
    $orderA = readyOrder($this->week, [[$this->iron, 6, 1000], [$this->panel, 3, 2000]]);
    $orderB = readyOrder($this->week, [[$this->iron, 3, 1000], [$this->cement, 1, 500]]);
    Payment::factory()->for($orderA)->create(['kind' => 'advance', 'amount' => 20000, 'payment_method_id' => $this->cash->id]);
    Payment::factory()->for($orderB)->create(['kind' => 'advance', 'amount' => 11000, 'payment_method_id' => $this->cash->id]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $this->actingAs(User::factory()->create());

    $this->get(route('purchases.index', ['week' => $this->week]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.advances', 31000)
            ->where('summary.shops', fn ($shops) => collect($shops)->mapWithKeys(fn ($row) => [$row['name'] => [$row['purchase'], $row['allocated_advance'], $row['to_finance']]])->all() === [
                'Boutique 1' => [9500, 19000, -9500],
                'Boutique 2' => [6000, 12000, -6000],
            ]));
});

test('the shop accounts show what was received, what was paid and what is still owed', function () {
    readyOrder($this->week, [[$this->iron, 5, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = PurchaseOrder::sole();
    $this->actingAs(User::factory()->create());
    $this->post(route('purchases.send', $purchase));
    $this->post(route('purchases.receive', $purchase), ['items' => [['id' => $purchase->items->sole()->id, 'quantity_received' => 5, 'unit_price_actual' => 1200]]]);
    ShopPayment::factory()->for($this->shop1)->create(['kind' => 'payment', 'amount' => 2500, 'payment_method_id' => $this->cash->id]);

    $this->get(route('purchases.index', ['week' => $this->week]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts', fn ($accounts) => collect($accounts)->firstWhere('name', 'Boutique 1') === [
                'shop_id' => $this->shop1->id,
                'name' => 'Boutique 1',
                'ordered' => 5000,
                'received' => 6000,
                'paid' => 2500,
                'debt' => 3500,
            ]));
});

test('the purchase order page shows the lines, the variances and the payments', function () {
    readyOrder($this->week, [[$this->iron, 4, 1000]]);
    app(GeneratePurchaseOrders::class)->handle(Carbon::parse($this->week));
    $purchase = PurchaseOrder::sole();
    $this->actingAs(User::factory()->create());
    $this->post(route('purchases.send', $purchase));
    $this->post(route('purchases.receive', $purchase), ['items' => [['id' => $purchase->items->sole()->id, 'quantity_received' => 3, 'unit_price_actual' => 1100]]]);
    ShopPayment::factory()->for($this->shop1)->create(['purchase_order_id' => $purchase->id, 'amount' => 1000, 'payment_method_id' => $this->cash->id]);

    $this->get(route('purchases.show', $purchase))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/show')
            ->where('purchase.status', 'partially_received')
            ->where('purchase.items.0.shortfall', 1)
            ->where('purchase.items.0.price_variance', 100)
            ->where('purchase.expected_total', 4000)
            ->where('purchase.actual_total', 3300)
            ->has('purchase.payments', 1)
            ->where('purchase.orders_count', 1));
});
