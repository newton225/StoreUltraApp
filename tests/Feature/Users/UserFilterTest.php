<?php

use App\Enums\UserRole;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Isaac Ndri', 'email' => 'isaac@example.com']);
    $this->employee = Profile::forRole(UserRole::Employee);

    User::factory()->create(['name' => 'Éléonore Kouamé', 'email' => 'eleonore@example.com', 'is_active' => true]);
    User::factory()->create(['name' => 'Zébé Arnaud', 'email' => 'zebe@example.com', 'password_set_at' => null]);
    User::factory()->inactive()->create(['name' => 'Ancien Agent', 'email' => 'ancien@example.com']);
});

/** The names the users screen lists for a filter, in order. */
function listedUsers(array $query = []): array
{
    $names = [];

    test()->actingAs(User::where('email', 'isaac@example.com')->firstOrFail())
        ->get(route('users.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$names) {
            $names = collect($page->toArray()['props']['users'])->pluck('name')->all();
        });

    return $names;
}

test('without a filter every account is listed, in the order a person reads names', function () {
    expect(listedUsers())->toBe(['Ancien Agent', 'Éléonore Kouamé', 'Isaac Ndri', 'Zébé Arnaud']);
});

test('the search finds a name or an e-mail address, accents and case ignored', function () {
    expect(listedUsers(['search' => 'ELEONORE']))->toBe(['Éléonore Kouamé']);
    expect(listedUsers(['search' => 'zebe@']))->toBe(['Zébé Arnaud']);
    expect(listedUsers(['search' => 'zebe']))->toBe(['Zébé Arnaud']);
    expect(listedUsers(['search' => 'nobody']))->toBe([]);
});

test('the list is filtered by state: working, invitation pending, deactivated', function () {
    expect(listedUsers(['state' => 'active']))->toBe(['Éléonore Kouamé', 'Isaac Ndri']);
    expect(listedUsers(['state' => 'pending']))->toBe(['Zébé Arnaud']);
    expect(listedUsers(['state' => 'inactive']))->toBe(['Ancien Agent']);
});

test('the list is filtered by profile', function () {
    $owner = Profile::forRole(UserRole::Owner);

    expect(listedUsers(['profile' => $owner->id]))->toBe(['Isaac Ndri']);
    expect(collect(listedUsers(['profile' => $this->employee->id])))->not->toContain('Isaac Ndri');
});

test('the filters combine, and an unknown state is ignored', function () {
    expect(listedUsers(['search' => 'a', 'state' => 'inactive']))->toBe(['Ancien Agent']);
    expect(listedUsers(['state' => 'whatever']))->toHaveCount(4);
});

test('the screen gets the profiles and the states to choose from, and keeps the filters', function () {
    $this->actingAs($this->owner)->get(route('users.index', ['search' => 'zebe', 'state' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['search' => 'zebe', 'profile' => null, 'state' => 'pending'])
            ->where('states', ['active', 'pending', 'inactive'])
            ->has('profiles'));
});

test('the export follows the same filters', function () {
    $csv = $this->actingAs($this->owner)
        ->get(route('list-exports.show', ['view' => 'users', 'format' => 'csv', 'state' => 'inactive']))
        ->assertOk()
        ->getContent();

    expect($csv)->toContain('Ancien Agent')->not->toContain('Isaac Ndri')->not->toContain('Zébé');
});

test('only the owner can list users, filtered or not', function () {
    $this->actingAs(User::factory()->create())->get(route('users.index', ['state' => 'active']))->assertForbidden();
});
