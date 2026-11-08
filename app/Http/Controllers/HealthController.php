<?php

namespace App\Http\Controllers;

use App\Support\HealthReport;
use Inertia\Inertia;
use Inertia\Response;

class HealthController extends Controller
{
    /**
     * Show whether the system is running as it should (owner only).
     */
    public function index(HealthReport $report): Response
    {
        $checks = $report->checks();

        return Inertia::render('health/index', [
            'checks' => $checks,
            'summary' => $report->summary($checks),
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
