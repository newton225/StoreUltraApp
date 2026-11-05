<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The security headers of every page: a Content Security Policy with a nonce per request, no framing,
 * no sniffing, a restrictive Permissions-Policy, HSTS over HTTPS, and no cache or referrer for the
 * routes that give out a document.
 */
class SecurityHeaders
{
    /**
     * The routes that give out a file or an export: nothing of them is cached or sent as a referrer.
     *
     * @var list<string>
     */
    private const SENSITIVE_ROUTES = [
        'attachments.file',
        'audit.archives.download',
        'dashboard.pdf',
        'deliveries.signature',
        'documents.download',
        'expenses.proof',
        'exports.show',
        'list-exports.show',
        'orders.documents.zip',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('security.csp');
        $nonce = $mode === 'off' ? null : Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->routeIs(...self::SENSITIVE_ROUTES)) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('Cache-Control', 'no-store, private');
        } else {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if ($nonce !== null) {
            $header = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';

            $response->headers->set($header, $this->policy($nonce));
        }

        return $response;
    }

    /**
     * The policy for this request. Only the application itself and the nonce of the page may run a
     * script or a style; the one inline style a library writes at run time is allowed by its hash.
     */
    private function policy(string $nonce): string
    {
        $dev = $this->devServer();
        $styles = array_map(fn (string $hash): string => "'{$hash}'", array_values((array) config('security.csp_style_hashes')));

        $directives = [
            "default-src 'self'",
            implode(' ', ["script-src 'self'", "'nonce-{$nonce}'", ...$dev]),
            implode(' ', ["style-src 'self'", "'nonce-{$nonce}'", ...$styles, ...$dev]),
            implode(' ', ["img-src 'self'", 'data:', 'blob:', ...$dev]),
            implode(' ', ["font-src 'self'", 'data:', ...$dev]),
            implode(' ', ["connect-src 'self'", ...$dev, ...array_map(fn (string $origin): string => preg_replace('#^http#', 'ws', $origin) ?? $origin, $dev)]),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        return implode('; ', $directives);
    }

    /**
     * The address of the Vite development server when it is running, so that a local page may load
     * its scripts and styles. Nothing is added on a server.
     *
     * @return list<string>
     */
    private function devServer(): array
    {
        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return [];
        }

        $hot = trim((string) file_get_contents(public_path('hot')));

        return $hot === '' ? [] : [$hot];
    }
}
