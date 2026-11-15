<?php

namespace App\Support;

/**
 * A shop sells by package (a packet of 10 bars): what is bought is a whole number of packages, never a fraction.
 */
final class PackQuantity
{
    /**
     * The smallest multiple of the package size that covers the units needed (0 when nothing is needed).
     */
    public static function units(int $needed, int $packSize): int
    {
        return self::packs($needed, $packSize) * max(1, $packSize);
    }

    /**
     * How many whole packages cover the units needed.
     */
    public static function packs(int $needed, int $packSize): int
    {
        if ($needed <= 0) {
            return 0;
        }

        return intdiv($needed + max(1, $packSize) - 1, max(1, $packSize));
    }
}
