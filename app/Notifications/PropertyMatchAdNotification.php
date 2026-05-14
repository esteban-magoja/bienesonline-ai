<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Contracts\ProvidesWhatsAppLogContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PropertyMatchAdNotification extends Notification implements ShouldQueue, ProvidesWhatsAppLogContext
{
    use Queueable;

    public function __construct(
        public readonly ?int $propertyRequestId = null,
        public readonly ?int $propertyListingId = null,
    ) {}

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

        $templateConfig = config("whatsapp.templates.match_ad.{$locale}")
            ?? config('whatsapp.templates.match_ad.es');

        if (empty($templateConfig)) {
            return [];
        }

        return [
            'template' => $templateConfig['name'],
            'language' => $templateConfig['language'],
            'params'   => [],
        ];
    }

    /**
     * @return array{event_type: string, property_listing_id: int|null, property_request_id: int|null}
     */
    public function getWhatsAppLogContext(): array
    {
        return [
            'event_type'          => 'match_ad',
            'property_listing_id' => $this->propertyListingId,
            'property_request_id' => $this->propertyRequestId,
        ];
    }
}
