<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email al dueño de anuncios cuando hay nuevas solicitudes compatibles.
 * Se envía con notifyNow() desde NotifyMatchingListingsByEmail (que ya corre en cola)
 * para poder registrar el resultado en email_message_logs.
 */
class PropertyMatchAdEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ?int $propertyRequestId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('emails.property_match_ad.subject'))
            ->greeting(__('emails.property_match_ad.greeting', ['name' => $notifiable->name]))
            ->line(__('emails.property_match_ad.intro'))
            ->line(__('emails.property_match_ad.details'))
            ->action(__('emails.property_match_ad.view_matches'), route('dashboard.matches.index'))
            ->line(__('emails.property_match_ad.footer'))
            ->salutation(__('emails.common.regards') . ",\n" . __('emails.common.team') . ' ' . config('app.name'));
    }
}
