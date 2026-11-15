<?php

namespace App\Enums;

enum StockMovementType: string
{
    /** Goods received from a shop: the stock grows. */
    case Receipt = 'receipt';

    /** Units given to an order: the stock shrinks. */
    case Allocation = 'allocation';

    /** Units given back by a cancelled order: the stock grows again. */
    case Release = 'release';

    /** A manual correction (breakage, loss, inventory, opening stock), in either direction. */
    case Adjustment = 'adjustment';

    /**
     * The phrase key of the audit log entry written for this kind of movement.
     */
    public function auditAction(): string
    {
        return match ($this) {
            self::Receipt => 'stock.received',
            self::Allocation => 'stock.allocated',
            self::Release => 'stock.released',
            self::Adjustment => 'stock.adjusted',
        };
    }
}
