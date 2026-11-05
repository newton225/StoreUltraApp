<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function validUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Kouadio Yao',
        'email' => 'kouadio@example.com',
        'role' => 'agent',
        'password' => 'Str0ng-Passw0rd-123',
        'password_confirmation' => 'Str0ng-Passw0rd-123',
        ...$overrides,
    ];
}

test('guests are sent to the login page', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('only the owner can reach user management', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $target = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
    $this->post(route('users.store'), validUserPayload())->assertForbidden();
    $this->get(route('users.edit', $target))->assertForbidden();
    $this->patch(route('users.update', $target), ['name' => 'Changed'])->assertForbidden();

    expect($target->refresh()->name)->not->toBe('Changed');
})->with(['agent']);

test('an employee cannot manage users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('users.index'))->assertForbidden();
});

test('the owner sees every user', function () {
    $owner = User::factory()->owner()->create();
    User::factory()->count(2)->create();

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users', 3));
});

test('the owner creates a user with a role, a verified email and a hashed password', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('users.store'), validUserPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'kouadio@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Agent);
    expect($user->is_active)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect(Hash::check('Str0ng-Passw0rd-123', $user->password))->toBeTrue();
});

test('creating a user is rejected for invalid data', function (array $overrides, string $field) {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('users.store'), validUserPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(User::where('name', 'Kouadio Yao')->exists())->toBeFalse();
})->with([
    'duplicate email' => [['email' => 'taken@example.com'], 'email'],
    'unknown role' => [['role' => 'admin'], 'role'],
    'weak password' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
    'password not confirmed' => [['password_confirmation' => 'different'], 'password'],
]);

test('the owner updates the details, the role and the status of a user', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('users.update', $user), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'role' => 'agent',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->role)->toBe(UserRole::Agent);
    expect($user->is_active)->toBeFalse();
});

test('the password only changes when a new one is given', function () {
    $user = User::factory()->create();
    $originalHash = $user->password;

    $this->actingAs(User::factory()->owner()->create())
        ->patch(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'employee',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->password)->toBe($originalHash);

    $this->patch(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'employee',
        'is_active' => true,
        'password' => 'An0ther-Passw0rd-456',
        'password_confirmation' => 'An0ther-Passw0rd-456',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('An0ther-Passw0rd-456', $user->refresh()->password))->toBeTrue();
});

test('the owner cannot lock themselves out', function (array $changes, string $field) {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->patch(route('users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => 'owner',
            'is_active' => true,
            ...$changes,
        ])
        ->assertSessionHasErrors($field);

    $owner->refresh();

    expect($owner->role)->toBe(UserRole::Owner);
    expect($owner->is_active)->toBeTrue();
})->with([
    'deactivating their own account' => [['is_active' => false], 'is_active'],
    'changing their own role' => [['role' => 'employee'], 'role'],
]);
