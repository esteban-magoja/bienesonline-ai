<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Contracts\ProvidesWhatsAppLogContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WelcomeWhatsAppNotification extends Notification implements ShouldQueue, ProvidesWhatsAppLogContext
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
            'params'   => ['customer_name' => $notifiable->name],
        ];
    }

    /**
     * @return array{event_type: string, property_listing_id: null, property_request_id: null}
     */
    public function getWhatsAppLogContext(): array
    {
        return [
            'event_type'          => 'welcome',
            'property_listing_id' => null,
            'property_request_id' => null,
        ];
    }
}
