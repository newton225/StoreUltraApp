<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum Frequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    /**
     * The date of the given cycle (0 is the first one) counted from the start date.
     */
    public function dateOfCycle(CarbonImmutable $start, int $cycle): CarbonImmutable
    {
        return match ($this) {
            self::Weekly => $start->addWeeks($cycle),
            self::Biweekly => $start->addWeeks(2 * $cycle),
            self::Monthly => $start->addMonthsNoOverflow($cycle),
        };
    }
}
