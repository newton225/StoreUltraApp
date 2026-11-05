<?php

use App\Actions\Orders\GenerateDueOrders;
use App\Models\Client;
use App\Models\KitVersion;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SupplyPlan;
use App\Support\BusinessSettings;

/**
 * @param  array<string, mixed>  $attributes
 */
function activePlan(array $attributes = [], ?KitVersion $version = null): SupplyPlan
{
    $version ??= pricedKit(['valid_from' => '2026-01-01']);

    return SupplyPlan::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'kit_id' => $version->kit_id,
        'frequency' => 'weekly',
        'starts_on' => '2026-07-13',
        ...$attributes,
    ]);
}

/**
 * @return array<int, string>
 */
function plannedWeeks(SupplyPlan $plan): array
{
    return Order::where('supply_plan_id', $plan->id)
        ->orderBy('delivery_week')
        ->get()
        ->map(fn (Order $order) => $order->delivery_week->toDateString())
        ->all();
}

beforeEach(function () {
    $this->travelTo('2026-07-15 09:00');
});

test('the current week and the next one are generated, but not further', function () {
    $plan = activePlan();

    $result = app(GenerateDueOrders::class)->handle();

    expect(plannedWeeks($plan))->toBe(['2026-07-13', '2026-07-20']);
    expect($result)->toBe(['created' => 2, 'incomplete' => 0]);
});

test('running it again creates nothing, and a later run adds the next week', function () {
    $plan = activePlan();
    $generator = app(GenerateDueOrders::class);

    $generator->handle();
    expect($generator->handle()['created'])->toBe(0);

    $this->travelTo('2026-07-22 09:00');
    $generator->handle();

    expect(plannedWeeks($plan))->toBe(['2026-07-13', '2026-07-20', '2026-07-27']);
});

test('weeks that are already over are never generated', function () {
    $plan = activePlan(['starts_on' => '2026-05-04']);

    app(GenerateDueOrders::class)->handle();

    expect(plannedWeeks($plan))->toBe(['2026-07-13', '2026-07-20']);
});

test('a plan that is paused or ended generates nothing', function (string $status) {
    $plan = activePlan(['status' => $status]);

    app(GenerateDueOrders::class)->handle();

    expect(Order::count())->toBe(0);
})->with(['paused', 'ended']);

test('only active clients get orders', function (string $status) {
    $plan = activePlan();
    $plan->client->forceFill(['status' => $status])->save();

    app(GenerateDueOrders::class)->handle();

    expect(Order::count())->toBe(0);
})->with(['pending', 'suspended', 'ended']);

test('the end date and the number of cycles stop the generation', function () {
    $byDate = activePlan(['ends_on' => '2026-07-14']);
    $byCount = activePlan(['cycles_count' => 1]);

    app(GenerateDueOrders::class)->handle();

    expect(plannedWeeks($byDate))->toBe(['2026-07-13']);
    expect(plannedWeeks($byCount))->toBe(['2026-07-13']);
});

test('a plan every two weeks skips the weeks in between', function () {
    $plan = activePlan(['frequency' => 'biweekly']);

    app(GenerateDueOrders::class)->handle();
    expect(plannedWeeks($plan))->toBe(['2026-07-13']);

    $this->travelTo('2026-07-25');
    app(GenerateDueOrders::class)->handle();
    expect(plannedWeeks($plan))->toBe(['2026-07-13', '2026-07-27']);
});

test('a monthly plan delivers once a month', function () {
    $plan = activePlan(['frequency' => 'monthly', 'starts_on' => '2026-06-15']);

    app(GenerateDueOrders::class)->handle();

    expect(plannedWeeks($plan))->toBe(['2026-07-13']);
});

test('the first day of the week setting moves the delivery weeks', function () {
    app(BusinessSettings::class)->set('week_starts_on', 'sunday');
    $plan = activePlan(['starts_on' => '2026-07-12']);

    app(GenerateDueOrders::class)->handle();

    expect(plannedWeeks($plan))->toBe(['2026-07-12', '2026-07-19']);
});

test('each order uses the kit version in effect during its week', function () {
    $first = pricedKit(['version' => 1, 'valid_from' => '2026-01-01']);
    $second = makeKitVersion(
        [[Product::factory()->create(), 1, 1000]],
        ['kit_id' => $first->kit_id, 'version' => 2, 'valid_from' => '2026-07-20'],
    );
    ProductPrice::factory()->for($second->items->first()->product)->create(['purchase_price' => 400]);

    $plan = activePlan([], $first);

    app(GenerateDueOrders::class)->handle();

    $orders = Order::where('supply_plan_id', $plan->id)->orderBy('delivery_week')->get();

    expect($orders[0]->kit_version_id)->toBe($first->id);
    expect($orders[1]->kit_version_id)->toBe($second->id);
});

test('an order that cannot be priced is counted and not created', function () {
    $version = makeKitVersion([[Product::factory()->create(), 1, 1000]], ['valid_from' => '2026-01-01']);
    $plan = activePlan([], $version);

    $result = app(GenerateDueOrders::class)->handle();

    expect($result)->toBe(['created' => 0, 'incomplete' => 2]);
    expect(Order::count())->toBe(0);
});

test('a cancelled week is not generated again', function () {
    $plan = activePlan();
    $generator = app(GenerateDueOrders::class);
    $generator->handle();

    Order::where('supply_plan_id', $plan->id)->where('delivery_week', '2026-07-13')->update(['status' => 'cancelled']);

    expect($generator->handle()['created'])->toBe(0);
    expect(plannedWeeks($plan))->toBe(['2026-07-13', '2026-07-20']);
});

test('the command generates the orders and reports them', function () {
    activePlan();

    $this->artisan('orders:generate')->expectsOutputToContain('2')->assertSuccessful();

    expect(Order::count())->toBe(2);
});
