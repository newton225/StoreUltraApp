<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;

beforeEach(function () {
    $this->cash = PaymentMethod::factory()->create(['code' => 'cash', 'requires_reference' => false]);
});

test('the office records an advance and a numbered receipt is issued', function () {
    $order = payableOrder();
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 20000]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('orders.show', $order));

    $payment = Payment::sole();

    expect($payment->receipt_number)->toMatch('/^REC-\d{4}-0001$/');
    expect($payment->kind->value)->toBe('advance');
    expect($payment->amount)->toBe(20000);
    expect($payment->order_id)->toBe($order->id);
    expect($payment->collected_by)->toBe($employee->id);
    expect($payment->payment_method_id)->toBe($this->cash->id);
    expect($order->refresh()->netPaid())->toBe(20000);
    expect(AuditLog::where('action', 'payment.recorded')->count())->toBe(1);
});

test('a payment keeps who paid, who collected the money and who entered it in the app', function () {
    $order = payableOrder();
    $employee = User::factory()->create();
    $colleague = User::factory()->create();

    $this->actingAs($employee)
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, [
            'amount' => 20000,
            'payer_name' => 'Le frère du client',
            'payer_phone' => '0102030405',
            'collected_by' => $colleague->id,
        ]))
        ->assertSessionHasNoErrors();

    $payment = Payment::sole();

    expect($payment->payer_name)->toBe('Le frère du client');
    expect($payment->payer_phone)->toBe('0102030405');
    expect($payment->collected_by)->toBe($colleague->id);
    expect($payment->recorded_by)->toBe($employee->id);
});

test('without a collector, the logged-in user collected the money and entered it', function () {
    $order = payableOrder();
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 20000]))
        ->assertSessionHasNoErrors();

    expect(Payment::sole()->collected_by)->toBe($employee->id);
    expect(Payment::sole()->recorded_by)->toBe($employee->id);
});

test('a field agent cannot name another collector', function () {
    $zone = Zone::factory()->create();
    $order = Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'proforma_issued', 'total_sale' => 100000, 'advance_due' => 40000]);
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zone);
    $other = User::factory()->create();

    $this->actingAs($agent)
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 20000, 'collected_by' => $other->id]))
        ->assertSessionHasNoErrors();

    expect(Payment::sole()->collected_by)->toBe($agent->id);
});

test('the order page lists the team members who can have collected a payment, except for an agent', function () {
    $order = payableOrder();
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page
            ->where('payments.current_user_id', $employee->id)
            ->has('payments.collectors'));
});

test('receipts are numbered one after the other', function () {
    $this->actingAs(User::factory()->create());
    $order = payableOrder();

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 1000]));
    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 1000]));

    expect(Payment::orderBy('id')->pluck('receipt_number')->all())->toBe([
        sprintf('REC-%d-0001', now()->year),
        sprintf('REC-%d-0002', now()->year),
    ]);
});

test('the order moves to advance received when the advance is covered', function () {
    $order = payableOrder('proforma_issued');
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 20000]));
    expect($order->refresh()->status->value)->toBe('proforma_issued');

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 20000]));
    expect($order->refresh()->status->value)->toBe('advance_received');

    $change = $order->statusChanges()->sole();
    expect($change->from->value)->toBe('proforma_issued');
    expect($change->to->value)->toBe('advance_received');
    expect($change->user_id)->toBe($employee->id);
});

test('an advance paid before the proforma does not move the order', function () {
    $order = payableOrder('draft');

    $this->actingAs(User::factory()->create())
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 40000]))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status->value)->toBe('draft');
    expect($order->netPaid())->toBe(40000);
});

test('the balance paid at delivery settles the order', function () {
    $order = payableOrder('delivered');
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['kind' => 'advance', 'amount' => 40000]))
        ->assertSessionHasErrors('kind');

    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000, 'payment_method_id' => $this->cash->id]);

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['kind' => 'balance', 'amount' => 30000]))
        ->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('delivered');

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['kind' => 'balance', 'amount' => 30000]))
        ->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('settled');
});

test('a method that needs a reference refuses a payment without one', function () {
    $mobile = PaymentMethod::factory()->create(['code' => 'mobile', 'requires_reference' => true]);
    $order = payableOrder();
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.payments.store', $order), paymentPayload($mobile))
        ->assertSessionHasErrors('reference');
    expect(Payment::count())->toBe(0);

    $this->post(route('orders.payments.store', $order), paymentPayload($mobile, ['reference' => 'MP240101.1234']))
        ->assertSessionHasNoErrors();
    expect(Payment::sole()->reference)->toBe('MP240101.1234');
});

test('a payment is rejected for invalid data', function (Closure $mutate, string $field) {
    $inactive = PaymentMethod::factory()->create(['active' => false]);
    $payload = $mutate(paymentPayload($this->cash), $inactive);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.payments.store', payableOrder()), $payload)
        ->assertSessionHasErrors($field);

    expect(Payment::count())->toBe(0);
})->with([
    'zero amount' => [fn (array $p) => [...$p, 'amount' => 0], 'amount'],
    'negative amount' => [fn (array $p) => [...$p, 'amount' => -5], 'amount'],
    'fractional amount' => [fn (array $p) => [...$p, 'amount' => 10.5], 'amount'],
    'more than the order costs' => [fn (array $p) => [...$p, 'amount' => 100001], 'amount'],
    'unknown method' => [fn (array $p) => [...$p, 'payment_method_id' => 9999], 'payment_method_id'],
    'inactive method' => [fn (array $p, PaymentMethod $inactive) => [...$p, 'payment_method_id' => $inactive->id], 'payment_method_id'],
    'unknown kind' => [fn (array $p) => [...$p, 'kind' => 'gift'], 'kind'],
    'missing date' => [fn (array $p) => [...$p, 'paid_on' => ''], 'paid_on'],
    'balance before delivery' => [fn (array $p) => [...$p, 'kind' => 'balance'], 'kind'],
]);

test('an advance cannot be taken once the order is delivered, cancelled or over', function (string $status) {
    $this->actingAs(User::factory()->create())
        ->post(route('orders.payments.store', payableOrder($status)), paymentPayload($this->cash))
        ->assertSessionHasErrors('kind');

    expect(Payment::count())->toBe(0);
})->with(['delivered', 'settled', 'closed', 'cancelled']);

test('the total paid can never go over what the order costs', function () {
    $order = payableOrder('received');
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 90000]))->assertSessionHasNoErrors();
    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 10001]))->assertSessionHasErrors('amount');
    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 10000]))->assertSessionHasNoErrors();

    expect($order->refresh()->netPaid())->toBe(100000);
});

test('a field agent records a payment for an order of their zone only', function () {
    $zone = Zone::factory()->create();
    $own = Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'proforma_issued', 'total_sale' => 100000, 'advance_due' => 40000]);
    $foreign = payableOrder();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zone);

    $this->actingAs($agent);

    $this->post(route('orders.payments.store', $own), paymentPayload($this->cash))->assertSessionHasNoErrors();
    expect(Payment::sole()->collected_by)->toBe($agent->id);

    $this->post(route('orders.payments.store', $foreign), paymentPayload($this->cash))->assertForbidden();
    expect(Payment::count())->toBe(1);
});

test('guests are sent to the login page', function () {
    $this->post(route('orders.payments.store', payableOrder()), paymentPayload($this->cash))
        ->assertRedirect(route('login'));
});

test('the same submission sent twice takes the money once', function () {
    $this->actingAs(User::factory()->create());
    $order = payableOrder();
    $payload = paymentPayload($this->cash, ['amount' => 40000, 'idempotency_key' => 'form-1']);

    $this->post(route('orders.payments.store', $order), $payload)->assertSessionHasNoErrors();
    $this->post(route('orders.payments.store', $order), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('orders.show', $order));

    expect(Payment::count())->toBe(1);
    expect($order->refresh()->netPaid())->toBe(40000);
    expect(AuditLog::where('action', 'payment.recorded')->count())->toBe(1);
});

test('a replayed submission does not use up a receipt number', function () {
    $this->actingAs(User::factory()->create());
    $order = payableOrder();
    $payload = paymentPayload($this->cash, ['amount' => 10000, 'idempotency_key' => 'form-1']);

    $this->post(route('orders.payments.store', $order), $payload);
    $this->post(route('orders.payments.store', $order), $payload);
    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 5000, 'idempotency_key' => 'form-2']));

    expect(Payment::orderBy('id')->pluck('receipt_number')->all())->toBe([
        'REC-'.today()->format('Y').'-0001',
        'REC-'.today()->format('Y').'-0002',
    ]);
});

test('two different submissions of the same amount are two payments', function () {
    $this->actingAs(User::factory()->create());
    $order = payableOrder();

    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 10000, 'idempotency_key' => 'form-1']));
    $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['amount' => 10000, 'idempotency_key' => 'form-2']));

    expect(Payment::count())->toBe(2);
    expect($order->refresh()->netPaid())->toBe(20000);
});

test('the same key on another order is a new payment', function () {
    $this->actingAs(User::factory()->create());
    $payload = paymentPayload($this->cash, ['amount' => 10000, 'idempotency_key' => 'form-1']);

    $this->post(route('orders.payments.store', payableOrder()), $payload);
    $this->post(route('orders.payments.store', payableOrder()), $payload);

    expect(Payment::count())->toBe(2);
});

test('a payment without a key is still recorded, and without protection against a replay', function () {
    $this->actingAs(User::factory()->create());
    $order = payableOrder();
    $payload = paymentPayload($this->cash, ['amount' => 10000]);

    $this->post(route('orders.payments.store', $order), $payload)->assertSessionHasNoErrors();
    $this->post(route('orders.payments.store', $order), $payload)->assertSessionHasNoErrors();

    expect(Payment::count())->toBe(2);
});

test('a key that is too long is rejected', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.payments.store', payableOrder()), paymentPayload($this->cash, ['idempotency_key' => str_repeat('a', 65)]))
        ->assertSessionHasErrors('idempotency_key');

    expect(Payment::count())->toBe(0);
});
