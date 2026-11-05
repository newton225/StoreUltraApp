<?php

use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopPayment;
use App\Models\User;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->cash = PaymentMethod::factory()->create(['code' => 'cash', 'requires_reference' => false]);
    $this->employee = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function shopPaymentPayload(PaymentMethod $method, array $overrides = []): array
{
    return [
        'kind' => 'payment',
        'amount' => 50000,
        'payment_method_id' => $method->id,
        'paid_on' => today()->toDateString(),
        ...$overrides,
    ];
}

test('the office records a payment to a shop for a purchase order', function () {
    $purchase = PurchaseOrder::factory()->for($this->shop)->create();

    $this->actingAs($this->employee)
        ->post(route('shops.payments.store', $this->shop), shopPaymentPayload($this->cash, ['purchase_order_id' => $purchase->id]))
        ->assertSessionHasNoErrors();

    $payment = ShopPayment::sole();

    expect($payment->shop_id)->toBe($this->shop->id);
    expect($payment->purchase_order_id)->toBe($purchase->id);
    expect($payment->amount)->toBe(50000);
    expect($payment->paid_by)->toBe($this->employee->id);
    expect($this->shop->netPaid())->toBe(50000);
    expect(AuditLog::where('action', 'shop_payment.recorded')->count())->toBe(1);
});

test('a payment does not need to be tied to a purchase order', function () {
    $this->actingAs($this->employee)
        ->post(route('shops.payments.store', $this->shop), shopPaymentPayload($this->cash))
        ->assertSessionHasNoErrors();

    expect(ShopPayment::sole()->purchase_order_id)->toBeNull();
});

test('a payment is rejected for invalid data', function (Closure $mutate, string $field) {
    $inactive = PaymentMethod::factory()->create(['active' => false]);
    $mobile = PaymentMethod::factory()->create(['requires_reference' => true]);
    $foreign = PurchaseOrder::factory()->create();

    $payload = $mutate(shopPaymentPayload($this->cash), $inactive, $mobile, $foreign);

    $this->actingAs($this->employee)
        ->post(route('shops.payments.store', $this->shop), $payload)
        ->assertSessionHasErrors($field);

    expect(ShopPayment::count())->toBe(0);
})->with([
    'zero amount' => [fn (array $p) => [...$p, 'amount' => 0], 'amount'],
    'fractional amount' => [fn (array $p) => [...$p, 'amount' => 10.5], 'amount'],
    'unknown kind' => [fn (array $p) => [...$p, 'kind' => 'gift'], 'kind'],
    'inactive method' => [fn (array $p, PaymentMethod $inactive) => [...$p, 'payment_method_id' => $inactive->id], 'payment_method_id'],
    'reference needed' => [fn (array $p, $i, PaymentMethod $mobile) => [...$p, 'payment_method_id' => $mobile->id], 'reference'],
    'purchase order of another shop' => [fn (array $p, $i, $m, PurchaseOrder $foreign) => [...$p, 'purchase_order_id' => $foreign->id], 'purchase_order_id'],
    'future date' => [fn (array $p) => [...$p, 'paid_on' => today()->addDay()->toDateString()], 'paid_on'],
]);

test('a shop can give money back, but not more than it was paid', function () {
    ShopPayment::factory()->for($this->shop)->create(['kind' => 'payment', 'amount' => 30000, 'payment_method_id' => $this->cash->id]);
    $this->actingAs($this->employee);

    $this->post(route('shops.payments.store', $this->shop), shopPaymentPayload($this->cash, ['kind' => 'refund', 'amount' => 30001]))
        ->assertSessionHasErrors('amount');

    $this->post(route('shops.payments.store', $this->shop), shopPaymentPayload($this->cash, ['kind' => 'refund', 'amount' => 10000]))
        ->assertSessionHasNoErrors();

    expect($this->shop->netPaid())->toBe(20000);
});

test('a shop payment can neither be changed nor deleted', function () {
    $payment = ShopPayment::factory()->for($this->shop)->create(['amount' => 1000, 'payment_method_id' => $this->cash->id]);

    expect($payment->update(['amount' => 1]))->toBeFalse();
    expect($payment->delete())->toBeFalse();
    expect($payment->refresh()->amount)->toBe(1000);
});

test('a field agent cannot record a payment to a shop', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('shops.payments.store', $this->shop), shopPaymentPayload($this->cash))
        ->assertForbidden();

    expect(ShopPayment::count())->toBe(0);
});
