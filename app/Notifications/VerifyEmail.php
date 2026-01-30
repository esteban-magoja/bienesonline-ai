<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends VerifyEmailBase
{
    /**
     * Get the verify email notification mail message for the given URL.
     *
     * @param  string  $url
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('Verificar Dirección de Email')
            ->line('Por favor haz clic en el botón de abajo para verificar tu dirección de email.')
            ->action('Verificar Dirección de Email', $url)
            ->line('Si no creaste una cuenta, no necesitas hacer nada más.');
    }
}
