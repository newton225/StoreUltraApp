<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->cash = PaymentMethod::factory()->create(['code' => 'cash']);
    $this->mobile = PaymentMethod::factory()->create(['code' => 'mobile']);
});

test('guests are sent to the login page and agents are refused', function () {
    $this->get(route('payments.index'))->assertRedirect(route('login'));
    $this->get(route('payments.arrears'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->agent()->create());
    $this->get(route('payments.index'))->assertForbidden();
    $this->get(route('payments.arrears'))->assertForbidden();
});

test('the list shows the payments newest first with the totals', function () {
    $order = payableOrder('received');
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000, 'payment_method_id' => $this->cash->id, 'paid_on' => today()->subDay()]);
    Payment::factory()->for($order)->create(['kind' => 'balance', 'amount' => 10000, 'payment_method_id' => $this->mobile->id, 'paid_on' => today()]);
    Payment::factory()->for($order)->create(['kind' => 'refund', 'amount' => 5000, 'payment_method_id' => $this->cash->id, 'paid_on' => today()]);

    $this->actingAs(User::factory()->create())
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/index')
            ->where('payments.total', 3)
            ->where('totals.received', 50000)
            ->where('totals.refunded', 5000)
            ->where('totals.net', 45000));
});

test('the list can be filtered by kind, method, dates and search', function () {
    $order = payableOrder('received');
    $client = $order->client;
    $client->update(['name' => 'Aya Kone']);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 1000, 'payment_method_id' => $this->cash->id, 'paid_on' => '2026-07-01']);
    Payment::factory()->for(payableOrder('received'))->create(['kind' => 'balance', 'amount' => 2000, 'payment_method_id' => $this->mobile->id, 'paid_on' => '2026-07-10']);

    $this->actingAs(User::factory()->create());

    $this->get(route('payments.index', ['kind' => 'balance']))
        ->assertInertia(fn (Assert $page) => $page->where('payments.total', 1)->where('totals.received', 2000));

    $this->get(route('payments.index', ['method' => $this->cash->id]))
        ->assertInertia(fn (Assert $page) => $page->where('payments.total', 1)->where('totals.received', 1000));

    $this->get(route('payments.index', ['from' => '2026-07-05', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page->where('payments.total', 1)->where('totals.received', 2000));

    $this->get(route('payments.index', ['search' => 'aya']))
        ->assertInertia(fn (Assert $page) => $page->where('payments.total', 1)->where('totals.received', 1000));
});

test('the arrears list the orders whose advance is late', function () {
    $this->travelTo('2026-07-15');
    $zone = Zone::factory()->create();

    $late = Order::factory()->for(Client::factory()->for($zone))->create([
        'status' => 'proforma_issued', 'delivery_week' => '2026-07-13', 'total_sale' => 100000, 'advance_due' => 40000,
    ]);
    Payment::factory()->for($late)->create(['kind' => 'advance', 'amount' => 15000, 'payment_method_id' => $this->cash->id]);

    Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-27', 'advance_due' => 40000]);
    Order::factory()->create(['status' => 'cancelled', 'delivery_week' => '2026-07-13', 'advance_due' => 40000]);
    Order::factory()->create(['status' => 'delivered', 'delivery_week' => '2026-07-13', 'advance_due' => 40000]);
    Order::factory()->create(['status' => 'advance_received', 'delivery_week' => '2026-07-13', 'advance_due' => 40000]);
    $paid = Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-13', 'advance_due' => 40000]);
    Payment::factory()->for($paid)->create(['kind' => 'advance', 'amount' => 40000, 'payment_method_id' => $this->cash->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('payments.arrears'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/arrears')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $late->id)
            ->where('orders.data.0.advance_outstanding', 25000)
            ->where('total_outstanding', 25000));

    $this->get(route('payments.arrears', ['zone' => $zone->id]))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));

    $this->get(route('payments.arrears', ['zone' => Zone::factory()->create()->id]))
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
});
