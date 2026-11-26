<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRegistered implements ShouldQueue
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        // Enviar verificación de teléfono por WhatsApp si el usuario aceptó y tiene móvil
        if ($user->whatsapp_opt_in && !empty($user->movil)) {
            $user->sendPhoneVerification();
        }
    }
}
