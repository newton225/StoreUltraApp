<?php

use App\Actions\Purchases\GeneratePurchaseOrders;
use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->shop1 = Shop::factory()->create(['name' => 'Boutique 1']);
    $this->shop2 = Shop::factory()->create(['name' => 'Boutique 2']);
    $this->iron = Product::factory()->for($this->shop1)->create();
    $this->cement = Product::factory()->for($this->shop1)->create();
    $this->panel = Product::factory()->for($this->shop2)->create();
    $this->week = '2026-07-13';
});

function generateFor(string $week, ?User $user = null): array
{
    return app(GeneratePurchaseOrders::class)->handle(Carbon::parse($week), $user);
}

test('the needs of the week are grouped into one purchase order per shop', function () {
    readyOrder($this->week, [[$this->iron, 6, 1000], [$this->panel, 3, 2000]]);
    readyOrder($this->week, [[$this->iron, 3, 1000], [$this->cement, 1, 500]]);

    $result = generateFor($this->week);

    expect($result)->toBe(['created' => 2, 'unassigned' => 0]);

    $first = PurchaseOrder::where('shop_id', $this->shop1->id)->sole();
    $lines = $first->items()->orderBy('product_id')->get();

    expect($first->status->value)->toBe('draft');
    expect($first->week->toDateString())->toBe($this->week);
    expect($first->expected_total)->toBe(9500);
    expect($lines)->toHaveCount(2);
    expect($lines[0]->product_id)->toBe($this->iron->id);
    expect($lines[0]->quantity_ordered)->toBe(9);
    expect($lines[0]->unit_price_expected)->toBe(1000);
    expect($lines[0]->quantity_received)->toBe(0);

    $second = PurchaseOrder::where('shop_id', $this->shop2->id)->sole();
    expect($second->expected_total)->toBe(6000);
    expect($second->items->sole()->quantity_ordered)->toBe(3);
});

test('the same product at two frozen prices makes two lines', function () {
    readyOrder($this->week, [[$this->iron, 2, 1000]]);
    readyOrder($this->week, [[$this->iron, 3, 1200]]);

    generateFor($this->week);

    $lines = PurchaseOrder::sole()->items()->orderBy('unit_price_expected')->get();

    expect($lines)->toHaveCount(2);
    expect([$lines[0]->quantity_ordered, $lines[0]->unit_price_expected])->toBe([2, 1000]);
    expect([$lines[1]->quantity_ordered, $lines[1]->unit_price_expected])->toBe([3, 1200]);
});

test('only paid orders of that week are taken', function () {
    readyOrder($this->week, [[$this->iron, 1, 1000]], 'proforma_issued');
    readyOrder($this->week, [[$this->iron, 1, 1000]], 'cancelled');
    readyOrder($this->week, [[$this->iron, 1, 1000]], 'ordered');
    readyOrder('2026-07-20', [[$this->iron, 1, 1000]]);

    expect(generateFor($this->week))->toBe(['created' => 0, 'unassigned' => 0]);
    expect(PurchaseOrder::count())->toBe(0);
});

test('a date inside the week is enough to pick the week', function () {
    readyOrder($this->week, [[$this->iron, 1, 1000]]);

    expect(generateFor('2026-07-16')['created'])->toBe(1);
    expect(PurchaseOrder::sole()->week->toDateString())->toBe($this->week);
});

test('lines already on a purchase order are not taken again, but new needs are', function () {
    readyOrder($this->week, [[$this->iron, 2, 1000]]);
    generateFor($this->week);

    expect(generateFor($this->week))->toBe(['created' => 0, 'unassigned' => 0]);

    readyOrder($this->week, [[$this->iron, 1, 1000]]);

    expect(generateFor($this->week)['created'])->toBe(1);
    expect(PurchaseOrder::count())->toBe(2);
    expect(OrderItem::whereNull('purchase_order_id')->count())->toBe(0);
});

test('a line whose product has no shop is reported and left aside', function () {
    $orphan = Product::factory()->create(['shop_id' => null]);
    readyOrder($this->week, [[$orphan, 2, 1000], [$this->iron, 1, 1000]]);

    expect(generateFor($this->week))->toBe(['created' => 1, 'unassigned' => 1]);
    expect(OrderItem::whereNull('purchase_order_id')->sole()->product_id)->toBe($orphan->id);
});

test('the purchase orders are numbered, attributed and recorded in the journal', function () {
    readyOrder($this->week, [[$this->iron, 1, 1000], [$this->panel, 1, 1000]]);
    $user = User::factory()->create();

    generateFor($this->week, $user);

    $numbers = PurchaseOrder::orderBy('id')->pluck('reference')->all();

    expect($numbers)->toBe([sprintf('BC-%d-0001', now()->year), sprintf('BC-%d-0002', now()->year)]);
    expect(PurchaseOrder::first()->created_by)->toBe($user->id);
    expect(AuditLog::where('action', 'purchase_order.created')->count())->toBe(2);
});

test('the office generates the purchase orders of a week', function () {
    readyOrder($this->week, [[$this->iron, 1, 1000]]);

    $this->actingAs(User::factory()->create())
        ->post(route('purchases.generate'), ['week' => '2026-07-15'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('purchases.index', ['week' => $this->week]));

    expect(PurchaseOrder::count())->toBe(1);
});

test('the week is required and must be a date', function (string $week) {
    $this->actingAs(User::factory()->create())
        ->post(route('purchases.generate'), ['week' => $week])
        ->assertSessionHasErrors('week');
})->with(['', 'soon']);

test('a field agent cannot generate purchase orders', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('purchases.generate'), ['week' => $this->week])
        ->assertForbidden();
});
