<?php

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\AdvanceArrears;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('the costs are sorted on the whole list by an accepted column, both ways', function () {
    foreach ([3000, 1000, 2000] as $amount) {
        Expense::factory()->create(['amount' => $amount]);
    }

    $amounts = fn (array $query): array => collect($this->actingAs($this->owner)->get(route('expenses.index', $query))->viewData('page')['props']['expenses']['data'])->pluck('amount')->all();

    expect($amounts(['sort' => 'amount', 'direction' => 'asc']))->toBe([1000, 2000, 3000])
        ->and($amounts(['sort' => 'amount', 'direction' => 'desc']))->toBe([3000, 2000, 1000]);
});

test('a column the list does not offer falls back to the natural order, without an error', function () {
    Expense::factory()->create(['amount' => 1000, 'incurred_on' => today()->subDays(5)]);
    Expense::factory()->create(['amount' => 2000, 'incurred_on' => today()]);

    $response = $this->actingAs($this->owner)->get(route('expenses.index', ['sort' => 'password; drop table users', 'direction' => 'desc']))->assertOk();
    $props = $response->viewData('page')['props'];

    expect(collect($props['expenses']['data'])->pluck('amount')->all())->toBe([2000, 1000])
        ->and($props['sort'])->toBe(['sort' => null, 'direction' => 'asc']);
});

test('the cash journal sorts by amount, and its in/out filter no longer shares the sort direction', function () {
    CashMovement::factory()->create(['direction' => CashMovement::IN, 'amount' => 500]);
    CashMovement::factory()->create(['direction' => CashMovement::IN, 'amount' => 900]);
    CashMovement::factory()->create(['direction' => CashMovement::OUT, 'amount' => 700]);

    $rows = fn (array $query): array => collect($this->actingAs($this->owner)->get(route('cash.index', $query))->viewData('page')['props']['movements']['data'])->pluck('amount')->all();

    // Sorted AND filtered at once: this was impossible while both used the `direction` parameter.
    expect($rows(['flow' => 'in', 'sort' => 'amount', 'direction' => 'desc']))->toBe([900, 500])
        ->and($rows(['sort' => 'amount', 'direction' => 'asc']))->toBe([500, 700, 900]);
});

test('the deliveries history, the documents and the purchase orders accept their sort', function () {
    Delivery::factory()->count(2)->create();
    Document::factory()->count(2)->create();
    PurchaseOrder::factory()->count(2)->create();

    foreach ([
        ['deliveries.index', 'deliveries', 'kits'],
        ['documents.index', 'documents', 'number'],
        ['purchases.index', 'purchaseOrders', 'reference'],
    ] as [$route, $prop, $column]) {
        $this->actingAs($this->owner)->get(route($route, ['sort' => $column, 'direction' => 'desc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('sort.sort', $column)->where('sort.direction', 'desc')->has($prop.'.data', 2));
    }
});

test('the export follows the sort of the screen', function () {
    foreach ([3000, 1000, 2000] as $amount) {
        Expense::factory()->create(['amount' => $amount]);
    }

    $csv = $this->actingAs($this->owner)->get(route('list-exports.show', ['view' => 'expenses', 'format' => 'csv', 'sort' => 'amount', 'direction' => 'asc']))->assertOk()->getContent();

    expect(strpos($csv, '1000'))->toBeLessThan(strpos($csv, '2000'))
        ->and(strpos($csv, '2000'))->toBeLessThan(strpos($csv, '3000'));
});

test('every accepted column of every sorted list runs on the database, both ways', function () {
    Delivery::factory()->create();
    Document::factory()->create();
    Expense::factory()->create();
    CashMovement::factory()->create();
    PurchaseOrder::factory()->create();

    $lists = [
        'deliveries.index' => Delivery::SORTABLE,
        'documents.index' => Document::SORTABLE,
        'expenses.index' => Expense::SORTABLE,
        'cash.index' => CashMovement::SORTABLE,
        'purchases.index' => PurchaseOrder::SORTABLE,
        'payments.arrears' => AdvanceArrears::SORTABLE,
        'payments.index' => Payment::SORTABLE,
        'orders.index' => Order::SORTABLE,
        'clients.index' => Client::SORTABLE,
        'products.index' => Product::SORTABLE,
    ];

    foreach ($lists as $route => $columns) {
        foreach ($columns as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $this->actingAs($this->owner)->get(route($route, ['sort' => $column, 'direction' => $direction]))->assertOk();
            }
        }
    }
});
