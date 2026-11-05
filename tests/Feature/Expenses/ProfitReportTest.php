<?php

use App\Enums\Permission;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

function paidOrder2(string $status, int $kits = 3, ?Zone $zone = null, string $week = '2026-07-13'): Order
{
    $order = realOrder($status, $kits, 0);
    $order->client->update(['name' => 'Client '.$order->id]);

    if ($zone !== null) {
        $order->client->update(['zone_id' => $zone->id]);
    }

    $order->forceFill(['delivery_week' => $week])->save();
    Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => $order->total_sale, 'payment_method_id' => PaymentMethod::factory()->create()->id]);

    return $order;
}

test('guests are sent to the login page', function () {
    $this->get(route('profits.index'))->assertRedirect(route('login'));
});

test('the owner sees the profit, an employee only when the profile grants it, an agent never', function () {
    $this->actingAs(User::factory()->owner()->create())->get(route('profits.index'))->assertOk();

    $employee = User::factory()->create();
    $this->actingAs($employee)->get(route('profits.index'))->assertForbidden();

    withProfile($employee, [Permission::ViewProfit]);
    $this->actingAs($employee)->get(route('profits.index'))->assertOk();

    $this->actingAs(User::factory()->agent()->create())->get(route('profits.index'))->assertForbidden();
});

test('by default only the settled and closed orders are counted, and totals add up', function () {
    $a = paidOrder2('settled', 3);
    $b = paidOrder2('closed', 3);
    paidOrder2('delivered', 3);
    paidOrder2('cancelled', 3);
    $expense = Expense::factory()->create(['amount' => 1000]);
    ExpenseAllocation::factory()->create(['expense_id' => $expense->id, 'order_id' => $a->id, 'amount' => 1000]);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('profits.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('profits/index')
            ->where('totals.orders', 2)
            ->where('totals.sale', 33000)
            ->where('totals.theoretical_margin', 9000)
            ->where('totals.logistics', 1000)
            ->where('totals.real_margin', 33000 - 24000 - 1000)
            ->where('totals.gap', -1000));

    expect($b->exists)->toBeTrue();
});

test('every order can be included', function () {
    paidOrder2('settled');
    paidOrder2('delivered');
    paidOrder2('cancelled');

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('profits.index', ['scope' => 'all', 'from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page->where('totals.orders', 2));
});

test('the figures can be grouped by week, zone, client or kit', function (string $group, int $rows) {
    $zoneA = Zone::factory()->create(['name' => 'Bouna']);
    $zoneB = Zone::factory()->create(['name' => 'Tanda']);
    paidOrder2('settled', 3, $zoneA, '2026-07-13');
    paidOrder2('settled', 3, $zoneA, '2026-07-20');
    paidOrder2('settled', 3, $zoneB, '2026-07-20');

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('profits.index', ['group' => $group, 'from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', $rows)
            ->where('rows', fn ($all) => collect($all)->sum('orders') === 3));
})->with([
    'week' => ['week', 2],
    'zone' => ['zone', 2],
    'client' => ['client', 3],
    'kit' => ['kit', 3],
]);

test('the figures can be limited to a zone and to a period', function () {
    $zone = Zone::factory()->create();
    paidOrder2('settled', 3, $zone, '2026-07-13');
    paidOrder2('settled', 3, null, '2026-07-13');
    paidOrder2('settled', 3, $zone, '2026-09-07');

    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('profits.index', ['zone' => $zone->id, 'from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page->where('totals.orders', 1));

    $this->get(route('profits.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page->where('totals.orders', 2));
});

test('the order page shows its profit to whoever may see it only', function () {
    $order = paidOrder2('settled');
    $other = Client::factory()->create();

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('profit.theoretical_margin', 4500)->has('profit.expenses'));

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('profit', null));

    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($order->client->zone_id);
    $this->actingAs($agent)
        ->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('profit', null));

    expect($other->exists)->toBeTrue();
});
