<?php

use App\Models\Order;
use App\Models\User;
use App\Support\BusinessSettings;
use App\Support\WeekCalendar;
use Illuminate\Support\Carbon;

test('a week starts on monday by default', function () {
    $start = app(WeekCalendar::class)->startOf(Carbon::parse('2026-07-15'));

    expect($start->toDateString())->toBe('2026-07-13');
});

test('a monday is the start of its own week', function () {
    expect(app(WeekCalendar::class)->startOf(Carbon::parse('2026-07-13 18:30'))->toDateString())->toBe('2026-07-13');
});

test('a sunday belongs to the week that started the monday before', function () {
    expect(app(WeekCalendar::class)->startOf(Carbon::parse('2026-07-19'))->toDateString())->toBe('2026-07-13');
});

test('the first day of the week follows the setting', function () {
    app(BusinessSettings::class)->set('week_starts_on', 'sunday');

    expect(app(WeekCalendar::class)->startOf(Carbon::parse('2026-07-15'))->toDateString())->toBe('2026-07-12');
});

test('the weeks offered are those with live orders, this week and the chosen one, newest first', function () {
    $user = User::factory()->owner()->create();
    Order::factory()->create(['delivery_week' => '2026-03-02']);
    Order::factory()->create(['delivery_week' => '2026-03-02']);
    Order::factory()->create(['delivery_week' => '2026-02-23', 'status' => 'cancelled']);

    $weeks = app(WeekCalendar::class)->known($user, Carbon::parse('2026-01-14'));

    expect($weeks)->toContain('2026-03-02', '2026-01-12')
        ->and($weeks)->not->toContain('2026-02-23')
        ->and($weeks)->toContain(app(WeekCalendar::class)->startOf(now())->toDateString())
        ->and($weeks)->toBe(collect($weeks)->sortDesc()->values()->all());
});
