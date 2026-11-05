<?php

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function proformaOrderIn(string $locality, int $paid): Order
{
    $order = Order::factory()->for(Client::factory()->create(['locality' => $locality]))->create([
        'status' => OrderStatus::ProformaIssued,
        'advance_due' => 200000,
    ]);

    if ($paid > 0) {
        Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => $paid]);
    }

    return $order;
}

test('only the orders with a settled advance are offered, by locality', function () {
    proformaOrderIn('Darakokaha', 200000);
    proformaOrderIn('Darakokaha', 50000);
    proformaOrderIn('Fronan', 0);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->has('advanceLocalities', 1)
            ->where('advanceLocalities.0.name', 'Darakokaha')
            ->has('advanceLocalities.0.orders', 1));
});

test('the picked orders with a settled advance move to advance received', function () {
    $picked = proformaOrderIn('Darakokaha', 200000);
    $left = proformaOrderIn('Darakokaha', 200000);
    $unpaid = proformaOrderIn('Darakokaha', 10000);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('orders.advances.store'), ['locality' => 'Darakokaha', 'order_ids' => [$picked->id, $unpaid->id]])
        ->assertRedirect();

    expect($picked->refresh()->status)->toBe(OrderStatus::AdvanceReceived)
        ->and($left->refresh()->status)->toBe(OrderStatus::ProformaIssued)
        ->and($unpaid->refresh()->status)->toBe(OrderStatus::ProformaIssued);
});

test('a user who cannot manage orders cannot record the advances', function () {
    $order = proformaOrderIn('Darakokaha', 200000);

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('orders.advances.store'), ['locality' => 'Darakokaha', 'order_ids' => [$order->id]])
        ->assertForbidden();
});
