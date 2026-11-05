<?php

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('local'));

function draftZone(string $name): Zone
{
    return Zone::query()->firstOrCreate(['name' => $name], ['active' => true]);
}

function draftOrderIn(string $zone, OrderStatus $status = OrderStatus::Draft): Order
{
    return Order::factory()->for(Client::factory()->create(['zone_id' => draftZone($zone)->id]))->create(['status' => $status]);
}

test('the proformas of a whole zone are issued in one go', function () {
    $here = [draftOrderIn('Darakokaha'), draftOrderIn('Darakokaha')];
    $elsewhere = draftOrderIn('Fronan');
    $already = draftOrderIn('Darakokaha', OrderStatus::ProformaIssued);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('orders.proformas.store'), ['zone_id' => draftZone('Darakokaha')->id])
        ->assertRedirect();

    foreach ($here as $order) {
        expect($order->refresh()->status)->toBe(OrderStatus::ProformaIssued);
        expect(Document::query()->where('type', DocumentType::Proforma->value)->where('subject_id', $order->id)->count())->toBe(1);
    }

    expect($elsewhere->refresh()->status)->toBe(OrderStatus::Draft)
        ->and($already->refresh()->status)->toBe(OrderStatus::ProformaIssued)
        ->and(Document::query()->where('type', DocumentType::Proforma->value)->count())->toBe(2);
});

test('running the batch twice does not issue a second proforma', function () {
    $order = draftOrderIn('Darakokaha');
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('orders.proformas.store'), ['zone_id' => draftZone('Darakokaha')->id]);
    $this->actingAs($owner)->post(route('orders.proformas.store'), ['zone_id' => draftZone('Darakokaha')->id]);

    expect(Document::query()->where('subject_id', $order->id)->where('type', DocumentType::Proforma->value)->count())->toBe(1);
});

test('a user who cannot manage orders cannot issue the batch', function () {
    draftOrderIn('Darakokaha');

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('orders.proformas.store'), ['zone_id' => draftZone('Darakokaha')->id])
        ->assertForbidden();
});

test('the orders list offers the zones that have waiting orders', function () {
    draftOrderIn('Darakokaha');
    draftOrderIn('Darakokaha');
    draftOrderIn('Fronan', OrderStatus::ProformaIssued);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->has('proformaZones', 1)
            ->where('proformaZones.0.name', 'Darakokaha')
            ->where('proformaZones.0.count', 2));
});
