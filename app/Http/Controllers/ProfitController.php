<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Support\ProfitReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitController extends Controller
{
    /**
     * The theoretical and real profit of a period, in total and grouped by week, zone, client or kit.
     */
    public function index(Request $request, ProfitReport $report): Response
    {
        $group = in_array($request->string('group')->toString(), ProfitReport::GROUPS, true)
            ? $request->string('group')->toString()
            : 'week';

        $filters = [
            'from' => ($request->date('from') ?? today()->startOfMonth())->toDateString(),
            'to' => ($request->date('to') ?? today()->endOfMonth())->toDateString(),
            'zone' => $request->integer('zone') ?: null,
            'scope' => $request->string('scope')->toString() === 'all' ? 'all' : 'settled',
            'group' => $group,
        ];

        $result = $report->build($filters);

        return Inertia::render('profits/index', [
            'totals' => $result['totals'],
            'rows' => $result['rows'],
            'filters' => $filters,
            'groups' => ProfitReport::GROUPS,
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
