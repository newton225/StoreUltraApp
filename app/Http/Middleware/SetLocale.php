<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the language: profile, then session, then browser (visitors only), then default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $user = $request->user();

        $candidates = [
            $user?->locale,
            $request->session()->get('locale'),
            $user === null && filled($request->header('Accept-Language'))
                ? $request->getPreferredLanguage($this->supportedLocales())
                : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $this->supportedLocales(), true)) {
                return $candidate;
            }
        }

        return config('app.locale');
    }

    /**
     * @return array<int, string>
     */
    protected function supportedLocales(): array
    {
        return config('app.supported_locales');
    }
}
