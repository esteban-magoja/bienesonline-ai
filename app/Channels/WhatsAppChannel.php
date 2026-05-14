<?php

namespace App\Channels;

use App\Contracts\ProvidesWhatsAppLogContext;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private WhatsAppService $whatsApp) {}

    /**
     * Enviar la notificación via WhatsApp.
     * La notificación debe implementar el método toWhatsApp().
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->movil ?? null;

        if (empty($phone)) {
            $this->writeLog($notifiable, $notification, $phone ?? '', 'no_phone', null);
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (empty($message)) {
            return;
        }

        if (is_array($message)) {
            $result = $this->whatsApp->sendTemplate(
                $phone,
                $message['template'],
                $message['language'],
                $message['params'] ?? [],
                $message['button_params'] ?? [],
            );
        } else {
            $result = $this->whatsApp->sendText($phone, $message);
        }

        $status = $result !== false ? 'sent' : ($this->whatsApp->isEnabled() ? 'failed' : 'disabled');

        $this->writeLog($notifiable, $notification, $phone, $status, $result ?: null, $message);
    }

    /**
     * Persist a log entry for the sent (or attempted) WhatsApp message.
     */
    private function writeLog(
        mixed $notifiable,
        Notification $notification,
        string $phone,
        string $status,
        string|null $messageId,
        mixed $messagePayload = null
    ): void {
        try {
            $context = $notification instanceof ProvidesWhatsAppLogContext
                ? $notification->getWhatsAppLogContext()
                : [];

            $templateName = null;
            $languageCode = null;

            if (is_array($messagePayload)) {
                $templateName = $messagePayload['template'] ?? null;
                $languageCode = $messagePayload['language'] ?? null;
            }

            WhatsAppMessageLog::create([
                'notifiable_type'     => get_class($notifiable),
                'notifiable_id'       => $notifiable->getKey(),
                'phone'               => $phone,
                'notification_class'  => get_class($notification),
                'event_type'          => $context['event_type'] ?? null,
                'template_name'       => $templateName,
                'language_code'       => $languageCode,
                'property_listing_id' => $context['property_listing_id'] ?? null,
                'property_request_id' => $context['property_request_id'] ?? null,
                'status'              => $status,
                'whatsapp_message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WhatsAppChannel: no se pudo registrar el log', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
