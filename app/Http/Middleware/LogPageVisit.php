<?php

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes to the access journal each page a signed-in user opens: who, which page, the answer, the address
 * and the browser. It runs after the answer has been sent (terminate), so it never slows a page, and it
 * ignores what is not a page (assets, health check, background requests).
 */
class LogPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('security.access_log') || ! $request->isMethod('GET') || $request->user() === null) {
            return;
        }

        if ($response->isRedirection()) {
            return;
        }

        if (! $request->headers->has('X-Inertia') && ! $request->acceptsHtml()) {
            return;
        }

        if ($request->is('up') || $request->header('X-Inertia-Partial-Component') !== null) {
            return;
        }

        AccessLog::record(AccessLog::PAGE, $request, ['status' => $response->getStatusCode()]);
    }
}
