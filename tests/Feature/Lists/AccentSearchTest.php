<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;

test('a search ignores accents and case', function () {
    $found = Client::factory()->create(['name' => 'Éléonore Kouamé', 'phone' => '0102030405']);
    Client::factory()->create(['name' => 'Autre Client']);

    foreach (['eleonore', 'ELEONORE', 'éléo', 'kouame', 'Kouamé', 'ele'] as $term) {
        expect(Client::query()->search($term)->pluck('id')->all())->toBe([$found->id], "term: {$term}");
    }

    expect(Client::query()->search('zzz')->count())->toBe(0);
});

test('the other way round: an accent typed by the user finds a name written without accent', function () {
    $client = Client::factory()->create(['name' => 'Koffi Ete']);

    expect(Client::query()->search('Été')->pluck('id')->all())->toBe([$client->id]);
});

test('percent, underscore and backslash typed by the user are plain characters', function () {
    Client::factory()->create(['name' => 'Alpha Beta']);
    $literal = Client::factory()->create(['name' => '100% Bio_Shop']);

    expect(Client::query()->search('%')->pluck('id')->all())->toBe([$literal->id])
        ->and(Client::query()->search('_')->pluck('id')->all())->toBe([$literal->id])
        ->and(Client::query()->search('\\')->count())->toBe(0);
});

test('a product is found by the accented name of its shop', function () {
    $shop = Shop::factory()->create(['name' => 'Quincaillerie Étoile']);
    $product = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Ciment']);
    Product::factory()->create(['name' => 'Fer à béton']);

    expect(Product::query()->search('etoile')->pluck('id')->all())->toBe([$product->id])
        ->and(Product::query()->search('beton')->count())->toBe(1);
});

test('the client list page searches without accents', function () {
    $owner = User::factory()->owner()->create();
    Client::factory()->create(['name' => 'Zoé Traoré']);
    Client::factory()->create(['name' => 'Autre Personne']);

    $this->actingAs($owner)->get(route('clients.index', ['search' => 'zoe traore']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('clients.data', 1)->where('clients.data.0.name', 'Zoé Traoré'));
});
