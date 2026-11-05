<?php

use App\Enums\ClientStatus;
use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Kit;
use App\Models\Order;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->kit = pricedKit()->kit;
    $this->week = today()->startOfWeek()->addWeek();
});

function zoneNamed(string $name): Zone
{
    return Zone::query()->firstOrCreate(['name' => $name], ['active' => true]);
}

function tpeIn(string $zone, array $attributes = []): Client
{
    return Client::factory()->create(['zone_id' => zoneNamed($zone)->id, 'status' => ClientStatus::Active, ...$attributes]);
}

/**
 * @param  list<Client>  $clients
 * @return array<string, mixed>
 */
function batchPayload(array $clients, array $overrides = []): array
{
    return [
        'zone_id' => zoneNamed('Fronan')->id,
        'kit_id' => test()->kit->id,
        'kits_count' => 3,
        'delivery_week' => test()->week->toDateString(),
        'advance_due' => 200000,
        'client_ids' => array_map(fn (Client $client): int => $client->id, $clients),
        ...$overrides,
    ];
}

test('one order is created for each ticked TPE of the zone, with the prices frozen and a trace each', function () {
    $clients = [tpeIn('Fronan'), tpeIn('Fronan')];

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload($clients))->assertSessionHasNoErrors()->assertRedirect(route('orders.index', ['week' => $this->week->toDateString()]));

    $orders = Order::query()->orderBy('id')->get();

    expect($orders)->toHaveCount(2)
        ->and($orders->pluck('client_id')->sort()->values()->all())->toBe(collect($clients)->pluck('id')->sort()->values()->all())
        ->and($orders->every(fn (Order $order): bool => $order->status === OrderStatus::Draft && $order->kits_count === 3 && $order->advance_due === 200000 && $order->created_by === $this->owner->id && $order->delivery_week->toDateString() === $this->week->toDateString()))->toBeTrue()
        ->and($orders->first()->total_sale)->toBeGreaterThan(0)
        ->and(AuditLog::where('action', 'order.created')->count())->toBe(2)
        ->and(AuditLog::where('action', 'order.batch_step')->get()->every(fn (AuditLog $line): bool => $line->after['step'] === 'created' && $line->after['zone'] === 'Fronan' && $line->after['group_of'] === 2))->toBeTrue()
        ->and(AuditLog::where('action', 'order.batch_step')->count())->toBe(2);
});

test('the proformas are issued at the same time when asked', function () {
    $clients = [tpeIn('Fronan'), tpeIn('Fronan')];

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload($clients, ['issue_proformas' => '1']))->assertSessionHasNoErrors();

    expect(Order::query()->where('status', OrderStatus::ProformaIssued->value)->count())->toBe(2)
        ->and(Document::query()->where('type', DocumentType::Proforma->value)->count())->toBe(2);
});

test('without the option the orders stay drafts and no proforma is issued', function () {
    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([tpeIn('Fronan')]))->assertSessionHasNoErrors();

    expect(Order::query()->where('status', OrderStatus::Draft->value)->count())->toBe(1)->and(Document::count())->toBe(0);
});

test('a TPE that already has an order that week is skipped, and running it again creates nothing', function () {
    $has = tpeIn('Fronan');
    $new = tpeIn('Fronan');
    Order::factory()->for($has)->create(['delivery_week' => $this->week->toDateString(), 'status' => 'draft']);

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([$has, $new]))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([$has, $new]));

    expect(Order::count())->toBe(2)->and(Order::where('client_id', $new->id)->count())->toBe(1);
});

test('a cancelled order does not block a new one for the same week', function () {
    $client = tpeIn('Fronan');
    Order::factory()->for($client)->create(['delivery_week' => $this->week->toDateString(), 'status' => 'cancelled']);

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([$client]));

    expect(Order::where('client_id', $client->id)->count())->toBe(2);
});

test('the server rechecks every TPE: inactive, other zone and forged ids are left out', function () {
    $good = tpeIn('Fronan');
    $inactive = tpeIn('Fronan', ['status' => ClientStatus::Suspended]);
    $pending = tpeIn('Fronan', ['status' => ClientStatus::Pending]);
    $elsewhere = tpeIn('Katiola');

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([$good, $inactive, $pending, $elsewhere]))->assertSessionHasNoErrors();

    expect(Order::pluck('client_id')->all())->toBe([$good->id]);
});

test('a user restricted to their zones only reaches the TPE of these zones', function () {
    $zone = zoneNamed('Fronan');
    $mine = tpeIn('Fronan');
    $other = tpeIn('Katiola');
    $restricted = User::factory()->create(['profile_id' => Profile::factory()->granting([Permission::ManageOrders])->restrictedToZones()->create()->id]);
    $restricted->zones()->attach($zone);

    $this->actingAs($restricted)->post(route('orders.batch.store'), batchPayload([$mine, $other]))->assertSessionHasNoErrors();

    expect(Order::pluck('client_id')->all())->toBe([$mine->id]);
});

test('a user restricted to their zones cannot create orders for a zone that is not theirs, and an unknown zone is refused', function () {
    $mine = zoneNamed('Fronan');
    $theirs = tpeIn('Katiola');
    $restricted = User::factory()->create(['profile_id' => Profile::factory()->granting([Permission::ManageOrders])->restrictedToZones()->create()->id]);
    $restricted->zones()->attach($mine);

    $this->actingAs($restricted)->post(route('orders.batch.store'), batchPayload([$theirs], ['zone_id' => $theirs->zone_id]));
    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([$theirs], ['zone_id' => 999999]))->assertSessionHasErrors(['zone_id' => __('orders.batch.errors.zone_required')]);

    expect(Order::count())->toBe(0);
});

test('a kit with no price for that week is refused and nothing is created', function () {
    $kit = Kit::factory()->create();

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([tpeIn('Fronan')], ['kit_id' => $kit->id]))->assertSessionHasErrors('kit_id');

    expect(Order::count())->toBe(0);
});

test('a mistake in the form is explained under its field', function () {
    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([], ['client_ids' => []]))
        ->assertSessionHasErrors(['client_ids' => __('orders.batch.errors.clients_required')]);

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([tpeIn('Fronan')], ['kit_id' => '']))
        ->assertSessionHasErrors(['kit_id' => __('orders.plans.errors.kit_required')]);

    expect(Order::count())->toBe(0);
});

test('the creation page lists the zones of the active TPE and the TPE of the chosen one with their week', function () {
    $a = tpeIn('Fronan');
    $b = tpeIn('Fronan');
    tpeIn('Katiola');
    tpeIn('Timbe', ['status' => ClientStatus::Suspended]);
    Order::factory()->for($a)->create(['delivery_week' => $this->week->toDateString(), 'status' => 'draft']);

    $this->actingAs($this->owner)->get(route('orders.create', ['mode' => 'zone', 'zone' => zoneNamed('Fronan')->id, 'batch_week' => $this->week->toDateString()]))
        ->assertInertia(fn ($page) => $page
            ->where('zones', fn ($list) => collect($list)->pluck('name')->sort()->values()->all() === ['Fronan', 'Katiola'])
            ->has('candidates', 2)
            ->where('candidates', fn ($list) => collect($list)->firstWhere('id', $a->id)['already'] === true && collect($list)->firstWhere('id', $b->id)['already'] === false));
});

test('a user who cannot manage orders cannot create the batch', function () {
    $user = User::factory()->agent()->create();

    $this->actingAs($user)->post(route('orders.batch.store'), batchPayload([tpeIn('Fronan')]))->assertForbidden();
});

test('the final message only names what is not zero', function () {
    $has = tpeIn('Fronan');
    $new = tpeIn('Fronan');
    Order::factory()->for($has)->create(['delivery_week' => $this->week->toDateString(), 'status' => 'draft']);

    $this->actingAs($this->owner)->post(route('orders.batch.store'), batchPayload([$has, $new], ['issue_proformas' => '1']))->assertRedirect();

    $toast = session(SessionKey::FLASH_DATA)['toast'];

    expect($toast['message'])->toBe('1 commande créée · 1 proforma émise · 1 TPE ignoré (a déjà une commande cette semaine)')->and($toast['type'])->toBe('success');
});
