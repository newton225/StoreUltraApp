<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class WeekCalendar
{
    private const DAYS = [
        'sunday' => CarbonInterface::SUNDAY,
        'monday' => CarbonInterface::MONDAY,
        'tuesday' => CarbonInterface::TUESDAY,
        'wednesday' => CarbonInterface::WEDNESDAY,
        'thursday' => CarbonInterface::THURSDAY,
        'friday' => CarbonInterface::FRIDAY,
        'saturday' => CarbonInterface::SATURDAY,
    ];

    /** How many past delivery weeks a list of weeks offers. */
    public const KNOWN = 52;

    public function __construct(private BusinessSettings $settings) {}

    /**
     * The weeks a list of weeks offers: the delivery weeks that have live orders the user may see (the latest
     * {@see self::KNOWN} of them), this week, and the given dates' weeks, newest first, as dates (Y-m-d).
     *
     * @return list<string>
     */
    public function known(User $user, CarbonInterface ...$include): array
    {
        $stored = Order::query()
            ->visibleTo($user)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->distinct()
            ->orderByDesc('delivery_week')
            ->limit(self::KNOWN)
            ->pluck('delivery_week')
            ->map(fn (mixed $week): string => CarbonImmutable::parse($week)->toDateString());

        $weeks = $stored
            ->merge(array_map(fn (CarbonInterface $date): string => $this->startOf($date)->toDateString(), [now(), ...$include]))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return array_values($weeks);
    }

    /**
     * The first day of the week of the given date, according to the setting.
     */
    public function startOf(CarbonInterface $date): CarbonImmutable
    {
        $firstDay = self::DAYS[$this->settings->get('week_starts_on')] ?? CarbonInterface::MONDAY;

        return CarbonImmutable::instance($date)->startOfDay()->startOfWeek($firstDay);
    }
}
