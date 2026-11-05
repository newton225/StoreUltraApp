<?php

use App\Models\Client;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function clientPayload(Zone $zone, array $overrides = []): array
{
    return [
        'commercial_number' => fake()->unique()->numerify('RC-########'),
        'name' => 'Kouassi Aya',
        'phone' => '0102030405',
        'zone_id' => $zone->id,
        'locality' => 'Village A',
        'delivery_address' => 'Pres du marche',
        ...$overrides,
    ];
}

function agentFor(Zone ...$zones): User
{
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach(array_map(fn (Zone $zone) => $zone->id, $zones));

    return $agent;
}

test('guests are sent to the login page', function () {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

test('the owner and employees see every client', function (string $state) {
    Client::factory()->count(3)->create();

    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/index')
            ->where('clients.total', 3));
})->with(['owner']);

test('an employee sees every client', function () {
    Client::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->where('clients.total', 2));
});

test('a field agent only sees the clients of the zones they are assigned to', function () {
    $mine = Zone::factory()->create();
    $other = Zone::factory()->create();
    Client::factory()->for($mine)->create(['name' => 'Client proche']);
    Client::factory()->for($other)->create(['name' => 'Client lointain']);

    $this->actingAs(agentFor($mine))
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 1)
            ->where('clients.data.0.name', 'Client proche'));
});

test('a field agent with no zone sees no client', function () {
    Client::factory()->count(2)->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->where('clients.total', 0));
});

test('a field agent can open the clients of their zones but not the others', function () {
    $mine = Zone::factory()->create();
    $other = Zone::factory()->create();
    $own = Client::factory()->for($mine)->create();
    $foreign = Client::factory()->for($other)->create();

    $this->actingAs(agentFor($mine));

    $this->get(route('clients.show', $own))->assertOk();
    $this->get(route('clients.edit', $own))->assertOk();
    $this->get(route('clients.show', $foreign))->assertForbidden();
    $this->get(route('clients.edit', $foreign))->assertForbidden();
});

test('a field agent cannot update a client of another zone', function () {
    $mine = Zone::factory()->create();
    $foreign = Client::factory()->for(Zone::factory())->create(['name' => 'Original']);

    $this->actingAs(agentFor($mine))
        ->patch(route('clients.update', $foreign), clientPayload($mine, ['name' => 'Changed']))
        ->assertForbidden();

    expect($foreign->refresh()->name)->toBe('Original');
});

test('a field agent registers a client in their zone, pending until the office confirms', function () {
    $zone = Zone::factory()->create();

    $this->actingAs(agentFor($zone))
        ->post(route('clients.store'), clientPayload($zone, ['status' => 'active']))
        ->assertSessionHasNoErrors();

    expect(Client::firstOrFail()->status->value)->toBe('pending');
});

test('a field agent cannot register a client in another zone', function () {
    $mine = Zone::factory()->create();
    $other = Zone::factory()->create();

    $this->actingAs(agentFor($mine))
        ->post(route('clients.store'), clientPayload($other))
        ->assertSessionHasErrors('zone_id');

    expect(Client::count())->toBe(0);
});

test('a field agent cannot change the status of a client', function () {
    $zone = Zone::factory()->create();
    $client = Client::factory()->for($zone)->create(['status' => 'pending']);

    $this->actingAs(agentFor($zone))
        ->patch(route('clients.update', $client), clientPayload($zone, ['status' => 'active', 'name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->name)->toBe('Renamed');
    expect($client->status->value)->toBe('pending');
});

test('the form offers a field agent only their own active zones', function () {
    $mine = Zone::factory()->create();
    Zone::factory()->create();
    $agent = agentFor($mine, Zone::factory()->create(['active' => false]));

    $this->actingAs($agent)
        ->get(route('clients.create'))
        ->assertInertia(fn (Assert $page) => $page->has('zones', 1)->where('zones.0.id', $mine->id));
});
