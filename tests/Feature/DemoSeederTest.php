<?php

use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use Database\Seeders\DemoSeeder;

test('the demonstration data loads the base data it needs and the sale at every stage', function () {
    $this->artisan('app:seed-demo')->assertSuccessful();

    expect(Client::where('name', 'like', DemoSeeder::PREFIX.'%')->count())->toBe(8)
        ->and(Order::count())->toBe(8)
        ->and(Order::distinct()->pluck('status')->count())->toBeGreaterThanOrEqual(5)
        ->and(Payment::count())->toBe(6)
        ->and(Delivery::count())->toBe(3)
        ->and(CashMovement::count())->toBe(3);
});

test('running it again adds nothing', function () {
    $this->artisan('app:seed-demo')->assertSuccessful();
    $this->artisan('app:seed-demo')->assertSuccessful();

    expect(Client::where('name', 'like', DemoSeeder::PREFIX.'%')->count())->toBe(8)->and(Order::count())->toBe(8)->and(CashMovement::count())->toBe(3);
});

test('it is refused in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->artisan('app:seed-demo')->assertFailed();

    expect(Client::where('name', 'like', DemoSeeder::PREFIX.'%')->count())->toBe(0);
});
