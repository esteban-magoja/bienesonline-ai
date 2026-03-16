<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Channels\WhatsAppChannel;

class WelcomeWhatsAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    /**
     * Devuelve el template a enviar según el idioma del usuario.
     * El array resultante es procesado por WhatsAppChannel::send().
     */
    public function toWhatsApp(mixed $notifiable): array
    {
        $locale = $notifiable->locale ?? app()->getLocale();
        $locale = in_array($locale, ['es', 'en']) ? $locale : 'es';

        $templateConfig = config("whatsapp.templates.welcome.{$locale}");

        return [
            'template' => $templateConfig['name'],
            'language' => $templateConfig['language'],
            'params'   => [$notifiable->name], // Variable {{1}} del template
        ];
    }
}
