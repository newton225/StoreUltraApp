<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

test('the owner creates a user with a role and a verified email, without choosing a password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('users.store'), validUserPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'kouadio@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Agent);
    expect($user->is_active)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    // The password typed by the owner (a leftover field of an old client) is never used: nobody knows the real one yet.
    expect(Hash::check('Str0ng-Passw0rd-123', $user->password))->toBeFalse();
    expect($user->password_set_at)->toBeNull();
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

test('the owner cannot set the password of an existing account either', function () {
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

    // A password sent by an old client is ignored: only the person chooses it (invitation, SEC-165).
    expect($user->refresh()->password)->toBe($originalHash);
    expect(Hash::check('An0ther-Passw0rd-456', $user->password))->toBeFalse();
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
