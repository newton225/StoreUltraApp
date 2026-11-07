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
    $paidOn = $extra['paid_on'] ?? today()->toDateString();
    $collector = $extra['collected_by'] ?? null;
    $method = $extra['payment_method_id'] ?? test()->method->id;
    unset($extra['paid_on'], $extra['collected_by'], $extra['payment_method_id']);

    return [
        'zone_id' => $zoneOrder->client->zone_id,
        'idempotency_key' => 'batch-key-1',
        'payments' => array_map(fn (array $row): array => ['paid_on' => $paidOn, 'collected_by' => $collector, 'payment_method_id' => $method, ...$row], $rows),
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
    $this->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 40000]], ['paid_on' => today()->addDay()->toDateString()]))->assertSessionHasErrors('payments.0.paid_on');
});

test('each payment keeps who paid and their phone, and the group says who collected the money', function () {
    $order = waitingOrder('Fronan');
    $colleague = User::factory()->create(['name' => 'Awa Traore']);

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($order, [
            ['order_id' => $order->id, 'amount' => 40000, 'payer_name' => 'Le chauffeur', 'payer_phone' => '0707070707'],
        ], ['collected_by' => $colleague->id]))
        ->assertRedirect();

    $payment = Payment::query()->sole();

    expect($payment->payer_name)->toBe('Le chauffeur')
        ->and($payment->payer_phone)->toBe('0707070707')
        ->and($payment->collected_by)->toBe($colleague->id)
        ->and($payment->recorded_by)->toBe($this->owner->id);
});

test('the collector defaults to the user and must be an active account', function () {
    $order = waitingOrder('Fronan');

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 20000]]))
        ->assertRedirect();

    expect(Payment::query()->sole()->collected_by)->toBe($this->owner->id);

    $inactive = User::factory()->inactive()->create();

    $this->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 20000]], ['collected_by' => $inactive->id, 'idempotency_key' => 'other']))
        ->assertSessionHasErrors('payments.0.collected_by');
});

test('the date and the collector can differ from one line to the next', function () {
    $a = waitingOrder('Fronan');
    $b = waitingOrder('Fronan');
    $colleague = User::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($a, [
            ['order_id' => $a->id, 'amount' => 40000, 'paid_on' => today()->subDays(2)->toDateString(), 'collected_by' => $colleague->id],
            ['order_id' => $b->id, 'amount' => 40000, 'paid_on' => today()->toDateString(), 'collected_by' => null],
        ]))
        ->assertRedirect();

    $first = Payment::query()->where('order_id', $a->id)->sole();
    $second = Payment::query()->where('order_id', $b->id)->sole();

    expect($first->paid_on->toDateString())->toBe(today()->subDays(2)->toDateString())
        ->and($first->collected_by)->toBe($colleague->id)
        ->and($second->paid_on->toDateString())->toBe(today()->toDateString())
        ->and($second->collected_by)->toBe($this->owner->id);
});

test('the payment method can differ from one line to the next, and each method asks its own reference', function () {
    $cash = $this->method;
    $mobile = PaymentMethod::factory()->create(['requires_reference' => true]);
    $a = waitingOrder('Fronan');
    $b = waitingOrder('Fronan');

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($a, [
            ['order_id' => $a->id, 'amount' => 40000, 'payment_method_id' => $cash->id],
            ['order_id' => $b->id, 'amount' => 40000, 'payment_method_id' => $mobile->id],
        ]))
        ->assertSessionHasErrors('payments.1.reference');

    expect(Payment::query()->count())->toBe(0);

    $this->post(route('orders.advance-payments.store'), advancePayload($a, [
        ['order_id' => $a->id, 'amount' => 40000, 'payment_method_id' => $cash->id],
        ['order_id' => $b->id, 'amount' => 40000, 'payment_method_id' => $mobile->id, 'reference' => 'MP9'],
    ]))->assertSessionDoesntHaveErrors();

    expect(Payment::query()->where('order_id', $a->id)->sole()->payment_method_id)->toBe($cash->id)
        ->and(Payment::query()->where('order_id', $b->id)->sole()->payment_method_id)->toBe($mobile->id);
});

test('an amount above what is still due on the advance is refused, even when the order costs more', function () {
    $order = waitingOrder('Fronan', 40000);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 15000]);

    $this->actingAs($this->owner)
        ->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 30000]]))
        ->assertRedirect();

    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1)->and(session(SessionKey::FLASH_DATA)['toast']['type'])->toBe('warning');

    $this->post(route('orders.advance-payments.store'), advancePayload($order, [['order_id' => $order->id, 'amount' => 25000]], ['idempotency_key' => 'second']))->assertRedirect();

    expect($order->refresh()->status->value)->toBe('advance_received')->and($order->netPaid())->toBe(40000);
});

test('the order list shows how much of the advance was received, never more than it asks', function () {
    $order = waitingOrder('Fronan', 40000);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 15000]);
    $none = waitingOrder('Fronan', 30000);

    $this->actingAs($this->owner)->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page
        ->where('orders.data', fn ($rows) => collect($rows)->firstWhere('id', $order->id)['advance_paid'] === 15000
            && collect($rows)->firstWhere('id', $order->id)['advance_due'] === 40000
            && collect($rows)->firstWhere('id', $none->id)['advance_paid'] === 0));
});
