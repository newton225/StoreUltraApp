<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\BusinessSettings;

test('an order moves one step at a time along its life', function () {
    $order = Order::factory()->create(['status' => 'draft']);
    $this->actingAs(User::factory()->create());

    foreach (['proforma_issued', 'advance_received', 'ordered', 'received', 'delivered', 'settled', 'closed'] as $status) {
        if ($status === 'delivered') {
            Delivery::factory()->for($order)->create(['kits_delivered' => $order->kits_count]);
        }

        if ($status === 'settled') {
            Payment::factory()->for($order)->create(['kind' => 'balance', 'amount' => $order->total_sale]);
        }

        $this->post(route('orders.transition', $order), ['to' => $status, 'password' => 'password'])->assertSessionHasNoErrors();
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

test('cancelling an order on which money was received asks for the password, and a refusal is traced', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => 'advance_received']);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 10000]);
    $this->actingAs($user);

    $this->post(route('orders.transition', $order), ['to' => 'cancelled', 'reason' => 'Client parti'])->assertSessionHasErrors('password');
    $this->post(route('orders.transition', $order), ['to' => 'cancelled', 'reason' => 'Client parti', 'password' => 'not-my-password'])->assertSessionHasErrors('password');

    expect($order->refresh()->status->value)->toBe('advance_received')
        ->and(AuditLog::query()->where('action', 'order.step_password_failed')->count())->toBe(1);

    $this->post(route('orders.transition', $order), ['to' => 'cancelled', 'reason' => 'Client parti', 'password' => 'password'])->assertSessionHasNoErrors();

    expect($order->refresh()->status->value)->toBe('cancelled');
});

test('an order without any payment is cancelled without a password', function () {
    $order = Order::factory()->create(['status' => 'proforma_issued']);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.transition', $order), ['to' => 'cancelled', 'reason' => 'Doublon'])
        ->assertSessionHasNoErrors();

    expect($order->refresh()->status->value)->toBe('cancelled');
});

test('the critical steps asked by hand need the password, and the earlier ones do not', function () {
    $order = Order::factory()->create(['status' => 'received']);
    Delivery::factory()->for($order)->create(['kits_delivered' => $order->kits_count]);
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.transition', $order), ['to' => 'delivered'])->assertSessionHasErrors('password');
    expect($order->refresh()->status->value)->toBe('received');

    $this->post(route('orders.transition', $order), ['to' => 'delivered', 'password' => 'password'])->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('delivered');

    $draft = Order::factory()->create(['status' => 'draft']);
    $this->post(route('orders.transition', $draft), ['to' => 'proforma_issued'])->assertSessionHasNoErrors();
});

test('an order cannot be marked delivered while kits remain, nor settled while money is still due', function () {
    $order = Order::factory()->create(['status' => 'received', 'total_sale' => 100000]);
    $this->actingAs(User::factory()->create());

    $this->post(route('orders.transition', $order), ['to' => 'delivered', 'password' => 'password'])->assertSessionHasErrors();
    expect($order->refresh()->status->value)->toBe('received');

    Delivery::factory()->for($order)->create(['kits_delivered' => $order->kits_count]);
    $this->post(route('orders.transition', $order), ['to' => 'delivered', 'password' => 'password'])->assertSessionHasNoErrors();
    $this->post(route('orders.transition', $order), ['to' => 'settled', 'password' => 'password'])->assertSessionHasErrors();

    expect($order->refresh()->status->value)->toBe('delivered');
});

test('the documents of an order are listed in the order of the process', function () {
    $order = Order::factory()->create(['status' => 'delivered', 'total_sale' => 100000]);
    Payment::factory()->for($order)->create(['kind' => 'balance', 'amount' => 60000, 'paid_on' => today()]);
    Delivery::factory()->for($order)->create(['kits_delivered' => $order->kits_count, 'delivered_on' => today()]);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000, 'paid_on' => today()->subDays(3)]);

    $this->actingAs(User::factory()->owner()->create())->get(route('orders.show', $order))->assertInertia(fn ($page) => $page
        ->where('documents.targets', fn ($targets) => collect($targets)->pluck('type')->all() === ['proforma', 'receipt', 'delivery_note', 'invoice', 'receipt']));
});
