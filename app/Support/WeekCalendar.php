<?php

namespace App\Support;

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

    public function __construct(private BusinessSettings $settings) {}

    /**
     * The first day of the week of the given date, according to the setting.
     */
    public function startOf(CarbonInterface $date): CarbonImmutable
    {
        $firstDay = self::DAYS[$this->settings->get('week_starts_on')] ?? CarbonInterface::MONDAY;

        return CarbonImmutable::instance($date)->startOfDay()->startOfWeek($firstDay);
    }
}
