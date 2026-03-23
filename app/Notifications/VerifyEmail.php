<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends VerifyEmailBase
{
    public function toMail($notifiable)
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('auth.verify_email.subject'))
            ->greeting(__('emails.common.hello').' '.$notifiable->name.'!')
            ->line(__('auth.verify_email.body', ['email' => $notifiable->email]))
            ->action(__('auth.verify_email.action'), $url)
            ->line(__('auth.verify_email.outro'))
            ->salutation(__('emails.common.regards').",\n".config('app.name'));
    }
}
