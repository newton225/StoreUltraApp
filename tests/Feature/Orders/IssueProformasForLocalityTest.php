<?php

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('local'));

function draftOrderIn(string $locality, OrderStatus $status = OrderStatus::Draft): Order
{
    return Order::factory()->for(Client::factory()->create(['locality' => $locality]))->create(['status' => $status]);
}

test('the proformas of a whole locality are issued in one go', function () {
    $here = [draftOrderIn('Darakokaha'), draftOrderIn('Darakokaha')];
    $elsewhere = draftOrderIn('Fronan');
    $already = draftOrderIn('Darakokaha', OrderStatus::ProformaIssued);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('orders.proformas.store'), ['locality' => 'Darakokaha'])
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

    $this->actingAs($owner)->post(route('orders.proformas.store'), ['locality' => 'Darakokaha']);
    $this->actingAs($owner)->post(route('orders.proformas.store'), ['locality' => 'Darakokaha']);

    expect(Document::query()->where('subject_id', $order->id)->where('type', DocumentType::Proforma->value)->count())->toBe(1);
});

test('a user who cannot manage orders cannot issue the batch', function () {
    draftOrderIn('Darakokaha');

    $this->actingAs(User::factory()->agent()->create())
        ->post(route('orders.proformas.store'), ['locality' => 'Darakokaha'])
        ->assertForbidden();
});

test('the orders list offers the localities that have waiting orders', function () {
    draftOrderIn('Darakokaha');
    draftOrderIn('Darakokaha');
    draftOrderIn('Fronan', OrderStatus::ProformaIssued);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('orders.index'))
        ->assertInertia(fn (Assert $page) => $page->has('proformaLocalities', 1)
            ->where('proformaLocalities.0.name', 'Darakokaha')
            ->where('proformaLocalities.0.count', 2));
});
