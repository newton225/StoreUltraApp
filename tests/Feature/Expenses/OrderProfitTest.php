<?php

use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\OrderProfit;

/**
 * An order of 3 priced kits: it sells for 16 500 F and should cost 12 000 F.
 */
function profitOrder(string $status = 'settled'): Order
{
    return realOrder($status, 3, 5000);
}

function bought(Order $order, int $actualPriceOfFirstLine): void
{
    $purchase = PurchaseOrder::factory()->create();

    foreach ($order->items()->orderBy('id')->get() as $index => $item) {
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchase->id,
            'product_id' => $item->product_id,
            'quantity_ordered' => $item->quantity,
            'unit_price_expected' => $item->unit_purchase_price,
            'unit_price_actual' => $index === 0 ? $actualPriceOfFirstLine : null,
        ]);
        $item->update(['purchase_order_id' => $purchase->id]);
    }
}

function logistics(Order $order, int $amount, bool $cancelled = false): void
{
    $expense = Expense::factory()->create(['amount' => $amount, 'cancelled_at' => $cancelled ? now() : null]);
    ExpenseAllocation::factory()->create(['expense_id' => $expense->id, 'order_id' => $order->id, 'amount' => $amount]);
}

function pay(Order $order, int $amount): void
{
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => $amount, 'payment_method_id' => PaymentMethod::factory()->create()->id]);
}

test('without any purchase or expense the real figures follow the theory', function () {
    $order = profitOrder();
    pay($order, 16500);

    $profit = app(OrderProfit::class)->forOrder($order);

    expect($profit)->toMatchArray([
        'sale' => 16500,
        'collected' => 16500,
        'theoretical_purchase' => 12000,
        'theoretical_margin' => 4500,
        'actual_purchase' => 12000,
        'logistics' => 0,
        'real_margin' => 4500,
        'projected_margin' => 4500,
        'gap' => 0,
    ]);
});

test('the price really paid to the shops and the logistics lower the margin', function () {
    $order = profitOrder();
    pay($order, 16500);
    bought($order, 1200);
    logistics($order, 1000);

    $profit = app(OrderProfit::class)->forOrder($order);

    expect($profit['actual_purchase'])->toBe(13200);
    expect($profit['logistics'])->toBe(1000);
    expect($profit['real_margin'])->toBe(2300);
    expect($profit['projected_margin'])->toBe(2300);
    expect($profit['theoretical_margin'])->toBe(4500);
    expect($profit['gap'])->toBe(-2200);
});

test('the real margin counts what was collected, the projected one what will be', function () {
    $order = profitOrder('delivered');
    pay($order, 5000);
    bought($order, 1200);
    logistics($order, 1000);

    $profit = app(OrderProfit::class)->forOrder($order);

    expect($profit['real_margin'])->toBe(5000 - 13200 - 1000);
    expect($profit['projected_margin'])->toBe(16500 - 13200 - 1000);
});

test('a cancelled expense is left out', function () {
    $order = profitOrder();
    logistics($order, 1000);
    logistics($order, 5000, cancelled: true);

    expect(app(OrderProfit::class)->forOrder($order)['logistics'])->toBe(1000);
});

test('several orders are computed at once and keyed by order', function () {
    $first = profitOrder();
    $second = profitOrder();
    logistics($second, 700);

    $profits = app(OrderProfit::class)->forOrders(Order::query()->whereKey([$first->id, $second->id])->get());

    expect($profits)->toHaveKeys([$first->id, $second->id]);
    expect($profits[$first->id]['logistics'])->toBe(0);
    expect($profits[$second->id]['logistics'])->toBe(700);
});
