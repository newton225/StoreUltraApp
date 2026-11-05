<?php

use App\Models\Attachment;
use App\Models\AttachmentType;
use App\Models\CashClosing;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The lists that grow are paginated on the server (playbook DATA-70): the browser only gets the page shown,
 * the totals still cover the whole list, and the number of queries does not depend on the number of rows
 * (DATA-71).
 */
function queriesFor(string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function lateOrders(int $count): void
{
    Order::factory()->count($count)->for(Client::factory())->create(['status' => 'proforma_issued', 'delivery_week' => today()->startOfWeek()->subWeek(), 'advance_due' => 10000]);
}

test('the late advances are paginated and the total still covers every page', function () {
    lateOrders(35);
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('payments.arrears'))->assertInertia(fn ($page) => $page
        ->has('orders.data', 30)
        ->where('orders.last_page', 2)
        ->where('orders.total', 35)
        ->where('total_outstanding', 350000));

    $this->get(route('payments.arrears', ['page' => 2]))->assertInertia(fn ($page) => $page->has('orders.data', 5)->where('total_outstanding', 350000));
});

test('the products are paginated and searched on the server, by name or by shop', function () {
    $shop = Shop::factory()->create(['name' => 'Quincaillerie Centrale']);
    Product::factory()->count(34)->create();
    Product::factory()->create(['name' => 'Ciment gris', 'shop_id' => $shop->id]);
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('products.index'))->assertInertia(fn ($page) => $page->has('products.data', 30)->where('products.last_page', 2)->where('products.total', 35));

    $this->get(route('products.index', ['search' => 'ciment']))->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.name', 'Ciment gris')->where('search', 'ciment'));
    $this->get(route('products.index', ['search' => 'Quincaillerie']))->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.name', 'Ciment gris'));
    $this->get(route('products.index', ['search' => '%']))->assertInertia(fn ($page) => $page->has('products.data', 0));
});

test('the administrative documents are paginated and searched by number', function () {
    $type = AttachmentType::factory()->create(['applies_to' => 'company']);
    Attachment::factory()->count(34)->create(['attachment_type_id' => $type->id]);
    Attachment::factory()->create(['attachment_type_id' => $type->id, 'number' => 'RCCM-UNIQUE-77']);
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('attachments.index', ['kind' => 'company']))->assertInertia(fn ($page) => $page->has('attachments.data', 30)->where('attachments.total', 35));
    $this->get(route('attachments.index', ['kind' => 'company', 'search' => 'UNIQUE-77']))->assertInertia(fn ($page) => $page->has('attachments.data', 1)->where('attachments.data.0.number', 'RCCM-UNIQUE-77'));
});

test('the cash closings have their own page number, apart from the movements', function () {
    foreach (range(1, 13) as $day) {
        CashClosing::factory()->create(['closed_on' => today()->subDays($day)]);
    }
    $this->actingAs(User::factory()->owner()->create());

    $this->get(route('cash.index'))->assertInertia(fn ($page) => $page->has('closings.data', 12)->where('closings.last_page', 2));
    $this->get(route('cash.index', ['closings_page' => 2]))->assertInertia(fn ($page) => $page->has('closings.data', 1)->where('movements.current_page', 1));
});

test('the number of queries of a paginated page does not grow with the rows', function (string $route) {
    $this->actingAs(User::factory()->owner()->create());

    $url = match ($route) {
        'arrears' => fn () => route('payments.arrears'),
        'products' => fn () => route('products.index'),
        'attachments' => fn () => route('attachments.index'),
        default => fn () => route('cash.index'),
    };

    $seed = function (int $count) use ($route): void {
        match ($route) {
            'arrears' => lateOrders($count),
            'products' => Product::factory()->count($count)->create(['shop_id' => Shop::factory()->create()->id]),
            'attachments' => Attachment::factory()->count($count)->create(['attachment_type_id' => AttachmentType::query()->firstOrCreate(['code' => 'rccm', 'applies_to' => 'company'], ['label_fr' => 'RCCM', 'label_en' => 'RCCM'])->id]),
            default => collect(range(1, $count))->each(fn (int $day) => CashClosing::factory()->create(['closed_on' => today()->subDays(100 + $day + CashClosing::count())])),
        };
    };

    $seed(3);
    queriesFor($url()); // the first request also warms what the application caches (settings, rights)
    $small = queriesFor($url());
    $seed(20);
    $large = queriesFor($url());

    expect($large)->toBe($small);
})->with(['arrears', 'products', 'attachments', 'closings']);
