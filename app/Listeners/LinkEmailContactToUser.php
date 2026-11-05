<?php

namespace App\Listeners;

use App\Models\EmailContact;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;

/**
 * Si un contacto del admin se registra, lo vincula con su nuevo usuario para unir el historial de correos.
 */
class LinkEmailContactToUser
{
    public function handle(Registered $event): void
    {
        EmailContact::where('email', Str::lower(trim((string) $event->user->email)))
            ->whereNull('user_id')
            ->update(['user_id' => $event->user->getKey()]);
    }
}
