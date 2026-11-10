<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * Informs the previous email address that the account email was changed.
 *
 * Informational only: it must not contain the new address or any link that changes account state.
 */
class EmailChangedNotification extends Notification
{
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(Lang::get('Your account email address was changed'))
            ->line(Lang::get('The email address of your :app account was changed. This address is no longer used to sign in.', [
                'app' => config('app.name'),
            ]));

        $support = $this->supportContact();

        return $support === null
            ? $mail->line(Lang::get('If you did not make this change, contact support immediately.'))
            : $mail->line(Lang::get('If you did not make this change, contact support immediately at :contact.', [
                'contact' => $support,
            ]));
    }

    private function supportContact(): ?string
    {
        $email = config('security.support_email');
        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $url = config('security.support_url');
        if (is_string($url) && preg_match('#^https://#i', $url) === 1) {
            return $url;
        }

        return null;
    }
}
