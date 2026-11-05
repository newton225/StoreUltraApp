<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * Writes to the journals everything that concerns an account: sign-in and sign-out, password changes and
 * resets, second factor, recovery codes and passkeys go to the audit journal (who did what, never a secret);
 * failed sign-ins and lock-outs go to the access journal, which is made for that volume (playbook SEC-162).
 */
class RecordAuthenticationEvents
{
    public function __construct(private readonly Request $request) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, fn (Login $event) => $this->audit('auth.login', $event->user, ['guard' => $event->guard, 'remember' => $event->remember]));
        $events->listen(Logout::class, fn (Logout $event) => $this->audit('auth.logout', $event->user));
        $events->listen(PasswordReset::class, fn (PasswordReset $event) => $this->audit('auth.password_reset', $event->user));
        $events->listen(PasswordUpdatedViaController::class, fn (PasswordUpdatedViaController $event) => $this->audit('auth.password_changed', $event->user));
        $events->listen(Verified::class, fn (Verified $event) => $this->audit('auth.email_verified', $event->user));
        $events->listen(TwoFactorAuthenticationEnabled::class, fn (TwoFactorAuthenticationEnabled $event) => $this->audit('auth.two_factor_enabled', $event->user));
        $events->listen(TwoFactorAuthenticationConfirmed::class, fn (TwoFactorAuthenticationConfirmed $event) => $this->audit('auth.two_factor_confirmed', $event->user));
        $events->listen(TwoFactorAuthenticationDisabled::class, fn (TwoFactorAuthenticationDisabled $event) => $this->audit('auth.two_factor_disabled', $event->user));
        $events->listen(RecoveryCodesGenerated::class, fn (RecoveryCodesGenerated $event) => $this->audit('auth.recovery_codes_generated', $event->user));
        $events->listen(TwoFactorAuthenticationFailed::class, fn (TwoFactorAuthenticationFailed $event) => $this->audit('auth.two_factor_failed', $event->user));
        $events->listen(PasskeyRegistered::class, fn (PasskeyRegistered $event) => $this->audit('auth.passkey_registered', $event->user, ['name' => $event->passkey->name ?? null]));
        $events->listen(PasskeyDeleted::class, fn (PasskeyDeleted $event) => $this->audit('auth.passkey_deleted', $event->user, ['name' => $event->passkey->name ?? null]));

        $events->listen(Failed::class, fn (Failed $event) => AccessLog::record(AccessLog::LOGIN_FAILED, $this->request, [
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => isset($event->credentials['email']) ? mb_substr((string) $event->credentials['email'], 0, 255) : null,
        ]));
        $events->listen(Lockout::class, fn (Lockout $event) => AccessLog::record(AccessLog::LOCKOUT, $event->request, [
            'email' => mb_substr((string) $event->request->input('email'), 0, 255) ?: null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function audit(string $action, mixed $user, array $after = []): void
    {
        if (! $user instanceof User) {
            return;
        }

        try {
            AuditLog::record($action, $user, [], $after, $user);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
