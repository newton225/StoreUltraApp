<?php

use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function agentPayload(array $zoneIds, array $overrides = []): array
{
    return [
        'name' => 'Agent Bouna',
        'email' => 'agent@example.com',
        'role' => 'agent',
        'password' => 'Str0ng-Passw0rd-123',
        'password_confirmation' => 'Str0ng-Passw0rd-123',
        'zone_ids' => $zoneIds,
        ...$overrides,
    ];
}

test('the owner assigns zones when creating a field agent', function () {
    $zones = Zone::factory()->count(2)->create();

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('users.store'), agentPayload($zones->pluck('id')->all()))
        ->assertSessionHasNoErrors();

    $agent = User::where('email', 'agent@example.com')->firstOrFail();

    expect($agent->zones->pluck('id')->sort()->values()->all())->toBe($zones->pluck('id')->sort()->values()->all());
});

test('updating a field agent replaces their zones', function () {
    [$a, $b, $c] = Zone::factory()->count(3)->create()->all();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach([$a->id, $b->id]);

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('users.update', $agent), [
            'name' => $agent->name,
            'email' => $agent->email,
            'role' => 'agent',
            'is_active' => true,
            'zone_ids' => [$b->id, $c->id],
        ])
        ->assertSessionHasNoErrors();

    expect($agent->zones()->pluck('zones.id')->sort()->values()->all())->toBe([$b->id, $c->id]);
});

test('a user who stops being a field agent loses their zones', function () {
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach(Zone::factory()->create());

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('users.update', $agent), [
            'name' => $agent->name,
            'email' => $agent->email,
            'role' => 'employee',
            'is_active' => true,
            'zone_ids' => [Zone::factory()->create()->id],
        ])
        ->assertSessionHasNoErrors();

    expect($agent->zones()->count())->toBe(0);
});

test('zones given for a role other than field agent are ignored', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('users.store'), agentPayload([Zone::factory()->create()->id], ['role' => 'employee']))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'agent@example.com')->firstOrFail()->zones()->count())->toBe(0);
});

test('an unknown zone is rejected', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('users.store'), agentPayload([9999]))
        ->assertSessionHasErrors('zone_ids.0');

    expect(User::where('email', 'agent@example.com')->exists())->toBeFalse();
});

test('the edit page lists the zones and the ones of the user', function () {
    $zones = Zone::factory()->count(2)->create();
    $agent = User::factory()->agent()->create();
    $agent->zones()->attach($zones->first());

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('users.edit', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->has('zones', 2)
            ->where('editedUser.zone_ids', [$zones->first()->id]));
});
