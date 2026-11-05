<?php

use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to the login page', function () {
    $this->get(route('zones.index'))->assertRedirect(route('login'));
});

test('only the owner can manage zones', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create());
    $zone = Zone::factory()->create();

    $this->get(route('zones.index'))->assertForbidden();
    $this->post(route('zones.store'), ['name' => 'Bouna'])->assertForbidden();
    $this->patch(route('zones.update', $zone), ['name' => 'Changed'])->assertForbidden();

    expect(Zone::count())->toBe(1);
    expect($zone->refresh()->name)->not->toBe('Changed');
})->with(['agent']);

test('an employee cannot manage zones', function () {
    $this->actingAs(User::factory()->create())->get(route('zones.index'))->assertForbidden();
});

test('the owner sees the zones sorted by name', function () {
    Zone::factory()->create(['name' => 'Tanda']);
    Zone::factory()->create(['name' => 'Bouna']);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('zones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('zones/index')
            ->where('zones.0.name', 'Bouna')
            ->where('zones.1.name', 'Tanda'));
});

test('the owner creates an active zone', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('zones.store'), ['name' => 'Bouna', 'code' => 'BNA'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('zones.index'));

    $zone = Zone::where('name', 'Bouna')->firstOrFail();

    expect($zone->code)->toBe('BNA');
    expect($zone->active)->toBeTrue();
});

test('a zone is rejected for invalid data', function (array $payload, string $field) {
    Zone::factory()->create(['name' => 'Bouna', 'code' => 'BNA']);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('zones.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(Zone::count())->toBe(1);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'duplicate name' => [['name' => 'Bouna'], 'name'],
    'duplicate code' => [['name' => 'Tanda', 'code' => 'BNA'], 'code'],
]);

test('the owner renames and deactivates a zone', function () {
    $zone = Zone::factory()->create(['name' => 'Bouna', 'code' => 'BNA']);

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('zones.update', $zone), ['name' => 'Bouna Nord', 'code' => 'BNA', 'active' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('zones.index'));

    $zone->refresh();

    expect($zone->name)->toBe('Bouna Nord');
    expect($zone->active)->toBeFalse();
});

test('a sub-prefecture can be added from another page and is given back to be chosen', function () {
    $response = $this->actingAs(User::factory()->owner()->create())
        ->postJson(route('zones.store'), ['name' => 'Bouna', 'code' => 'BOU']);

    $response->assertCreated()->assertJson(['name' => 'Bouna']);
    expect($response->json('id'))->toBe(Zone::where('name', 'Bouna')->sole()->id);
});

test('a sub-prefecture added from another page is checked like any other', function () {
    Zone::factory()->create(['name' => 'Bouna']);

    $this->actingAs(User::factory()->owner()->create())
        ->postJson(route('zones.store'), ['name' => 'Bouna'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect(Zone::where('name', 'Bouna')->count())->toBe(1);
});

test('only the owner can add a sub-prefecture from another page', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('zones.store'), ['name' => 'Bouna'])
        ->assertForbidden();

    expect(Zone::count())->toBe(0);
});

test('the client form is told whether the user may add a sub-prefecture', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('clients.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.canManageSettings', true));

    $this->actingAs(User::factory()->create())
        ->get(route('clients.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.canManageSettings', false));
});
