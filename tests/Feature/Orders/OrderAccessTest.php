<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\Permission;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to the login page', function () {
    $this->get(route('orders.index'))->assertRedirect(route('login'));
});

test('the office sees every order, a field agent only the ones of their zones', function () {
    $mine = Zone::factory()->create();
    Order::factory()->for(Client::factory()->for($mine))->create();
    Order::factory()->for(Client::factory()->for(Zone::factory()))->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->component('orders/index')->where('orders.total', 2));

    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($mine);

    $this->actingAs($agent)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 1));
});

test('a field agent can open an order of their zone but not another', function () {
    $mine = Zone::factory()->create();
    $own = Order::factory()->for(Client::factory()->for($mine))->create();
    $foreign = Order::factory()->for(Client::factory()->for(Zone::factory()))->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($mine);

    $this->actingAs($agent);

    $this->get(route('orders.show', $own))->assertOk();
    $this->get(route('orders.show', $foreign))->assertForbidden();
});

test('the list can be filtered by status, week and client', function () {
    Order::factory()->for(Client::factory()->create(['name' => 'Aya Kone']))->create(['status' => 'draft', 'delivery_week' => '2026-07-13']);
    Order::factory()->create(['status' => 'delivered', 'delivery_week' => '2026-07-20']);

    $this->actingAs(User::factory()->create());

    $this->get(route('orders.index', ['status' => 'draft']))
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 1));

    $this->get(route('orders.index', ['week' => '2026-07-20']))
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 1)->where('orders.data.0.status', 'delivered'));

    $this->get(route('orders.index', ['search' => 'aya']))
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 1)->where('orders.data.0.client.name', 'Aya Kone'));
});

test('the order page shows the frozen lines, the totals and the history', function () {
    $this->actingAs(User::factory()->owner()->create());
    $order = app(CreateOrder::class)->handle(
        client: Client::factory()->create(),
        version: pricedKit(),
        kitsCount: 2,
        deliveryWeek: today(),
        advanceAmount: 100000,
    );

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/show')
            ->where('order.total_sale', 11000)
            ->where('order.total_purchase_expected', 8000)
            ->where('order.advance_due', 100000)
            ->has('order.items', 2)
            ->where('order.allowed_next', ['proforma_issued', 'cancelled']));
});

test('the office creates an order by hand and its prices are frozen', function () {
    $version = pricedKit();
    $client = Client::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->post(route('orders.store'), [
            'client_id' => $client->id,
            'kit_id' => $version->kit_id,
            'kits_count' => 3,
            'delivery_week' => today()->toDateString(),
            'advance_due' => 200000,
        ])
        ->assertSessionHasNoErrors();

    $order = Order::sole();

    $response->assertRedirect(route('orders.show', $order));
    expect($order->client_id)->toBe($client->id);
    expect($order->kit_version_id)->toBe($version->id);
    expect($order->total_sale)->toBe(16500);
    expect($order->delivery_week->toDateString())->toBe(today()->startOfWeek()->toDateString());
});

test('an order by hand is rejected for invalid data', function (Closure $mutate, string $field) {
    $version = pricedKit();

    $payload = $mutate([
        'client_id' => Client::factory()->create()->id,
        'kit_id' => $version->kit_id,
        'kits_count' => 3,
        'delivery_week' => '2026-07-15',
        'advance_due' => 200000,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(Order::count())->toBe(0);
})->with([
    'unknown client' => [fn (array $p) => [...$p, 'client_id' => 9999], 'client_id'],
    'unknown kit' => [fn (array $p) => [...$p, 'kit_id' => 9999], 'kit_id'],
    'no kit' => [fn (array $p) => [...$p, 'kits_count' => 0], 'kits_count'],
    'missing week' => [fn (array $p) => [...$p, 'delivery_week' => ''], 'delivery_week'],
    'negative advance' => [fn (array $p) => [...$p, 'advance_due' => -5], 'advance_due'],
]);

test('an order by hand whose prices are missing is refused with a message', function () {
    $version = makeKitVersion([[Product::factory()->create(), 1, 1000]]);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.store'), [
            'client_id' => Client::factory()->create()->id,
            'kit_id' => $version->kit_id,
            'kits_count' => 1,
            'delivery_week' => '2026-07-15',
            'advance_due' => 0,
        ])
        ->assertSessionHasErrors('kit_id');

    expect(Order::count())->toBe(0);
});

test('a field agent cannot create an order', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('orders.store'), [])
        ->assertForbidden();
});

test('what the goods cost and the margin are not sent to those who may not see the profit', function () {
    $zone = Zone::factory()->create();
    $order = app(CreateOrder::class)->handle(
        client: Client::factory()->for($zone)->create(),
        version: pricedKit(),
        kitsCount: 1,
        deliveryWeek: today(),
        advanceAmount: 1000,
    );

    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zone);

    foreach ([$agent, User::factory()->create()] as $user) {
        $this->actingAs($user)->get(route('orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.total_sale', 5500)
                ->missing('order.total_purchase_expected')
                ->missing('order.margin_expected')
                ->missing('order.items.0.unit_purchase_price'));
    }

    $this->actingAs(withProfile(User::factory()->create(), [Permission::ViewProfit]))
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.total_purchase_expected', 4000)
            ->has('order.items.0.unit_purchase_price'));
});
