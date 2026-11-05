<?php

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
