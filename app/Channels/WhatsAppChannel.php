<?php

namespace App\Channels;

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
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (empty($message)) {
            return;
        }

        // $message puede ser un array con template, o un string de texto libre
        if (is_array($message)) {
            $this->whatsApp->sendTemplate(
                $phone,
                $message['template'],
                $message['language'],
                $message['params'] ?? [],
                $message['button_params'] ?? [],
            );
        } else {
            $this->whatsApp->sendText($phone, $message);
        }
    }
}
