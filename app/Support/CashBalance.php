<?php

namespace App\Support;

use App\Models\CashMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What the books say the cash box holds: money in minus money out, cancelled movements left out.
 */
class CashBalance
{
    /**
     * The balance at the end of a day, that day included.
     */
    public function asOf(CarbonInterface $date): int
    {
        return $this->net(CashMovement::query()->whereNull('cancelled_at')->whereDate('occurred_on', '<=', $date));
    }

    /**
     * The net amount (in minus out) of a set of movements.
     *
     * @param  Builder<CashMovement>  $query
     */
    public function net(Builder $query): int
    {
        return (int) $query->sum(DB::raw("case when direction = 'out' then -amount else amount end"));
    }
}
