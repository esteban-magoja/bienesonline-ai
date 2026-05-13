<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PropertyMatchAdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    /**
     * @return array{template: string, language: string, params: array<int, string>}
     */
    public function toWhatsApp(mixed $notifiable): array
    {
        $locale = $notifiable->locale ?? app()->getLocale();
        $locale = in_array($locale, ['es', 'en']) ? $locale : 'es';

        $templateConfig = config("whatsapp.templates.match_ad.{$locale}");

        return [
            'template' => $templateConfig['name'],
            'language' => $templateConfig['language'],
            'params'   => [],
        ];
    }
}
