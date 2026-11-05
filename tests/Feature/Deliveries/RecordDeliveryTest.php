<?php

use App\Actions\Deliveries\RecordDelivery;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;
use Illuminate\Support\Facades\Storage;

const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach(function () {
    Storage::fake('local');
    $this->cash = PaymentMethod::factory()->create(['code' => 'cash', 'requires_reference' => false]);
    $this->employee = User::factory()->create();
});

function deliverableOrder(int $kits = 3, int $totalSale = 100000, string $status = 'received'): Order
{
    return Order::factory()->create([
        'status' => $status,
        'kits_count' => $kits,
        'total_sale' => $totalSale,
        'advance_due' => 40000,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function deliveryPayload(array $overrides = []): array
{
    return [
        'kits_delivered' => 3,
        'delivered_on' => today()->toDateString(),
        'received_by' => 'Kouassi Aya',
        'signature' => TINY_PNG,
        ...$overrides,
    ];
}

test('a full delivery is recorded, signed and closes the delivery of the order', function () {
    $order = deliverableOrder();

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', $order), deliveryPayload(['notes' => 'RAS']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('orders.show', $order));

    $delivery = Delivery::sole();

    expect($delivery->order_id)->toBe($order->id);
    expect($delivery->kits_delivered)->toBe(3);
    expect($delivery->received_by)->toBe('Kouassi Aya');
    expect($delivery->created_by)->toBe($this->employee->id);
    expect($delivery->reference)->toBe(sprintf('LIV-%06d', $delivery->id));
    expect($delivery->signature_path)->not->toBeNull();
    Storage::disk('local')->assertExists($delivery->signature_path);
    expect($order->refresh()->status->value)->toBe('delivered');
    expect($order->statusChanges()->sole()->to->value)->toBe('delivered');
    expect(AuditLog::where('action', 'delivery.recorded')->count())->toBe(1);
});

test('a partial delivery leaves the rest to deliver later', function () {
    $order = deliverableOrder(3);
    $this->actingAs($this->employee);

    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['kits_delivered' => 2]))->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('received');

    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['kits_delivered' => 2]))->assertSessionHasErrors('kits_delivered');

    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['kits_delivered' => 1]))->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('delivered');
    expect(Delivery::sum('kits_delivered'))->toBe(3);
});

test('only an order whose goods were received can be delivered', function (string $status) {
    $order = deliverableOrder(3, 100000, $status);

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', $order), deliveryPayload())
        ->assertSessionHasErrors('status');

    expect(Delivery::count())->toBe(0);
})->with(['draft', 'proforma_issued', 'advance_received', 'ordered', 'delivered', 'settled', 'closed', 'cancelled']);

test('a delivery is rejected for invalid data', function (Closure $mutate, string $field) {
    $payload = $mutate(deliveryPayload());

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', deliverableOrder()), $payload)
        ->assertSessionHasErrors($field);

    expect(Delivery::count())->toBe(0);
})->with([
    'no kit' => [fn (array $p) => [...$p, 'kits_delivered' => 0], 'kits_delivered'],
    'more than ordered' => [fn (array $p) => [...$p, 'kits_delivered' => 4], 'kits_delivered'],
    'missing date' => [fn (array $p) => [...$p, 'delivered_on' => ''], 'delivered_on'],
    'future date' => [fn (array $p) => [...$p, 'delivered_on' => today()->addDay()->toDateString()], 'delivered_on'],
    'nobody received' => [fn (array $p) => [...$p, 'received_by' => ''], 'received_by'],
    'no signature' => [fn (array $p) => [...$p, 'signature' => null], 'signature'],
    'not an image' => [fn (array $p) => [...$p, 'signature' => 'hello'], 'signature'],
    'not a png' => [fn (array $p) => [...$p, 'signature' => 'data:image/png;base64,'.base64_encode('not really an image')], 'signature'],
    'too heavy' => [fn (array $p) => [...$p, 'signature' => 'data:image/png;base64,'.str_repeat('A', 800000)], 'signature'],
]);

test('the signature is optional when the setting says so', function () {
    app(BusinessSettings::class)->set('delivery_signature_required', false);
    $order = deliverableOrder();

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', $order), deliveryPayload(['signature' => null]))
        ->assertSessionHasNoErrors();

    expect(Delivery::sole()->signature_path)->toBeNull();
});

test('the balance can be collected during the delivery and settles the order', function () {
    $order = deliverableOrder(3, 100000);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000, 'payment_method_id' => $this->cash->id]);

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', $order), deliveryPayload([
            'collect_amount' => 60000,
            'payment_method_id' => $this->cash->id,
        ]))
        ->assertSessionHasNoErrors();

    $payment = Payment::where('kind', 'balance')->sole();

    expect($payment->amount)->toBe(60000);
    expect($payment->collected_by)->toBe($this->employee->id);
    expect($payment->receipt_number)->not->toBeNull();
    expect($order->refresh()->status->value)->toBe('settled');
});

test('an order already paid in full is settled by its delivery', function () {
    $order = deliverableOrder(3, 100000);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 100000, 'payment_method_id' => $this->cash->id]);

    $this->actingAs($this->employee)->post(route('orders.deliveries.store', $order), deliveryPayload())->assertSessionHasNoErrors();

    expect($order->refresh()->status->value)->toBe('settled');
});

test('a part of the balance leaves the order delivered', function () {
    $order = deliverableOrder(3, 100000);

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', $order), deliveryPayload([
            'collect_amount' => 30000,
            'payment_method_id' => $this->cash->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status->value)->toBe('delivered');
    expect($order->netPaid())->toBe(30000);
});

test('a payment that is refused cancels the whole delivery', function () {
    $order = deliverableOrder(3, 100000);

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', $order), deliveryPayload([
            'collect_amount' => 100001,
            'payment_method_id' => $this->cash->id,
        ]))
        ->assertSessionHasErrors('collect_amount');

    expect(Delivery::count())->toBe(0);
    expect(Payment::count())->toBe(0);
    expect($order->refresh()->status->value)->toBe('received');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('a field agent delivers the orders of their zone and is the agent of the delivery', function () {
    $zone = Zone::factory()->create();
    $order = Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'received', 'kits_count' => 3, 'total_sale' => 100000]);
    $other = deliverableOrder();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zone);

    $this->actingAs($agent);

    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['agent_id' => User::factory()->agent()->create()->id]))->assertSessionHasNoErrors();
    expect(Delivery::sole()->agent_id)->toBe($agent->id);

    $this->post(route('orders.deliveries.store', $other), deliveryPayload())->assertForbidden();
});

test('the office can name the field agent who delivered', function () {
    $agent = User::factory()->agent()->create();
    $order = deliverableOrder();

    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', $order), deliveryPayload(['agent_id' => $agent->id]))
        ->assertSessionHasNoErrors();

    expect(Delivery::sole()->agent_id)->toBe($agent->id);
});

test('the agent named must be a field agent', function () {
    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', deliverableOrder()), deliveryPayload(['agent_id' => User::factory()->create()->id]))
        ->assertSessionHasErrors('agent_id');
});

test('guests are sent to the login page', function () {
    $this->post(route('orders.deliveries.store', deliverableOrder()), deliveryPayload())->assertRedirect(route('login'));
});

test('the same submission sent twice hands the kits over and takes the money once', function () {
    $order = deliverableOrder();
    $payload = deliveryPayload([
        'idempotency_key' => 'form-1',
        'collect_amount' => 100000,
        'payment_method_id' => $this->cash->id,
    ]);
    $this->actingAs($this->employee);

    $this->post(route('orders.deliveries.store', $order), $payload)->assertSessionHasNoErrors();
    $this->post(route('orders.deliveries.store', $order), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('orders.show', $order));

    expect(Delivery::count())->toBe(1);
    expect(Payment::count())->toBe(1);
    expect($order->refresh()->status->value)->toBe('settled');
    expect(AuditLog::where('action', 'delivery.recorded')->count())->toBe(1);
});

test('a replayed partial delivery does not hand the kits over twice', function () {
    $order = deliverableOrder(3);
    $payload = deliveryPayload(['kits_delivered' => 2, 'idempotency_key' => 'form-1']);
    $this->actingAs($this->employee);

    $this->post(route('orders.deliveries.store', $order), $payload);
    $this->post(route('orders.deliveries.store', $order), $payload)->assertSessionHasNoErrors();

    expect(Delivery::count())->toBe(1);
    expect($order->refresh()->remainingKits())->toBe(1);
});

test('two different submissions are two deliveries', function () {
    $order = deliverableOrder(3);
    $this->actingAs($this->employee);

    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['kits_delivered' => 1, 'idempotency_key' => 'form-1']));
    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['kits_delivered' => 1, 'idempotency_key' => 'form-2']));

    expect(Delivery::count())->toBe(2);
    expect($order->refresh()->remainingKits())->toBe(1);
});

test('the same key on another order is a new delivery', function () {
    $this->actingAs($this->employee);
    $payload = deliveryPayload(['kits_delivered' => 1, 'idempotency_key' => 'form-1']);

    $this->post(route('orders.deliveries.store', deliverableOrder(3)), $payload);
    $this->post(route('orders.deliveries.store', deliverableOrder(3)), $payload);

    expect(Delivery::count())->toBe(2);
});

test('a delivery without a key is still recorded, without protection against a replay', function () {
    $order = deliverableOrder(3);
    $this->actingAs($this->employee);

    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['kits_delivered' => 1]))->assertSessionHasNoErrors();
    $this->post(route('orders.deliveries.store', $order), deliveryPayload(['kits_delivered' => 1]))->assertSessionHasNoErrors();

    expect(Delivery::count())->toBe(2);
});

test('a delivery key that is too long is rejected', function () {
    $this->actingAs($this->employee)
        ->post(route('orders.deliveries.store', deliverableOrder()), deliveryPayload(['idempotency_key' => str_repeat('a', 65)]))
        ->assertSessionHasErrors('idempotency_key');

    expect(Delivery::count())->toBe(0);
});

test('a signature written for a delivery that could not be recorded is removed', function () {
    $order = deliverableOrder();
    Delivery::updating(function (Delivery $delivery): void {
        if ($delivery->isDirty('signature_path')) {
            throw new RuntimeException('the database refuses');
        }
    });

    expect(fn () => app(RecordDelivery::class)->handle(
        order: $order,
        kits: 3,
        deliveredOn: today(),
        receivedBy: 'Kouassi Aya',
        signature: base64_decode(substr(TINY_PNG, strlen('data:image/png;base64,'))),
        user: $this->employee,
    ))->toThrow(RuntimeException::class);

    expect(Delivery::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    expect($order->refresh()->status->value)->toBe('received');
    expect(AuditLog::where('action', 'delivery.recorded')->count())->toBe(0);
});

test('when the trace cannot be written, no delivery and no signature file are left', function () {
    $order = deliverableOrder();
    AuditLog::creating(function (): void {
        throw new RuntimeException('the journal is unavailable');
    });

    expect(fn () => app(RecordDelivery::class)->handle(
        order: $order,
        kits: 3,
        deliveredOn: today(),
        receivedBy: 'Kouassi Aya',
        signature: base64_decode(substr(TINY_PNG, strlen('data:image/png;base64,'))),
        user: $this->employee,
    ))->toThrow(RuntimeException::class);

    expect(Delivery::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    expect($order->refresh()->status->value)->toBe('received');
});

test('a signed delivery keeps its signature file next to its row', function () {
    $order = deliverableOrder();

    $delivery = app(RecordDelivery::class)->handle(
        order: $order,
        kits: 3,
        deliveredOn: today(),
        receivedBy: 'Kouassi Aya',
        signature: base64_decode(substr(TINY_PNG, strlen('data:image/png;base64,'))),
        user: $this->employee,
    );

    expect($delivery->refresh()->signature_path)->toBe("signatures/delivery-{$delivery->id}.png");
    Storage::disk('local')->assertExists($delivery->signature_path);
    expect($delivery->signed_at)->not->toBeNull();
});
