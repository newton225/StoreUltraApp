<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Zone;

/**
 * A column header sorts the whole list on the server (playbook UI-46): only the columns a list names are
 * accepted, the choice comes back with the filters, and the export follows the same order.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->owner()->create());
});

test('the clients are sorted by the column asked, both ways, over the whole list', function () {
    Client::factory()->create(['name' => 'Bravo', 'zone_id' => Zone::factory()->create(['name' => 'Zeta'])->id]);
    Client::factory()->create(['name' => 'Alpha', 'zone_id' => Zone::factory()->create(['name' => 'Beta'])->id]);
    Client::factory()->create(['name' => 'Charlie', 'zone_id' => Zone::factory()->create(['name' => 'Alpha'])->id]);

    $names = fn (array $query) => collect(test()->get(route('clients.index', $query))->viewData('page')['props']['clients']['data'])->pluck('name')->all();

    expect($names([]))->toBe(['Alpha', 'Bravo', 'Charlie'])
        ->and($names(['sort' => 'name', 'direction' => 'desc']))->toBe(['Charlie', 'Bravo', 'Alpha'])
        ->and($names(['sort' => 'zone']))->toBe(['Charlie', 'Alpha', 'Bravo']);
});

test('the sort is sent back with the filters so that the headers can show it', function () {
    $this->get(route('clients.index', ['sort' => 'zone', 'direction' => 'desc']))
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'zone')->where('filters.direction', 'desc'));

    $this->get(route('clients.index'))
        ->assertInertia(fn ($page) => $page->where('filters.sort', null)->where('filters.direction', 'asc'));
});

test('a column the list does not offer is ignored, and so is a strange direction', function () {
    Client::factory()->create(['name' => 'Bravo']);
    Client::factory()->create(['name' => 'Alpha']);

    $this->get(route('clients.index', ['sort' => 'password; drop table clients', 'direction' => 'sideways']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', null)->where('clients.data.0.name', 'Alpha'));

    $this->get(route('clients.index', ['sort' => 'name', 'direction' => 'sideways']))
        ->assertInertia(fn ($page) => $page->where('filters.direction', 'asc'));
});

test('the orders are sorted by client name and by total', function () {
    $small = Order::factory()->for(Client::factory()->create(['name' => 'Zed']))->create(['total_sale' => 1000]);
    $big = Order::factory()->for(Client::factory()->create(['name' => 'Abe']))->create(['total_sale' => 9000]);

    $ids = fn (array $query) => collect(test()->get(route('orders.index', $query))->viewData('page')['props']['orders']['data'])->pluck('id')->all();

    expect($ids(['sort' => 'client']))->toBe([$big->id, $small->id])
        ->and($ids(['sort' => 'total', 'direction' => 'desc']))->toBe([$big->id, $small->id])
        ->and($ids(['sort' => 'total']))->toBe([$small->id, $big->id]);
});

test('the payments are sorted by amount and by client', function () {
    $low = Payment::factory()->create(['amount' => 1000]);
    $high = Payment::factory()->create(['amount' => 5000]);

    $ids = fn (array $query) => collect(test()->get(route('payments.index', $query))->viewData('page')['props']['payments']['data'])->pluck('id')->all();

    expect($ids(['sort' => 'amount']))->toBe([$low->id, $high->id])
        ->and($ids(['sort' => 'amount', 'direction' => 'desc']))->toBe([$high->id, $low->id])
        ->and($ids(['sort' => 'client']))->toHaveCount(2);
});

test('the products are sorted by shop', function () {
    $zebra = Product::factory()->create(['name' => 'A', 'shop_id' => Shop::factory()->create(['name' => 'Zebra'])->id]);
    $acme = Product::factory()->create(['name' => 'B', 'shop_id' => Shop::factory()->create(['name' => 'Acme'])->id]);

    $this->get(route('products.index', ['sort' => 'shop']))
        ->assertInertia(fn ($page) => $page->where('products.data.0.id', $acme->id)->where('products.data.1.id', $zebra->id)->where('sort.sort', 'shop'));
});

test('the export follows the sort of the page', function () {
    Client::factory()->create(['name' => 'Alpha']);
    Client::factory()->create(['name' => 'Bravo']);

    $csv = $this->get(route('list-exports.show', ['view' => 'clients', 'format' => 'csv', 'sort' => 'name', 'direction' => 'desc']))->getContent();

    expect(strpos($csv, 'Bravo'))->toBeLessThan(strpos($csv, 'Alpha'));
});
