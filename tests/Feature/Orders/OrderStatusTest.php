<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\BusinessSettings;

test('an order moves one step at a time along its life', function () {
    $order = Order::factory()->create(['status' => 'draft']);
    $this->actingAs(User::factory()->create());

    foreach (['proforma_issued', 'advance_received', 'ordered', 'received', 'delivered', 'settled', 'closed'] as $status) {
        $this->post(route('orders.transition', $order), ['to' => $status])->assertSessionHasNoErrors();
        expect($order->refresh()->status->value)->toBe($status);
    }
});

test('every change of status is kept in the history and the journal', function () {
    $order = Order::factory()->create(['status' => 'draft']);
    $employee = User::factory()->create();

    $this->actingAs($employee)->post(route('orders.transition', $order), ['to' => 'proforma_issued']);

    $change = $order->statusChanges()->sole();

    expect($change->from->value)->toBe('draft');
    expect($change->to->value)->toBe('proforma_issued');
    expect($change->user_id)->toBe($employee->id);

    $entry = AuditLog::where('action', 'order.status_changed')->sole();

    expect($entry->subject_id)->toBe($order->id);
    expect($entry->before)->toBe(['status' => 'draft']);
    expect($entry->after)->toBe(['status' => 'proforma_issued']);
});

test('a step cannot be skipped and an order cannot go backwards', function (string $from, string $to) {
    $order = Order::factory()->create(['status' => $from]);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.transition', $order), ['to' => $to])
        ->assertSessionHasErrors('to');

    expect($order->refresh()->status->value)->toBe($from);
    expect($order->statusChanges()->count())->toBe(0);
})->with([
    'skipping a step' => ['draft', 'ordered'],
    'going backwards' => ['ordered', 'proforma_issued'],
    'staying where it is' => ['draft', 'draft'],
    'leaving closed' => ['closed', 'draft'],
]);

test('an unknown status is rejected', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('orders.transition', $order), ['to' => 'flying'])
        ->assertSessionHasErrors('to');
});

test('an order can be cancelled before it is delivered, with a reason', function (string $from) {
    $order = Order::factory()->create(['status' => $from]);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.transition', $order), ['to' => 'cancelled', 'reason' => 'Client absent'])
        ->assertSessionHasNoErrors();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->statusChanges()->sole()->reason)->toBe('Client absent');
})->with(['draft', 'proforma_issued', 'advance_received', 'ordered', 'received']);

test('a cancellation needs a reason', function () {
    $order = Order::factory()->create(['status' => 'draft']);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.transition', $order), ['to' => 'cancelled'])
        ->assertSessionHasErrors('reason');

    expect($order->refresh()->status->value)->toBe('draft');
});

test('an order cannot be cancelled once it is delivered or over', function (string $from) {
    $order = Order::factory()->create(['status' => $from]);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.transition', $order), ['to' => 'cancelled', 'reason' => 'Too late'])
        ->assertSessionHasErrors('to');

    expect($order->refresh()->status->value)->toBe($from);
})->with(['delivered', 'settled', 'closed', 'cancelled']);

test('a field agent cannot change the status of an order', function () {
    $order = Order::factory()->create(['status' => 'draft']);

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('orders.transition', $order), ['to' => 'proforma_issued'])
        ->assertForbidden();

    expect($order->refresh()->status->value)->toBe('draft');
});

test('the statuses know which ones are final', function () {
    expect(OrderStatus::Closed->isFinal())->toBeTrue();
    expect(OrderStatus::Cancelled->isFinal())->toBeTrue();
    expect(OrderStatus::Draft->isFinal())->toBeFalse();
});

test('the order page says whether the advance still blocks the next step', function () {
    $order = Order::factory()->create(['status' => 'proforma_issued', 'advance_due' => 40000]);
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('payments.advance_blocking', true)->where('payments.advance_outstanding', 40000));

    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000]);

    $this->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('payments.advance_blocking', false));

    app(BusinessSettings::class)->set('advance_required', false);
    $unpaid = Order::factory()->create(['status' => 'proforma_issued', 'advance_due' => 40000]);

    $this->get(route('orders.show', $unpaid))
        ->assertInertia(fn ($page) => $page->where('payments.advance_blocking', false));
});
