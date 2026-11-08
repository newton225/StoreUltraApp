<?php

use App\Enums\ClientStatus;
use App\Enums\Permission;
use App\Http\Controllers\ClientLookupController;
use App\Models\Client;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

/** The names a lookup returns, in order. */
function foundNames($response): array
{
    return collect($response->assertOk()->json('data'))->pluck('name')->all();
}

test('a search finds clients without caring for accents or case, active ones only, by name', function () {
    Client::factory()->create(['name' => 'Éléonore Kouamé']);
    Client::factory()->create(['name' => 'Eleonore Zadi']);
    Client::factory()->create(['name' => 'Eleonore Suspendue', 'status' => ClientStatus::Suspended]);
    Client::factory()->create(['name' => 'Autre Client']);

    $names = foundNames($this->actingAs($this->owner)->getJson(route('lookup.clients', ['q' => 'ELEONORE'])));

    // Ordered the way a person reads names: the accent does not decide, the letters after it do (Kouamé before Zadi).
    expect($names)->toBe(['Éléonore Kouamé', 'Eleonore Zadi']);
});

test('a search returns a bounded list, never the whole table', function () {
    Client::factory()->count(ClientLookupController::LIMIT + 5)->create();

    $this->actingAs($this->owner)->getJson(route('lookup.clients'))->assertOk()->assertJsonCount(ClientLookupController::LIMIT, 'data');
});

test('the order form holds only the first clients and the rest is searched', function () {
    Client::factory()->count(ClientLookupController::LIMIT + 5)->create();

    $this->actingAs($this->owner)->get(route('orders.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients', ClientLookupController::LIMIT));
});

test('only who may create orders can look clients up', function () {
    $reader = withProfile(User::factory()->create(), [Permission::ManageClients]);

    $this->actingAs($reader)->getJson(route('lookup.clients'))->assertForbidden();
});

test('a visitor who is not signed in cannot look clients up', function () {
    $this->getJson(route('lookup.clients'))->assertUnauthorized();
});

test('a profile limited to zones is only offered the clients of its zones', function () {
    $mine = Zone::factory()->create();
    $other = Zone::factory()->create();
    Client::factory()->create(['name' => 'Alpha de ma zone', 'zone_id' => $mine->id]);
    Client::factory()->create(['name' => 'Alpha ailleurs', 'zone_id' => $other->id]);

    $agent = withProfile(User::factory()->create(), [Permission::ManageClients, Permission::ManageOrders], restrictedToZones: true);
    $agent->zones()->attach($mine);

    expect(foundNames($this->actingAs($agent)->getJson(route('lookup.clients', ['q' => 'alpha']))))->toBe(['Alpha de ma zone']);
});
