<?php

use App\Models\Shop;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to the login page', function () {
    $this->get(route('shops.index'))->assertRedirect(route('login'));
});

test('field agents cannot manage shops', function () {
    $this->actingAs(User::factory()->agent()->create());
    $shop = Shop::factory()->create();

    $this->get(route('shops.index'))->assertForbidden();
    $this->post(route('shops.store'), ['name' => 'Boutique 3'])->assertForbidden();
    $this->patch(route('shops.update', $shop), ['name' => 'Changed'])->assertForbidden();

    expect($shop->refresh()->name)->not->toBe('Changed');
});

test('the owner and employees see the shops', function (string $state) {
    Shop::factory()->count(2)->create();

    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('shops.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('shops/index')->has('shops', 2));
})->with(['owner']);

test('an employee creates a shop', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('shops.store'), [
            'name' => 'Boutique 1',
            'contact' => 'Mr Traore',
            'phone' => '0102030405',
            'address' => 'Marche central',
            'payment_terms' => 'Comptant',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('shops.index'));

    $shop = Shop::where('name', 'Boutique 1')->firstOrFail();

    expect($shop->contact)->toBe('Mr Traore');
    expect($shop->active)->toBeTrue();
});

test('a shop is rejected for invalid data', function (array $payload, string $field) {
    Shop::factory()->create(['name' => 'Boutique 1']);

    $this->actingAs(User::factory()->create())
        ->post(route('shops.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(Shop::count())->toBe(1);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'duplicate name' => [['name' => 'Boutique 1'], 'name'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
]);

test('a shop is updated and deactivated', function () {
    $shop = Shop::factory()->create(['name' => 'Boutique 1']);

    $this->actingAs(User::factory()->create())
        ->patch(route('shops.update', $shop), ['name' => 'Boutique 1 bis', 'active' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('shops.index'));

    $shop->refresh();

    expect($shop->name)->toBe('Boutique 1 bis');
    expect($shop->active)->toBeFalse();
});
