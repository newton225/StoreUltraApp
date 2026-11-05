<?php

use App\Models\User;
use Illuminate\Support\Facades\Vite;

function pageNonce(string $html): string
{
    preg_match('/<script nonce="([^"]+)"/', $html, $found);

    return $found[1] ?? '';
}

test('every page carries the security headers', function () {
    $response = $this->get('/');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()')->toContain('geolocation=()');
});

test('the policy lets only the page itself run scripts and styles, by a nonce', function () {
    $response = $this->get('/');
    $policy = (string) $response->headers->get('Content-Security-Policy');
    $nonce = pageNonce($response->getContent());

    expect($nonce)->not->toBe('');
    expect($policy)->toContain("script-src 'self' 'nonce-{$nonce}'");
    expect($policy)->toContain("style-src 'self' 'nonce-{$nonce}'");
    expect($policy)->toContain("default-src 'self'")->toContain("object-src 'none'")->toContain("base-uri 'self'")->toContain("frame-ancestors 'none'")->toContain("form-action 'self'");
    expect($policy)->not->toContain('unsafe-inline')->not->toContain('unsafe-eval');
    expect($response->getContent())->toContain('<style nonce="'.$nonce.'">');
});

test('the nonce is new for every request', function () {
    $first = pageNonce($this->get('/')->getContent());
    $second = pageNonce($this->get('/')->getContent());

    expect($first)->not->toBe('')->not->toBe($second);
});

test('every script of the page carries the nonce', function () {
    $html = $this->get('/')->getContent();

    preg_match_all('/<script\b[^>]*>/', $html, $tags);

    foreach ($tags[0] as $tag) {
        if (str_contains($tag, 'type="application/json"')) {
            continue;
        }

        expect($tag)->toContain('nonce="');
    }
});

test('the policy can be set to report only, or switched off', function () {
    config(['security.csp' => 'report-only']);
    $reporting = $this->get('/');

    expect($reporting->headers->has('Content-Security-Policy'))->toBeFalse();
    expect($reporting->headers->get('Content-Security-Policy-Report-Only'))->toContain("object-src 'none'");

    config(['security.csp' => 'off']);
    $off = $this->get('/');

    expect($off->headers->has('Content-Security-Policy'))->toBeFalse();
    expect($off->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

test('HSTS is only sent over HTTPS', function () {
    expect($this->get('http://localhost/')->headers->has('Strict-Transport-Security'))->toBeFalse();
    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});

test('the routes that give out a file are never cached and send no referrer', function () {
    $this->actingAs(User::factory()->owner()->create());

    $response = $this->get(route('exports.show', 'clients'));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
});

test('the style a library writes at run time is allowed by its hash and that hash is the installed one', function () {
    $file = base_path('node_modules/sonner/dist/index.mjs');

    if (! file_exists($file)) {
        $this->markTestSkipped('sonner is not installed.');
    }

    $line = collect(explode("\n", (string) file_get_contents($file)))->first(fn (string $line): bool => str_starts_with($line, '__insertCSS("'));
    $start = strlen('__insertCSS(');
    $css = json_decode(substr($line, $start, (int) strrpos($line, ');') - $start), true);
    $hash = 'sha256-'.base64_encode(hash('sha256', $css, true));

    expect($hash)->toBe(config('security.csp_style_hashes.sonner'));
    expect((string) $this->get('/')->headers->get('Content-Security-Policy'))->toContain("'{$hash}'");
});

test('the nonce is only generated when the policy is on', function () {
    config(['security.csp' => 'off']);

    $this->get('/');

    expect(Vite::cspNonce())->toBeNull();
});
