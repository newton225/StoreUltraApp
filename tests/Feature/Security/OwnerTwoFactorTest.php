<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['security.require_owner_two_factor' => true]);
});

test('an owner without a second factor is sent to the security page', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('the rest of the application stays closed to them, for every kind of request', function () {
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('users.index'))->assertRedirect(route('security.edit'));
    $this->get(route('exports.show', 'clients'))->assertRedirect(route('security.edit'));
    $this->post(route('zones.store'), ['name' => 'Nouvelle'])->assertRedirect(route('security.edit'));
});

test('the page where the second factor is set up stays open and says why', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/security')->where('twoFactorRequired', true));
});

test('the owner can get to the security page by confirming their password, with nothing already confirmed', function () {
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));

    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    $this->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/security')->where('twoFactorRequired', true));
});

test('they can still choose a language and log out', function () {
    $this->actingAs(User::factory()->owner()->create());

    $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirectBack();
    $this->post(route('logout'))->assertRedirect('/');
});

test('an owner with a second factor works as usual', function () {
    $this->actingAs(User::factory()->owner()->withTwoFactor()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

test('employees and field agents are not asked for it', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
    $this->actingAs(User::factory()->agent()->create())->get(route('dashboard'))->assertOk();
});

test('the security page does not warn an employee or an owner who is covered', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorRequired', false));

    $this->actingAs(User::factory()->owner()->withTwoFactor()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorRequired', false));
});

test('the requirement can be switched off for a local machine', function () {
    config(['security.require_owner_two_factor' => false]);

    $this->actingAs(User::factory()->owner()->create())->get(route('dashboard'))->assertOk();
});
