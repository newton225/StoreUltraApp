<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientMember;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->zone = Zone::factory()->create(['name' => 'Fronan']);
});

function readyToDeliver(Zone $zone, int $kits = 3, string $status = 'advance_received'): Order
{
    $client = Client::factory()->create(['zone_id' => $zone->id]);

    return Order::factory()->for($client)->create(['status' => $status, 'kits_count' => $kits, 'total_sale' => 100000, 'advance_due' => 40000]);
}

/**
 * @param  list<array{0: Order, 1: int}>  $rows  order and number of kits
 * @return array<string, mixed>
 */
function deliveryBatch(Zone $zone, array $rows, array $overrides = []): array
{
    return [
        'zone_id' => $zone->id,
        'idempotency_key' => 'batch-1',
        'delivered_on' => today()->toDateString(),
        'deliveries' => array_map(fn (array $row): array => ['order_id' => $row[0]->id, 'kits' => $row[1], 'received_by' => 'Moussa'], $rows),
        ...$overrides,
    ];
}

test('the orders page offers the zones with orders ready to deliver and who usually receives', function () {
    $order = readyToDeliver($this->zone);
    ClientMember::factory()->create(['client_id' => $order->client_id, 'role' => ClientMember::ROLE_MANAGER, 'name' => 'Awa Kone', 'position' => 0]);
    readyToDeliver($this->zone, status: 'proforma_issued');

    $this->actingAs($this->owner)->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('deliveryZones', 1)
            ->where('deliveryZones.0.name', 'Fronan')
            ->has('deliveryZones.0.orders', 1)
            ->where('deliveryZones.0.orders.0.remaining', 3)
            ->where('deliveryZones.0.orders.0.receiver', 'Awa Kone'));
});

test('the picked orders are delivered in one go, each with its delivery and its trace', function () {
    $first = readyToDeliver($this->zone, 3);
    $second = readyToDeliver($this->zone, 5);

    $this->actingAs($this->owner)->post(route('orders.deliveries-batch.store'), deliveryBatch($this->zone, [[$first, 3], [$second, 2]]))
        ->assertSessionHasNoErrors();

    expect(Delivery::count())->toBe(2)
        ->and($first->refresh()->status->value)->toBe('delivered')
        ->and($second->refresh()->status->value)->toBe('advance_received')
        ->and($second->remainingKits())->toBe(3)
        ->and(Delivery::where('order_id', $second->id)->value('received_by'))->toBe('Moussa')
        ->and(AuditLog::where('action', 'order.batch_step')->count())->toBe(2);
});

test('sending the same batch again delivers nothing twice', function () {
    $order = readyToDeliver($this->zone, 3);
    $payload = deliveryBatch($this->zone, [[$order, 1]]);

    $this->actingAs($this->owner)->post(route('orders.deliveries-batch.store'), $payload);
    $this->post(route('orders.deliveries-batch.store'), $payload);

    expect(Delivery::count())->toBe(1)->and($order->remainingKits())->toBe(2);
});

test('an order that cannot be delivered fails alone and does not stop the others', function () {
    $good = readyToDeliver($this->zone, 3);
    $notReady = readyToDeliver($this->zone, 3, 'proforma_issued');
    $elsewhere = readyToDeliver(Zone::factory()->create(), 3);

    $this->actingAs($this->owner)->post(route('orders.deliveries-batch.store'), deliveryBatch($this->zone, [[$notReady, 1], [$elsewhere, 1], [$good, 2], [$good, 9]]))
        ->assertSessionHasNoErrors();

    expect(Delivery::count())->toBe(1)->and(Delivery::sole()->order_id)->toBe($good->id);
});

test('more kits than left, no receiver or a future date are refused', function (array $change) {
    $order = readyToDeliver($this->zone, 3);
    $payload = deliveryBatch($this->zone, [[$order, 1]], $change);

    $this->actingAs($this->owner)->post(route('orders.deliveries-batch.store'), $payload)->assertSessionHasErrors();

    expect(Delivery::count())->toBe(0);
})->with([
    'future date' => [['delivered_on' => today()->addDay()->toDateString()]],
    'no receiver' => [['deliveries' => [['order_id' => 1, 'kits' => 1, 'received_by' => '']]]],
    'no deliveries' => [['deliveries' => []]],
]);

test('a user without the delivery right cannot deliver in a batch', function () {
    $order = readyToDeliver($this->zone, 3);
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->post(route('orders.deliveries-batch.store'), deliveryBatch($this->zone, [[$order, 1]]))->assertForbidden();

    expect(Delivery::count())->toBe(0);
});
