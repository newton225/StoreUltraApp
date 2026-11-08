<?php

namespace App\Http\Controllers;

use App\Actions\Users\InviteUser;
use App\Concerns\PasswordValidationRules;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public page where an invited person chooses their password (playbook SEC-165).
 */
class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): Response
    {
        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Set the password when the token is valid and has not expired, then send the person to the login page.
     */
    public function accept(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        // A deactivated account cannot be opened with a link it received before (same answer as an expired one).
        if (User::query()->where('email', $credentials['email'])->where('is_active', false)->exists()) {
            return back()->withErrors(['email' => __('access.invitation.expired')]);
        }

        $accepted = null;

        $status = Password::broker(InviteUser::BROKER)->reset(
            [...$credentials, 'password_confirmation' => $request->input('password_confirmation')],
            function (User $user, string $password) use (&$accepted): void {
                $user->forceFill([
                    'password' => $password,
                    'password_set_at' => now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'remember_token' => Str::random(60),
                ])->save();

                $accepted = $user;
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $accepted instanceof User) {
            return back()->withErrors(['email' => __('access.invitation.expired')]);
        }

        AuditLog::record('user.invitation_accepted', $accepted, [], [], $accepted);

        return to_route('login')->with('status', __('access.invitation.done'));
    }
}
