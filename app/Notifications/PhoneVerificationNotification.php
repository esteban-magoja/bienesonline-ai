<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Channels\WhatsAppChannel;

class PhoneVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    /**
     * Devuelve el template de verificación con sus parámetros.
     *
     * Estructura del template en Meta Business Suite:
     *   Body {{1}} → nombre del usuario
     *   Body {{2}} → texto descriptivo (ej: "número de teléfono")
     *   Botón URL dinámico {{1}} → URL completa de verificación
     */
    public function toWhatsApp(mixed $notifiable): array
    {
        $locale = $notifiable->locale ?? app()->getLocale();
        $locale = in_array($locale, ['es', 'en']) ? $locale : 'es';

        $templateConfig = config("whatsapp.templates.verify.{$locale}");

        $phoneLabel = $locale === 'en' ? 'phone number' : 'número de teléfono';

        $verificationUrl = $notifiable->movil_verification_token;

        return [
            'template'      => $templateConfig['name'],
            'language'      => $templateConfig['language'],
            'params'        => [
                $notifiable->name,
                $phoneLabel,
            ],
            'button_params' => [
                0 => $verificationUrl,
            ],
        ];
    }
}
