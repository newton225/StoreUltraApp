<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Kit;
use App\Models\Order;
use App\Models\SupplyPlan;
use App\Models\User;

/**
 * @return array<string, mixed>
 */
function planPayload(Kit $kit, array $overrides = []): array
{
    return [
        'kit_id' => $kit->id,
        'kits_per_cycle' => 3,
        'advance_amount' => 200000,
        'frequency' => 'weekly',
        'starts_on' => today()->toDateString(),
        ...$overrides,
    ];
}

test('the office sets up a recurring supply for a client', function () {
    $kit = pricedKit()->kit;
    $client = Client::factory()->create();
    $employee = User::factory()->create();

    $this->actingAs($employee)
        ->post(route('clients.plans.store', $client), planPayload($kit))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clients.show', $client));

    $plan = SupplyPlan::firstOrFail();

    expect($plan->client_id)->toBe($client->id);
    expect($plan->kit_id)->toBe($kit->id);
    expect($plan->kits_per_cycle)->toBe(3);
    expect($plan->advance_amount)->toBe(200000);
    expect($plan->frequency->value)->toBe('weekly');
    expect($plan->status->value)->toBe('active');
    expect($plan->created_by)->toBe($employee->id);
    expect(AuditLog::where('action', 'supply_plan.created')->count())->toBe(1);
});

test('the orders that are already due are generated as soon as the plan exists', function () {
    $kit = pricedKit()->kit;
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('clients.plans.store', $client), planPayload($kit));

    expect(Order::count())->toBe(2);

    $order = Order::orderBy('delivery_week')->firstOrFail();

    expect($order->client_id)->toBe($client->id);
    expect($order->kits_count)->toBe(3);
    expect($order->advance_due)->toBe(200000);
    expect($order->supply_plan_id)->toBe(SupplyPlan::firstOrFail()->id);
});

test('a supply plan is rejected for invalid data', function (Closure $mutate, string $field) {
    $kit = pricedKit()->kit;
    $inactive = Kit::factory()->create(['active' => false]);

    $this->actingAs(User::factory()->create())
        ->post(route('clients.plans.store', Client::factory()->create()), $mutate(planPayload($kit), $inactive))
        ->assertSessionHasErrors($field);

    expect(SupplyPlan::count())->toBe(0);
})->with([
    'unknown kit' => [fn (array $p) => [...$p, 'kit_id' => 9999], 'kit_id'],
    'inactive kit' => [fn (array $p, Kit $inactive) => [...$p, 'kit_id' => $inactive->id], 'kit_id'],
    'no kit per cycle' => [fn (array $p) => [...$p, 'kits_per_cycle' => 0], 'kits_per_cycle'],
    'negative advance' => [fn (array $p) => [...$p, 'advance_amount' => -1], 'advance_amount'],
    'unknown frequency' => [fn (array $p) => [...$p, 'frequency' => 'daily'], 'frequency'],
    'missing start' => [fn (array $p) => [...$p, 'starts_on' => ''], 'starts_on'],
    'end before start' => [fn (array $p) => [...$p, 'ends_on' => today()->subDay()->toDateString()], 'ends_on'],
    'zero cycles' => [fn (array $p) => [...$p, 'cycles_count' => 0], 'cycles_count'],
]);

test('a field agent cannot set up a supply plan', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('clients.plans.store', Client::factory()->create()), planPayload(pricedKit()->kit))
        ->assertForbidden();

    expect(SupplyPlan::count())->toBe(0);
});

test('a plan can be paused, resumed and ended, and an ended plan stays ended', function () {
    $plan = SupplyPlan::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->patch(route('plans.update', $plan), ['status' => 'paused'])->assertSessionHasNoErrors();
    expect($plan->refresh()->status->value)->toBe('paused');

    $this->patch(route('plans.update', $plan), ['status' => 'active'])->assertSessionHasNoErrors();
    expect($plan->refresh()->status->value)->toBe('active');

    $this->patch(route('plans.update', $plan), ['status' => 'ended'])->assertSessionHasNoErrors();
    expect($plan->refresh()->status->value)->toBe('ended');

    $this->patch(route('plans.update', $plan), ['status' => 'active'])->assertSessionHasErrors('status');
    expect($plan->refresh()->status->value)->toBe('ended');
});

test('changing a plan only affects the orders to come', function () {
    $plan = SupplyPlan::factory()->create(['kits_per_cycle' => 3, 'advance_amount' => 200000]);
    $existing = Order::factory()->for($plan->client)->create([
        'supply_plan_id' => $plan->id,
        'kits_count' => 3,
        'advance_due' => 200000,
    ]);

    $this->actingAs(User::factory()->create())
        ->patch(route('plans.update', $plan), ['kits_per_cycle' => 5, 'advance_amount' => 150000])
        ->assertSessionHasNoErrors();

    expect($plan->refresh()->kits_per_cycle)->toBe(5);
    expect($existing->refresh()->kits_count)->toBe(3);
    expect($existing->advance_due)->toBe(200000);
    expect(AuditLog::where('action', 'supply_plan.updated')->count())->toBe(1);
});

test('a field agent cannot update a plan', function () {
    $plan = SupplyPlan::factory()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->patch(route('plans.update', $plan), ['status' => 'paused'])
        ->assertForbidden();
});

test('a mistake in the form is explained under its field, in the language of the user', function (string $locale) {
    app()->setLocale($locale);
    $kit = pricedKit()->kit;
    $client = Client::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('clients.plans.store', $client), planPayload($kit, ['kit_id' => '']))
        ->assertSessionHasErrors(['kit_id' => __('orders.plans.errors.kit_required', locale: $locale)]);

    $this->actingAs($user)->post(route('clients.plans.store', $client), planPayload($kit, ['ends_on' => today()->subDay()->toDateString()]))
        ->assertSessionHasErrors(['ends_on' => __('orders.plans.errors.ends_after_start', locale: $locale)]);

    expect(SupplyPlan::count())->toBe(0);
})->with(['fr', 'en']);
