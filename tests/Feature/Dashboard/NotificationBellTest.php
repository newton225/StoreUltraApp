<?php

use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 7, 15)->startOfDay());
});

function lateAdvanceOrder(?Zone $zone = null): Order
{
    $order = Order::factory()->create(['status' => 'proforma_issued', 'delivery_week' => '2026-07-13', 'advance_due' => 40000, 'total_sale' => 100000]);

    if ($zone !== null) {
        $order->client->update(['zone_id' => $zone->id]);
    }

    return $order;
}

test('every screen of a signed-in user carries the alerts in force', function () {
    lateAdvanceOrder();

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.late_advances.count', 1)
            ->where('alerts.late_advances.amount', 40000)
            ->has('alerts.blocked_orders')
            ->has('alerts.blocked_after_days'));
});

test('a guest has no alerts', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts', null));
});

test('the alerts of the bell are the ones of the dashboard', function () {
    lateAdvanceOrder();
    $owner = User::factory()->owner()->create();

    $dashboard = $this->actingAs($owner)->get(route('dashboard', ['from' => '2026-07-01', 'to' => '2026-07-31']));
    $report = $dashboard->viewData('page')['props']['report']['alerts'];

    $this->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts', $report));
});

test('a field agent is only told about the orders of their sub-prefectures', function () {
    $mine = Zone::factory()->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($mine);
    lateAdvanceOrder($mine);
    lateAdvanceOrder();

    $this->actingAs($agent)
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.late_advances.count', 1)
            ->where('alerts.attachments', null)
            ->where('alerts.client_cap', null));
});

test('only the owner is told about the documents', function () {
    AttachmentType::factory()->required()->create();
    $expired = AttachmentType::factory()->create();
    Attachment::factory()->create(['attachment_type_id' => $expired->id, 'expires_on' => '2026-07-01']);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alerts.attachments.missing', 1)
            ->where('alerts.attachments.expired', 1));

    $this->actingAs(User::factory()->create())
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->where('alerts.attachments', null));
});
