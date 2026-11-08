<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The e-mail inviting a person to choose the password of the account the owner created for them.
 */
class AccountInvitation extends Notification
{
    public function __construct(public readonly string $url, public readonly int $validHours) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('invitation.subject', ['app' => config('app.name')]))
            ->greeting(__('invitation.greeting', ['name' => $notifiable->name]))
            ->line(__('invitation.intro', ['app' => config('app.name')]))
            ->action(__('invitation.action'), $this->url)
            ->line(__('invitation.expires', ['hours' => $this->validHours]))
            ->line(__('invitation.ignore'));
    }
}
