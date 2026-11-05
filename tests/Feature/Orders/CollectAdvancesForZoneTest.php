<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->method = PaymentMethod::factory()->create(['label_fr' => 'Espèces']);
});

function waitingOrder(string $zone, int $due = 40000, string $status = 'proforma_issued'): Order
{
    $zoneModel = Zone::query()->firstOrCreate(['name' => $zone], ['active' => true]);

    return Order::factory()
        ->for(Client::factory()->create(['zone_id' => $zoneModel->id]))
        ->create(['status' => $status, 'advance_due' => $due, 'total_sale' => 100000]);
}

function advancePayload(Order $zoneOrder, array $rows, array $extra = []): array
{
    return [
        'zone_id' => $zoneOrder->client->zone_id,
        'payment_method_id' => test()->method->id,
        'paid_on' => today()->toDateString(),
        'idempotency_key' => 'batch-key-1',
        'payments' => $rows,
        ...$extra,
    ];
}

test('the advances of the picked orders of a zone are recorded in one go, each with its receipt', function () {
    $a = waitingOrder('Fronan');
    $b = waitingOrder('Fronan');
    $left = waitingOrder('Fronan');
    $elsewhere = waitingOrder('Katiola');

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($a, [
            ['order_id' => $a->id, 'amount' => 40000],
            ['order_id' => $b->id, 'amount' => 40000],
            ['order_id' => $elsewhere->id, 'amount' => 40000],
        ]))
        ->assertRedirect();

    expect($a->refresh()->status->value)->toBe('advance_received')
        ->and($b->refresh()->status->value)->toBe('advance_received')
        ->and($left->refresh()->status->value)->toBe('proforma_issued')
        ->and($elsewhere->refresh()->status->value)->toBe('proforma_issued');
    expect(Payment::query()->count())->toBe(2);
    expect(Payment::query()->whereNull('receipt_number')->count())->toBe(0);
    expect(AuditLog::query()->where('action', 'payment.recorded')->count())->toBe(2);

    $toast = session(SessionKey::FLASH_DATA)['toast'];

    expect($toast['type'])->toBe('warning')->and($toast['message'])->toContain('2 avance(s) encaissée(s)')->and($toast['message'])->toContain('Échecs : 1');
});

test('a client who pays only part leaves the order waiting, with the rest still to collect', function () {
    $order = waitingOrder('Fronan');

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 15000]]))
        ->assertRedirect();

    expect($order->refresh()->status->value)->toBe('proforma_issued')->and($order->netPaid())->toBe(15000);

    $this->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page
        ->where('collectZones.0.orders.0.outstanding', 25000)
        ->where('collectZones.0.orders.0.paid', 15000));
});

test('sending the same batch twice takes the money once', function () {
    $order = waitingOrder('Fronan', 40000);
    $payload = advancePayload($order, [['order_id' => $order->id, 'amount' => 15000]]);

    $this->actingAs($this->owner)->post(route('orders.advance-payments.store'), $payload)->assertRedirect();
    $this->post(route('orders.advance-payments.store'), $payload)->assertRedirect();

    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('the list only offers the zones whose orders still wait for an advance', function () {
    $paid = waitingOrder('Katiola');
    Payment::factory()->for($paid)->create(['kind' => 'advance', 'amount' => 40000]);
    waitingOrder('Fronan');
    waitingOrder('Darakokaha', 40000, 'draft');

    $this->actingAs($this->owner)->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page
        ->has('collectZones', 1)
        ->where('collectZones.0.name', 'Fronan')
        ->where('paymentMethods', fn ($methods) => collect($methods)->pluck('id')->contains($this->method->id)));
});

test('a method that needs a reference asks for one on every payment', function () {
    $order = waitingOrder('Fronan');
    $mobile = PaymentMethod::factory()->create(['requires_reference' => true]);

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 40000]], ['payment_method_id' => $mobile->id]))
        ->assertSessionHasErrors('payments.0.reference');

    expect(Payment::query()->count())->toBe(0);

    $this->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 40000, 'reference' => 'MP123']], ['payment_method_id' => $mobile->id]))
        ->assertSessionDoesntHaveErrors();

    expect(Payment::query()->sole()->reference)->toBe('MP123');
});

test('an amount above what the order costs is counted as failed and the others go through', function () {
    $good = waitingOrder('Fronan');
    $bad = waitingOrder('Fronan');

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($good, [
            ['order_id' => $good->id, 'amount' => 40000],
            ['order_id' => $bad->id, 'amount' => 999999],
        ]))
        ->assertRedirect();

    expect($good->refresh()->status->value)->toBe('advance_received')->and($bad->refresh()->status->value)->toBe('proforma_issued');
    expect(Payment::query()->count())->toBe(1);
});

test('only the office records advances in a group, and the request must be complete', function () {
    $order = waitingOrder('Fronan');
    $payload = advancePayload($order, [['order_id' => $order->id, 'amount' => 40000]]);

    $this->actingAs(User::factory()->agent()->create())->post(route('orders.advance-payments.store'), $payload)->assertForbidden();

    $this->actingAs($this->owner)->post(route('orders.advance-payments.store'), [...$payload, 'payments' => []])->assertSessionHasErrors('payments');
    $this->post(route('orders.advance-payments.store'), [...$payload, 'paid_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('paid_on');
});
