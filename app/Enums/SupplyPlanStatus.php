<?php

namespace App\Enums;

enum SupplyPlanStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    /**
     * A plan can be paused and resumed, or ended for good.
     */
    public function canBecome(self $to): bool
    {
        return match ($this) {
            self::Active => $to === self::Paused || $to === self::Ended,
            self::Paused => $to === self::Active || $to === self::Ended,
            self::Ended => false,
        };
    }
}
