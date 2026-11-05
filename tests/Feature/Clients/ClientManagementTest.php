<?php

use App\Models\Client;
use App\Models\User;
use App\Models\Zone;
use App\Support\BusinessSettings;
use Inertia\Testing\AssertableInertia as Assert;

test('an employee registers a client who is active and gets a reference', function () {
    $zone = Zone::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->post(route('clients.store'), clientPayload($zone))
        ->assertSessionHasNoErrors();

    $client = Client::firstOrFail();

    $response->assertRedirect(route('clients.show', $client));
    expect($client->status->value)->toBe('active');
    expect($client->reference)->toBe(sprintf('CLI-%04d', $client->id));
    expect($client->zone_id)->toBe($zone->id);
    expect($client->created_by)->not->toBeNull();
});

test('the office can register a client as pending', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('clients.store'), clientPayload(Zone::factory()->create(), ['status' => 'pending']));

    expect(Client::firstOrFail()->status->value)->toBe('pending');
});

test('a client is rejected for invalid data', function (Closure $mutate, string $field) {
    $payload = $mutate(clientPayload(Zone::factory()->create()), Zone::factory()->create(['active' => false]));

    $this->actingAs(User::factory()->create())
        ->post(route('clients.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(Client::count())->toBe(0);
})->with([
    'missing name' => [fn (array $p) => [...$p, 'name' => ''], 'name'],
    'name too long' => [fn (array $p) => [...$p, 'name' => str_repeat('a', 256)], 'name'],
    'missing commercial number' => [fn (array $p) => [...$p, 'commercial_number' => ''], 'commercial_number'],
    'commercial number too long' => [fn (array $p) => [...$p, 'commercial_number' => str_repeat('1', 51)], 'commercial_number'],
    'missing phone' => [fn (array $p) => [...$p, 'phone' => ''], 'phone'],
    'missing zone' => [fn (array $p) => [...$p, 'zone_id' => null], 'zone_id'],
    'unknown zone' => [fn (array $p) => [...$p, 'zone_id' => 9999], 'zone_id'],
    'inactive zone' => [fn (array $p, Zone $inactive) => [...$p, 'zone_id' => $inactive->id], 'zone_id'],
    'unknown status' => [fn (array $p) => [...$p, 'status' => 'vip'], 'status'],
]);

test('two clients cannot share the same commercial number', function () {
    Client::factory()->create(['commercial_number' => 'RC-0001']);

    $this->actingAs(User::factory()->create())
        ->post(route('clients.store'), clientPayload(Zone::factory()->create(), ['commercial_number' => 'RC-0001']))
        ->assertSessionHasErrors('commercial_number');

    expect(Client::count())->toBe(1);
});

test('a client keeps its own commercial number when it is updated', function () {
    $client = Client::factory()->create(['commercial_number' => 'RC-0002']);

    $this->actingAs(User::factory()->create())
        ->patch(route('clients.update', $client), clientPayload($client->zone, ['commercial_number' => 'RC-0002', 'name' => 'Renamed']))
        ->assertSessionHasNoErrors();

    expect($client->refresh()->name)->toBe('Renamed');
});

test('the office updates a client including the status and the zone', function () {
    $client = Client::factory()->create(['status' => 'pending']);
    $newZone = Zone::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('clients.update', $client), clientPayload($newZone, [
            'name' => 'Nouveau nom',
            'status' => 'suspended',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $client));

    $client->refresh();

    expect($client->name)->toBe('Nouveau nom');
    expect($client->status->value)->toBe('suspended');
    expect($client->zone_id)->toBe($newZone->id);
    expect($client->reference)->toBe(sprintf('CLI-%04d', $client->id));
});

test('a client of a zone that was deactivated since can still be edited in that zone', function () {
    $zone = Zone::factory()->create(['active' => false]);
    $client = Client::factory()->for($zone)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('clients.update', $client), clientPayload($zone, ['name' => 'Same zone']))
        ->assertSessionHasNoErrors();

    expect($client->refresh()->name)->toBe('Same zone');
});

test('the fiche shows the client with its zone', function () {
    $client = Client::factory()->for(Zone::factory()->create(['name' => 'Bouna']))->create(['name' => 'Aya']);

    $this->actingAs(User::factory()->create())
        ->get(route('clients.show', $client))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/show')
            ->where('client.name', 'Aya')
            ->where('client.zone.name', 'Bouna'));
});

test('the list can be searched by name, phone or reference', function (string $term) {
    Client::factory()->create(['name' => 'Traore Moussa', 'phone' => '0707070707', 'commercial_number' => 'RC-7777']);
    Client::factory()->create(['name' => 'Other', 'phone' => '0505050505']);
    Client::query()->where('name', 'Traore Moussa')->update(['reference' => 'CLI-9999']);

    $this->actingAs(User::factory()->create())
        ->get(route('clients.index', ['search' => $term]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 1)
            ->where('clients.data.0.name', 'Traore Moussa'));
})->with(['traore', '0707', 'CLI-9999', 'RC-7777']);

test('the list can be filtered by zone and by status', function () {
    $zone = Zone::factory()->create();
    Client::factory()->for($zone)->create(['status' => 'active']);
    Client::factory()->for($zone)->create(['status' => 'suspended']);
    Client::factory()->create(['status' => 'active']);

    $this->actingAs(User::factory()->create());

    $this->get(route('clients.index', ['zone' => $zone->id]))
        ->assertInertia(fn (Assert $page) => $page->where('clients.total', 2));

    $this->get(route('clients.index', ['status' => 'suspended']))
        ->assertInertia(fn (Assert $page) => $page->where('clients.total', 1));

    $this->get(route('clients.index', ['zone' => $zone->id, 'status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page->where('clients.total', 1));
});

test('the list is paginated on the server', function () {
    Client::factory()->count(30)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients.total', 30)
            ->has('clients.data', 25));
});

test('the number of clients is shown against the cap and exceeding it blocks nothing', function () {
    app(BusinessSettings::class)->set('client_cap', 2);
    Client::factory()->count(2)->create();
    Client::factory()->create(['status' => 'ended']);

    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('capacity.count', 2)
            ->where('capacity.cap', 2)
            ->where('capacity.exceeded', false));

    $this->post(route('clients.store'), clientPayload(Zone::factory()->create()))
        ->assertSessionHasNoErrors();

    expect(Client::count())->toBe(4);

    $this->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('capacity.count', 3)
            ->where('capacity.exceeded', true));
});

test('there is no limit to the number of clients unless a cap is set', function () {
    Client::factory()->count(60)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->where('capacity', null));

    $this->post(route('clients.store'), clientPayload(Zone::factory()->create()))
        ->assertSessionHasNoErrors();

    expect(Client::count())->toBe(61);
});

test('a cap of zero means no limit', function () {
    app(BusinessSettings::class)->set('client_cap', 0);
    Client::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page->where('capacity', null));
});
