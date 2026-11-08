<?php

namespace App\Actions\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Lets a person choose their own password: the owner never knows it (playbook SEC-165). The link carries a
 * single-use token that expires, sent by e-mail or, when the mail is not available, handed to the owner to pass on.
 */
class InviteUser
{
    public const BROKER = 'invitations';

    /**
     * A new link for the user (the previous one stops working). Whoever holds it can set the password.
     */
    public function link(User $user): string
    {
        $token = Password::broker(self::BROKER)->createToken($user);

        return route('invitation.show', ['token' => $token, 'email' => $user->email]);
    }

    /**
     * Send the invitation by e-mail. False when the mail could not be sent (the owner can then pass the link on).
     */
    public function sendByMail(User $user, User $by): bool
    {
        try {
            $user->notify(new AccountInvitation($this->link($user), $this->validHours()));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $user->forceFill(['invitation_sent_at' => now()])->save();

        AuditLog::record('user.invited', $user, [], ['channel' => 'mail'], $by);

        return true;
    }

    /**
     * A link made for the owner to hand over (the mail is not available). Traced: it gives access to the account.
     */
    public function linkToHandOver(User $user, User $by): string
    {
        $url = $this->link($user);

        AuditLog::record('user.invitation_link_shown', $user, [], ['channel' => 'link'], $by);

        return $url;
    }

    public function validHours(): int
    {
        return intdiv((int) config('auth.passwords.'.self::BROKER.'.expire'), 60);
    }
}
