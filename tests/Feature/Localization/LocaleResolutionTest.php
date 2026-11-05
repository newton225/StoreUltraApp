<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the default language is french when the browser sends no preference', function () {
    $this->withHeaders(['Accept-Language' => ''])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
});

test('a visitor gets the language of the browser when it is supported', function () {
    $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('an unsupported browser language falls back to the default language', function () {
    $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
});

test('a visitor can choose a language and it is remembered in the session', function () {
    $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();

    $this->withHeaders(['Accept-Language' => 'fr'])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('an authenticated user gets the language saved in their profile, not the browser one', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'fr'])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('choosing a language while logged in saves it in the profile', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();

    expect($user->refresh()->locale)->toBe('en');
});

test('a language outside the supported catalog is rejected', function () {
    $this->post(route('locale.update'), ['locale' => 'de'])->assertSessionHasErrors('locale');

    $this->post(route('locale.update'), ['locale' => '../../etc'])->assertSessionHasErrors('locale');
});

test('only the translation groups needed by the browser are shared', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('translations.common')
            ->missing('translations.validation'));
});
