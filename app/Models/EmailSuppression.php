<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Direcciones dadas de baja de los correos enviados desde el admin (usuarios y contactos).
 */
class EmailSuppression extends Model
{
    protected $fillable = [
        'email',
        'reason',
    ];

    public static function isSuppressed(string $email): bool
    {
        return static::where('email', Str::lower(trim($email)))->exists();
    }

    public static function suppress(string $email, string $reason = 'unsubscribed'): self
    {
        return static::firstOrCreate(['email' => Str::lower(trim($email))], ['reason' => $reason]);
    }
}
