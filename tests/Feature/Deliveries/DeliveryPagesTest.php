<?php

use App\Models\Client;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

test('guests are sent to the login page', function () {
    $this->get(route('deliveries.index'))->assertRedirect(route('login'));
});

test('the page lists the orders to deliver and the deliveries made', function () {
    $toDeliver = Order::factory()->create(['status' => 'received', 'kits_count' => 3]);
    $partial = Order::factory()->create(['status' => 'received', 'kits_count' => 3]);
    Delivery::factory()->for($partial)->create(['kits_delivered' => 1]);
    Order::factory()->create(['status' => 'ordered']);
    Order::factory()->create(['status' => 'delivered']);
    $done = Delivery::factory()->create(['kits_delivered' => 3]);

    $this->actingAs(User::factory()->create())
        ->get(route('deliveries.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('deliveries/index')
            ->has('to_deliver', 2)
            ->where('to_deliver', fn ($rows) => collect($rows)->pluck('remaining_kits', 'id')->all() === [
                $toDeliver->id => 3,
                $partial->id => 2,
            ])
            ->where('deliveries.total', 2));
});

test('a field agent only sees the work of their zones', function () {
    $zone = Zone::factory()->create();
    Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'received']);
    Order::factory()->create(['status' => 'received']);
    Delivery::factory()->for(Order::factory()->for(Client::factory()->for($zone)))->create();
    Delivery::factory()->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zone);

    $this->actingAs($agent)
        ->get(route('deliveries.index'))
        ->assertInertia(fn (Assert $page) => $page->has('to_deliver', 1)->where('deliveries.total', 1));
});

test('the list can be filtered by zone', function () {
    $zone = Zone::factory()->create();
    Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'received']);
    Order::factory()->create(['status' => 'received']);

    $this->actingAs(User::factory()->create())
        ->get(route('deliveries.index', ['zone' => $zone->id]))
        ->assertInertia(fn (Assert $page) => $page->has('to_deliver', 1));
});

test('the order page lists its deliveries and what remains to deliver', function () {
    $order = Order::factory()->create(['status' => 'received', 'kits_count' => 5]);
    Delivery::factory()->for($order)->create(['kits_delivered' => 2, 'received_by' => 'Aya']);

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('delivery.remaining_kits', 3)
            ->where('delivery.can_deliver', true)
            ->has('delivery.items', 1)
            ->where('delivery.items.0.received_by', 'Aya'));
});

test('the signature is shown to whoever can see the order', function () {
    $zone = Zone::factory()->create();
    $order = Order::factory()->for(Client::factory()->for($zone))->create();
    Storage::disk('local')->put('signatures/test.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    $delivery = Delivery::factory()->for($order)->create(['signature_path' => 'signatures/test.png']);

    $this->actingAs(User::factory()->create())
        ->get(route('deliveries.signature', $delivery))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $mine = User::factory()->agent()->create();
    $mine->zones()->attach($zone);
    $this->actingAs($mine)->get(route('deliveries.signature', $delivery))->assertOk();

    $this->actingAs(User::factory()->agent()->create())->get(route('deliveries.signature', $delivery))->assertForbidden();
});

test('a delivery without a signature has no image', function () {
    $delivery = Delivery::factory()->create(['signature_path' => null]);

    $this->actingAs(User::factory()->create())->get(route('deliveries.signature', $delivery))->assertNotFound();
});

test('guests cannot see a signature', function () {
    $this->get(route('deliveries.signature', Delivery::factory()->create()))->assertRedirect(route('login'));
});
