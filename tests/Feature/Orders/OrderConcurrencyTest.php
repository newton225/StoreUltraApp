<?php

use App\Actions\Orders\TransitionOrder;
use App\Actions\Payments\RecordPayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\InvalidPayment;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;

/*
 * Two requests that loaded the same order before either was saved: the second works on a copy that
 * is already out of date. SQLite ignores row locks, so these tests prove that the state is read
 * again from the database before it is used, which is what the lock relies on in production.
 */

test('two requests that saw the same state cannot both move the order', function () {
    $order = Order::factory()->create(['status' => 'draft']);
    $first = Order::findOrFail($order->id);
    $second = Order::findOrFail($order->id);
    $transition = app(TransitionOrder::class);

    $transition->handle($first, OrderStatus::ProformaIssued);

    expect(fn () => $transition->handle($second, OrderStatus::ProformaIssued))->toThrow(InvalidOrderTransition::class);
    expect($order->refresh()->status)->toBe(OrderStatus::ProformaIssued);
    expect($order->statusChanges()->count())->toBe(1);
    expect(AuditLog::where('action', 'order.status_changed')->count())->toBe(1);
});

test('an order cancelled by one request cannot be cancelled again by another', function () {
    $order = Order::factory()->create(['status' => 'draft']);
    $first = Order::findOrFail($order->id);
    $second = Order::findOrFail($order->id);
    $transition = app(TransitionOrder::class);

    $transition->handle($first, OrderStatus::Cancelled, 'Doublon');

    expect(fn () => $transition->handle($second, OrderStatus::Cancelled, 'Doublon'))->toThrow(InvalidOrderTransition::class);
    expect($order->statusChanges()->count())->toBe(1);
});

test('an order cancelled by one request cannot be moved forward by another', function () {
    $order = Order::factory()->create(['status' => 'draft']);
    $stale = Order::findOrFail($order->id);
    $transition = app(TransitionOrder::class);

    $transition->handle(Order::findOrFail($order->id), OrderStatus::Cancelled, 'Erreur');

    expect(fn () => $transition->handle($stale, OrderStatus::ProformaIssued))->toThrow(InvalidOrderTransition::class);
    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    expect($order->statusChanges()->count())->toBe(1);
});

test('the order given to the action reflects its new state and is not left modified', function () {
    $order = Order::factory()->create(['status' => 'draft']);

    app(TransitionOrder::class)->handle($order, OrderStatus::ProformaIssued);

    expect($order->status)->toBe(OrderStatus::ProformaIssued);
    expect($order->isDirty())->toBeFalse();
});

test('two payments that both saw the amount still to pay cannot exceed it', function () {
    $method = PaymentMethod::factory()->create(['requires_reference' => false]);
    $order = payableOrder();
    $first = Order::findOrFail($order->id);
    $second = Order::findOrFail($order->id);
    $record = app(RecordPayment::class);

    $record->handle($first, PaymentKind::Advance, 70000, $method, null, today());

    expect(fn () => $record->handle($second, PaymentKind::Advance, 70000, $method, null, today()))->toThrow(InvalidPayment::class);
    expect((int) Payment::where('order_id', $order->id)->sum('amount'))->toBe(70000);
});
