<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;

beforeEach(function () {
    $this->cash = PaymentMethod::factory()->create(['code' => 'cash', 'requires_reference' => false]);
});

function paidOrder(PaymentMethod $method, string $status = 'proforma_issued', int $paid = 40000): array
{
    $order = payableOrder($status);
    $payment = Payment::factory()->for($order)->create([
        'kind' => 'advance',
        'amount' => $paid,
        'payment_method_id' => $method->id,
    ]);

    return [$order, $payment];
}

test('the net amount paid counts refunds against payments', function () {
    [$order] = paidOrder($this->cash, 'cancelled', 40000);
    Payment::factory()->for($order)->create(['kind' => 'refund', 'amount' => 15000, 'payment_method_id' => $this->cash->id]);

    expect($order->netPaid())->toBe(25000);
});

test('the office refunds part of what was paid, even on a cancelled order', function () {
    [$order] = paidOrder($this->cash, 'cancelled', 40000);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['kind' => 'refund', 'amount' => 15000]))
        ->assertSessionHasNoErrors();

    expect($order->refresh()->netPaid())->toBe(25000);
    expect(Payment::where('kind', 'refund')->sole()->receipt_number)->not->toBeNull();
});

test('a refund cannot be more than what was paid', function () {
    [$order] = paidOrder($this->cash, 'cancelled', 40000);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['kind' => 'refund', 'amount' => 40001]))
        ->assertSessionHasErrors('amount');
});

test('a field agent cannot refund', function () {
    $zone = Zone::factory()->create();
    $order = Order::factory()->for(Client::factory()->for($zone))->create(['status' => 'cancelled', 'total_sale' => 100000]);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000, 'payment_method_id' => $this->cash->id]);
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zone);

    $this->actingAs($agent)
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, ['kind' => 'refund', 'amount' => 1000]))
        ->assertForbidden();

    expect(Payment::where('kind', 'refund')->count())->toBe(0);
});

test('a mistaken payment is corrected by a reversal that points to it', function () {
    [$order, $payment] = paidOrder($this->cash, 'proforma_issued', 20000);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, [
            'kind' => 'refund',
            'amount' => 20000,
            'reverses_payment_id' => $payment->id,
        ]))
        ->assertSessionHasNoErrors();

    $reversal = Payment::where('kind', 'refund')->sole();

    expect($reversal->reverses_payment_id)->toBe($payment->id);
    expect($order->refresh()->netPaid())->toBe(0);
    expect($payment->refresh()->amount)->toBe(20000);
});

test('a reversal must cancel the whole payment', function () {
    [$order, $payment] = paidOrder($this->cash, 'proforma_issued', 20000);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.payments.store', $order), paymentPayload($this->cash, [
            'kind' => 'refund',
            'amount' => 5000,
            'reverses_payment_id' => $payment->id,
        ]))
        ->assertSessionHasErrors('amount');
});

test('a payment cannot be reversed twice, nor a refund, nor a payment of another order', function () {
    [$order, $payment] = paidOrder($this->cash, 'proforma_issued', 20000);
    $reversal = Payment::factory()->for($order)->create([
        'kind' => 'refund',
        'amount' => 20000,
        'payment_method_id' => $this->cash->id,
        'reverses_payment_id' => $payment->id,
    ]);
    [, $foreignPayment] = paidOrder($this->cash, 'proforma_issued', 3000);

    $this->actingAs(User::factory()->create());

    foreach ([$payment->id => 20000, $reversal->id => 20000, $foreignPayment->id => 3000] as $id => $amount) {
        $this->post(route('orders.payments.store', $order), paymentPayload($this->cash, [
            'kind' => 'refund',
            'amount' => $amount,
            'reverses_payment_id' => $id,
        ]))->assertSessionHasErrors('reverses_payment_id');
    }

    expect(Payment::where('kind', 'refund')->count())->toBe(1);
});

test('a payment can neither be changed nor deleted', function () {
    [, $payment] = paidOrder($this->cash);

    expect($payment->update(['amount' => 1]))->toBeFalse();
    expect($payment->delete())->toBeFalse();
    expect($payment->refresh()->amount)->toBe(40000);
});

test('the order cannot go to advance received before the advance is covered', function () {
    $order = payableOrder('proforma_issued');
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.transition', $order), ['to' => 'advance_received'])->assertSessionHasErrors('to');
    expect($order->refresh()->status->value)->toBe('proforma_issued');

    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000, 'payment_method_id' => $this->cash->id]);

    $this->post(route('orders.transition', $order), ['to' => 'advance_received'])->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('advance_received');
});

test('the advance is not needed when the setting says it is optional', function () {
    app(BusinessSettings::class)->set('advance_required', false);
    $order = payableOrder('proforma_issued');

    $this->actingAs(User::factory()->create())
        ->post(route('orders.transition', $order), ['to' => 'advance_received'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status->value)->toBe('advance_received');
});
