<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner sees the profit, creates the users and changes the settings: until they have set up a
 * second factor, the only places open to them are the security page where they do it, the password
 * confirmation it asks for, the language choice and the way out. The page says why it is closed.
 */
class RequireOwnerTwoFactor
{
    /**
     * The routes that stay open so the owner can set up the second factor, or leave.
     *
     * @var list<string>
     */
    private const OPEN_ROUTES = [
        'security.edit',
        'two-factor.*',
        'password.confirm',
        'password.confirm.*',
        'password.confirmation',
        'verification.*',
        'passkey.*',
        'logout',
        'locale.update',
        'appearance.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || ! config('security.require_owner_two_factor')
            || ! $user->isOwner()
            || $user->hasEnabledTwoFactorAuthentication()
            || $request->routeIs(...self::OPEN_ROUTES)) {
            return $next($request);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => __('account.two_factor_required.toast')]);

        return to_route('security.edit');
    }
}
