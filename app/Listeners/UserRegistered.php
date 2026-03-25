<?php

namespace App\Listeners;

use App\Notifications\PhoneVerificationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

class UserRegistered implements ShouldQueue
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        // Enviar verificación de teléfono por WhatsApp si el usuario aceptó y tiene móvil
        if ($user->whatsapp_opt_in && !empty($user->movil)) {
            // Generar token de verificación si no existe
            if (empty($user->movil_verification_token)) {
                $user->movil_verification_token = Str::random(64);
                $user->saveQuietly();
            }

            $user->notify(new PhoneVerificationNotification());
        }
    }
}
