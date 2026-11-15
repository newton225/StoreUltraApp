<?php

use App\Enums\Permission;
use App\Enums\PermissionAction;
use App\Models\AuditLog;
use App\Models\OrderDiscount;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Support\CommercialSheet;

function discountPayload(array $overrides = []): array
{
    return ['amount' => 10000, 'reason' => 'Geste commercial', 'password' => 'password', ...$overrides];
}

test('a discount lowers the price, keeps its reason and is traced', function () {
    $order = payableOrder('proforma_issued', 100000, 40000);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('orders.discount.store', $order), discountPayload())->assertSessionHasNoErrors();

    $discount = $order->discounts()->sole();

    expect($order->refresh()->total_sale)->toBe(90000)
        ->and($discount->amount)->toBe(10000)
        ->and($discount->reason)->toBe('Geste commercial')
        ->and($discount->total_before)->toBe(100000)
        ->and($discount->total_after)->toBe(90000)
        ->and($discount->user_id)->toBe($owner->id)
        ->and($order->discountTotal())->toBe(10000);

    $entry = AuditLog::where('action', 'order.discount_granted')->sole();

    expect($entry->subject_id)->toBe($order->id)
        ->and($entry->before['total_sale'])->toBe(100000)
        ->and($entry->after['total_sale'])->toBe(90000)
        ->and($entry->after['reason'])->toBe('Geste commercial');
});

test('an advance still to be received follows the price, one already received stays', function () {
    $waiting = payableOrder('proforma_issued', 100000, 40000);
    $paid = payableOrder('advance_received', 100000, 40000);
    Payment::factory()->for($paid)->create(['kind' => 'advance', 'amount' => 40000]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('orders.discount.store', $waiting), discountPayload(['amount' => 50000]));
    $this->actingAs($owner)->post(route('orders.discount.store', $paid), discountPayload(['amount' => 50000]));

    expect($waiting->refresh()->advance_due)->toBe(20000)
        ->and($paid->refresh()->advance_due)->toBe(40000);
});

test('the price never goes below what is already paid', function () {
    $order = payableOrder('advance_received', 100000, 40000);
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => 40000]);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('orders.discount.store', $order), discountPayload(['amount' => 60001]))
        ->assertSessionHasErrors('amount');

    expect($order->refresh()->total_sale)->toBe(100000)
        ->and(OrderDiscount::count())->toBe(0);

    $this->post(route('orders.discount.store', $order), discountPayload(['amount' => 60000]))->assertSessionHasNoErrors();

    expect($order->refresh()->total_sale)->toBe(40000);
});

test('a discount is refused once the kits are delivered', function (string $status) {
    $order = payableOrder($status, 100000, 40000);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('orders.discount.store', $order), discountPayload())
        ->assertSessionHasErrors('amount');

    expect($order->refresh()->total_sale)->toBe(100000);
})->with(['delivered', 'settled', 'closed', 'cancelled']);

test('a discount needs a reason, a positive amount and the password', function (array $override, string $field) {
    $order = payableOrder();

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('orders.discount.store', $order), discountPayload($override))
        ->assertSessionHasErrors($field);

    expect($order->refresh()->total_sale)->toBe(100000);
})->with([
    'no reason' => [['reason' => ''], 'reason'],
    'zero' => [['amount' => 0], 'amount'],
    'negative' => [['amount' => -5], 'amount'],
    'wrong password' => [['password' => 'nope'], 'password'],
]);

test('only a profile with the discount action may grant one', function () {
    $order = payableOrder();
    $without = Profile::factory()->create(['permissions' => [Permission::ManageOrders->value, PermissionAction::OrdersCancel->value]]);
    $with = Profile::factory()->create(['permissions' => [Permission::ManageOrders->value, PermissionAction::OrdersDiscount->value]]);

    $this->actingAs(User::factory()->create(['profile_id' => $without->id])->refresh())
        ->post(route('orders.discount.store', $order), discountPayload())
        ->assertForbidden();

    expect($order->refresh()->total_sale)->toBe(100000);

    $this->actingAs(User::factory()->create(['profile_id' => $with->id])->refresh())
        ->post(route('orders.discount.store', $order), discountPayload())
        ->assertSessionHasNoErrors();

    expect($order->refresh()->total_sale)->toBe(90000);
});

test('a discount is never modified or removed', function () {
    $order = payableOrder();
    $this->actingAs(User::factory()->owner()->create())->post(route('orders.discount.store', $order), discountPayload());
    $discount = $order->discounts()->sole();

    $discount->update(['amount' => 1]);
    $discount->delete();

    expect($discount->fresh()->amount)->toBe(10000);
});

test('the order page lists the discounts and offers the button only when it is allowed', function () {
    $order = payableOrder();
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner)->post(route('orders.discount.store', $order), discountPayload());

    $this->get(route('orders.show', $order))->assertInertia(fn ($page) => $page
        ->where('order.may_discount', true)
        ->where('order.discounts.0.amount', 10000)
        ->where('order.discounts.0.reason', 'Geste commercial'));

    $order->forceFill(['status' => 'delivered'])->save();

    $this->get(route('orders.show', $order))->assertInertia(fn ($page) => $page->where('order.may_discount', false));
});

test('the sheet of the documents shows the discount', function () {
    $order = payableOrder();
    $this->actingAs(User::factory()->owner()->create())->post(route('orders.discount.store', $order), discountPayload());

    $sheet = CommercialSheet::forProforma($order->refresh());

    expect($sheet['discount'])->toBe(10000)
        ->and($sheet['total'])->toBe(90000);
});
