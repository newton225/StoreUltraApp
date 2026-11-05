<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an owner with a verified email and an active account', function () {
    $this->artisan('app:create-owner', ['name' => 'Ama Kone', 'email' => 'Owner@Example.com'])
        ->expectsQuestion('Password', 'Str0ng-Passw0rd-123')
        ->assertSuccessful();

    $owner = User::where('email', 'owner@example.com')->firstOrFail();

    expect($owner->name)->toBe('Ama Kone');
    expect($owner->role)->toBe(UserRole::Owner);
    expect($owner->is_active)->toBeTrue();
    expect($owner->email_verified_at)->not->toBeNull();
    expect(Hash::check('Str0ng-Passw0rd-123', $owner->password))->toBeTrue();
});

test('it refuses an email address that is already used', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('app:create-owner', ['name' => 'Ama Kone', 'email' => 'owner@example.com'])
        ->expectsQuestion('Password', 'Str0ng-Passw0rd-123')
        ->assertFailed();

    expect(User::count())->toBe(1);
});

test('it refuses a password that is too short', function () {
    $this->artisan('app:create-owner', ['name' => 'Ama Kone', 'email' => 'owner@example.com'])
        ->expectsQuestion('Password', 'abc')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
