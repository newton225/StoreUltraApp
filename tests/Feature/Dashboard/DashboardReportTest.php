<?php

use App\Enums\Permission;
use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
    $this->owner = User::factory()->owner()->create();
});

function dashboardOrder(string $status = 'delivered', string $week = '2026-07-13', int $paid = 0, ?Zone $zone = null): Order
{
    $order = realOrder($status, 3, 0);
    $order->forceFill(['delivery_week' => $week])->save();

    if ($zone !== null) {
        $order->client->update(['zone_id' => $zone->id]);
    }

    if ($paid > 0) {
        Payment::factory()->for($order)->create(['kind' => 'advance', 'amount' => $paid, 'payment_method_id' => PaymentMethod::factory()->create()->id]);
    }

    return $order;
}

test('guests are sent to the login page for every report', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('dashboard.pdf'))->assertRedirect(route('login'));
    $this->get(route('exports.show', 'orders'))->assertRedirect(route('login'));
});

test('the dashboard counts the orders, kits and money of the period', function () {
    $order = dashboardOrder('delivered', paid: 10000);
    Delivery::factory()->create(['order_id' => $order->id, 'kits_delivered' => 2]);
    $cancelled = dashboardOrder('cancelled');
    dashboardOrder('delivered', '2026-05-04', 500);

    $this->actingAs($this->owner)
        ->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('report.indicators.orders', 1)
            ->where('report.indicators.kits_ordered', 3)
            ->where('report.indicators.kits_delivered', 2)
            ->where('report.indicators.kits_remaining', 1)
            ->where('report.indicators.advances', 10000)
            ->where('report.indicators.sale_total', $order->total_sale)
            ->where('report.indicators.receivables', $order->total_sale - 10000)
            ->where('report.indicators.orders_by_status.delivered', 1)
            ->where('report.indicators.orders_by_status.cancelled', 1));

    expect($cancelled->status->value)->toBe('cancelled');
});

test('refunds and balances are told apart and a refund lowers what is owed back', function () {
    $order = dashboardOrder('delivered', paid: 10000);
    $method = PaymentMethod::factory()->create();
    Payment::factory()->for($order)->create(['kind' => 'balance', 'amount' => 3000, 'payment_method_id' => $method->id]);
    Payment::factory()->for($order)->create(['kind' => 'refund', 'amount' => 1000, 'payment_method_id' => $method->id]);

    $this->actingAs($this->owner)
        ->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.indicators.advances', 10000)
            ->where('report.indicators.balances', 3000)
            ->where('report.indicators.refunds', 1000)
            ->where('report.indicators.receivables', $order->total_sale - 12000));
});

test('the period defaults to the current month', function () {
    dashboardOrder('delivered', '2026-07-13');
    dashboardOrder('delivered', '2026-06-08');

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-07-01')
            ->where('filters.to', '2026-07-31')
            ->where('report.indicators.orders', 1));
});

test('the zone and kit filters narrow the figures', function () {
    $zone = Zone::factory()->create();
    dashboardOrder('delivered', zone: $zone);
    $other = dashboardOrder('delivered');

    $this->actingAs($this->owner)
        ->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31', 'zone' => $zone->id]))
        ->assertInertia(fn (Assert $page) => $page->where('report.indicators.orders', 1)->where('report.indicators.active_clients', 1));

    $this->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31', 'kit' => $other->kitVersion->kit_id]))
        ->assertInertia(fn (Assert $page) => $page->where('report.indicators.orders', 1));

    $this->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31', 'kit' => 9999]))
        ->assertInertia(fn (Assert $page) => $page->where('report.indicators.orders', 0));
});

test('a reversed period is put right', function () {
    dashboardOrder();

    $this->actingAs($this->owner)
        ->get(route('dashboard', ['from' => '2026-07-31', 'to' => '2026-07-01']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.from', '2026-07-01')->where('filters.to', '2026-07-31')->where('report.indicators.orders', 1));
});

test('a field agent only sees their sub-prefectures and nothing about shops or profit', function () {
    $mine = Zone::factory()->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($mine);
    dashboardOrder('delivered', zone: $mine);
    dashboardOrder('delivered');

    $this->actingAs($agent)
        ->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.indicators.orders', 1)
            ->where('report.indicators.active_clients', 1)
            ->where('report.shops', null)
            ->where('report.profit', null)
            ->where('report.alerts.client_cap', null)
            ->where('report.alerts.attachments', null)
            ->has('zones', 1)
            ->where('exports', ['orders', 'clients']));
});

test('profit is shown to the owner, and to an employee only when allowed', function () {
    dashboardOrder('settled', paid: 16500);
    $employee = User::factory()->create();

    $this->actingAs($this->owner)->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page->has('report.profit.real_margin')->has('report.shops.summary'));

    $this->actingAs($employee)->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page->where('report.profit', null)->has('report.shops.summary'));

    withProfile($employee, [Permission::ViewProfit]);

    $this->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page->has('report.profit.real_margin'));
});

test('the synthesis by shop shares what was paid in proportion to the purchases', function () {
    $order = dashboardOrder('advance_received', paid: 9000);
    $purchase = $order->items->sum(fn ($item) => $item->quantity * $item->unit_purchase_price);

    $this->actingAs($this->owner)->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(function (Assert $page) use ($purchase) {
            $summary = $page->toArray()['props']['report']['shops']['summary'];

            expect(array_sum(array_column($summary, 'advance')))->toBe(9000);
            expect(array_sum(array_column($summary, 'purchase')))->toBe($purchase);
            expect(round(array_sum(array_column($summary, 'percent'))))->toBe(100.0);

            foreach ($summary as $row) {
                expect($row['balance'])->toBe($row['purchase'] - $row['advance']);
            }
        });
});

test('what is owed to the shops counts goods received and not yet paid', function () {
    $shop = Shop::factory()->create();
    PurchaseOrder::factory()->create(['shop_id' => $shop->id, 'status' => 'received', 'expected_total' => 8000, 'actual_total' => 8000]);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('report.shops.debts', 8000));
});

test('late advances, blocked orders and the client cap raise alerts', function () {
    app(BusinessSettings::class)->set('client_cap', 2);
    $late = Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-13', 'advance_due' => 40000, 'total_sale' => 100000]);
    Payment::factory()->for($late)->create(['kind' => 'advance', 'amount' => 15000, 'payment_method_id' => PaymentMethod::factory()->create()->id]);
    Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-13', 'advance_due' => 5000, 'total_sale' => 20000]);
    Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-13', 'advance_due' => 0, 'total_sale' => 20000]);
    Order::factory()->create(['status' => 'ordered', 'delivery_week' => '2026-06-29', 'total_sale' => 1000]);
    Order::factory()->create(['status' => 'ordered', 'delivery_week' => '2026-07-13', 'total_sale' => 1000]);
    Order::factory()->create(['status' => 'delivered', 'delivery_week' => '2026-06-01', 'total_sale' => 1000]);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.alerts.late_advances.count', 2)
            ->where('report.alerts.late_advances.amount', 30000)
            ->where('report.alerts.blocked_orders', 1)
            ->where('report.alerts.client_cap.reached', true));
});

test('an advance paid in full or a future week is not late', function () {
    $paid = Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-13', 'advance_due' => 5000]);
    Payment::factory()->for($paid)->create(['kind' => 'advance', 'amount' => 5000, 'payment_method_id' => PaymentMethod::factory()->create()->id]);
    Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-27', 'advance_due' => 5000]);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('report.alerts.late_advances.count', 0));
});

test('only the owner is warned about documents', function () {
    AttachmentType::factory()->required()->create();
    $expired = AttachmentType::factory()->create();
    Attachment::factory()->create(['attachment_type_id' => $expired->id, 'expires_on' => '2026-07-01']);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.alerts.attachments.missing', 1)
            ->where('report.alerts.attachments.expired', 1)
            ->where('report.alerts.attachments.expiring', 0));

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('report.alerts.attachments', null));
});

test('the client count ignores clients that ended', function () {
    Client::factory()->count(2)->create();
    Client::factory()->create(['status' => 'ended']);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('report.indicators.active_clients', 2));
});

test('the delay before an order counts as blocked comes from the settings', function () {
    Order::factory()->create(['status' => 'ordered', 'delivery_week' => '2026-07-06', 'total_sale' => 1000]);

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.alerts.blocked_after_days', 7)
            ->where('report.alerts.blocked_orders', 1));

    app(BusinessSettings::class)->set('blocked_order_days', 14);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.alerts.blocked_after_days', 14)
            ->where('report.alerts.blocked_orders', 0));
});

test('the shop filter narrows the synthesis and what is owed to that shop only', function () {
    $order = dashboardOrder('advance_received', paid: 9000);
    $shop = Shop::factory()->create();
    $order->items()->toBase()->update(['shop_id' => $shop->id]);
    $order->unsetRelation('items');
    $purchase = $order->items->where('shop_id', $shop->id)->sum(fn ($item) => $item->quantity * $item->unit_purchase_price);
    PurchaseOrder::factory()->create(['shop_id' => $shop->id, 'status' => 'received', 'expected_total' => 8000, 'actual_total' => 8000]);
    PurchaseOrder::factory()->create(['shop_id' => Shop::factory()->create()->id, 'status' => 'received', 'expected_total' => 5000, 'actual_total' => 5000]);

    $this->actingAs($this->owner)
        ->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31', 'shop' => $shop->id]))
        ->assertInertia(function (Assert $page) use ($shop, $purchase) {
            $props = $page->toArray()['props'];

            expect($props['filters']['shop'])->toBe($shop->id);
            expect($props['report']['shops']['debts'])->toBe(8000);
            expect($props['report']['shops']['summary'])->toHaveCount(1);
            expect($props['report']['shops']['summary'][0]['shop'])->toBe($shop->name);
            expect($props['report']['shops']['summary'][0]['purchase'])->toBe($purchase);
            expect(array_column($props['shopChoices'], 'id'))->toContain($shop->id);
        });

    $this->get(route('dashboard.pdf', ['from' => '2026-07-01', 'to' => '2026-07-31', 'shop' => $shop->id]))->assertOk();
});

test('a field agent has no shop to choose from', function () {
    $agent = User::factory()->agent()->create();
    Shop::factory()->create();

    $this->actingAs($agent)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('shopChoices', [])->where('report.shops', null));
});

test('the weekly series gives, for each week of the period, what was sold, collected and delivered', function () {
    $first = dashboardOrder('delivered', '2026-07-06', 10000);
    Delivery::factory()->create(['order_id' => $first->id, 'kits_delivered' => 2]);
    $second = dashboardOrder('delivered', '2026-07-13', 4000);
    dashboardOrder('delivered', '2026-07-13');
    dashboardOrder('cancelled', '2026-07-20');
    dashboardOrder('delivered', '2026-05-04', 500);

    $this->actingAs($this->owner)
        ->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(function (Assert $page) use ($first, $second) {
            $weekly = $page->toArray()['props']['report']['weekly'];

            expect(array_column($weekly, 'week'))->toBe(['2026-07-06', '2026-07-13']);
            expect($weekly[0]['sale'])->toBe($first->total_sale);
            expect($weekly[0]['collected'])->toBe(10000);
            expect($weekly[0]['kits_ordered'])->toBe(3);
            expect($weekly[0]['kits_delivered'])->toBe(2);
            expect($weekly[1]['collected'])->toBe(4000);
            expect($weekly[1]['kits_ordered'])->toBe(6);
            expect($weekly[1]['sale'])->toBe($second->total_sale * 2);
        });
});

test('the weekly margins are given to whoever may see the profit and hidden from the others', function () {
    dashboardOrder('settled', '2026-07-13', 16500);
    $filters = ['from' => '2026-07-01', 'to' => '2026-07-31'];

    $this->actingAs($this->owner)->get(route('dashboard', $filters))
        ->assertInertia(fn (Assert $page) => $page
            ->whereType('report.weekly.0.theoretical_margin', 'integer')
            ->whereType('report.weekly.0.real_margin', 'integer'));

    $this->actingAs(User::factory()->create())->get(route('dashboard', $filters))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.weekly.0.theoretical_margin', null)
            ->where('report.weekly.0.real_margin', null)
            ->has('report.weekly.0.sale'));
});

test('a field agent gets the weekly series of their own sub-prefectures only', function () {
    $mine = Zone::factory()->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($mine);
    dashboardOrder('delivered', '2026-07-13', zone: $mine);
    dashboardOrder('delivered', '2026-07-06');

    $this->actingAs($agent)->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.weekly', 1)
            ->where('report.weekly.0.week', '2026-07-13')
            ->where('report.weekly.0.real_margin', null));
});

test('with no client cap there is no client alert, however many clients there are', function () {
    Client::factory()->count(70)->create();

    $this->actingAs($this->owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('report.alerts.client_cap', null));
});

test('the synthesis by shop gives the theoretical and real profit only to who may see the profit', function () {
    dashboardOrder('settled', paid: 16500);
    $employee = User::factory()->create();

    $this->actingAs($this->owner)->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(function (Assert $page) {
            $shops = $page->toArray()['props']['report']['shops'];

            expect($shops['with_profit'])->toBeTrue();

            foreach ($shops['summary'] as $row) {
                expect($row['theoretical_margin'])->toBe($row['sale'] - $row['purchase']);
                expect($row)->toHaveKey('real_margin');
            }
        });

    $this->actingAs($employee)->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertInertia(function (Assert $page) {
            $shops = $page->toArray()['props']['report']['shops'];

            expect($shops['with_profit'])->toBeFalse();
            expect($shops['summary'][0])->not->toHaveKey('theoretical_margin')->not->toHaveKey('real_margin');
        });
});
