<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The period, sub-prefecture and kit chosen on the dashboard and carried to its exports. A field
 * agent's zone choice is only a narrowing: the orders and clients they may see are limited anyway.
 */
class ReportFilters
{
    /**
     * @return array{from: string, to: string, zone: int|null, kit: int|null, shop: int|null}
     */
    public static function fromRequest(Request $request, ?User $user = null): array
    {
        $from = $request->date('from') ?? CarbonImmutable::today()->startOfMonth();
        $to = $request->date('to') ?? CarbonImmutable::today()->endOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'zone' => $request->integer('zone') ?: null,
            'kit' => $request->integer('kit') ?: null,
            'shop' => $request->integer('shop') ?: null,
        ];
    }
}
