<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('new users are employees by default', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Employee);
    expect($user->is_active)->toBeTrue();
});

test('only the owner can manage users and settings', function (UserRole $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect(Gate::forUser($user)->allows('manage-users'))->toBe($allowed);
    expect(Gate::forUser($user)->allows('manage-settings'))->toBe($allowed);
})->with([
    'owner' => [UserRole::Owner, true],
    'employee' => [UserRole::Employee, false],
    'agent' => [UserRole::Agent, false],
]);

test('only the agent role is restricted to its own zones', function (UserRole $role, bool $restricted) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->isRestrictedToZones())->toBe($restricted);
})->with([
    'owner' => [UserRole::Owner, false],
    'employee' => [UserRole::Employee, false],
    'agent' => [UserRole::Agent, true],
]);
