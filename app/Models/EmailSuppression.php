<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Direcciones dadas de baja de los correos enviados desde el admin (usuarios y contactos).
 */
class EmailSuppression extends Model
{
    public const REASON_UNSUBSCRIBED = 'unsubscribed';

    public const REASON_BOUNCED = 'bounced';

    public const REASON_MANUAL = 'manual';

    /**
     * @var array<string, string>
     */
    public const REASON_LABELS = [
        self::REASON_BOUNCED => 'Rebotó (no existe)',
        self::REASON_UNSUBSCRIBED => 'Se dio de baja',
        self::REASON_MANUAL => 'Agregada a mano',
    ];

    protected $fillable = [
        'email',
        'reason',
    ];

    public static function isSuppressed(string $email): bool
    {
        return static::where('email', Str::lower(trim($email)))->exists();
    }

    public static function suppress(string $email, string $reason = self::REASON_UNSUBSCRIBED): self
    {
        return static::firstOrCreate(['email' => Str::lower(trim($email))], ['reason' => $reason]);
    }

    public static function reasonLabel(?string $reason): string
    {
        return self::REASON_LABELS[$reason] ?? (string) $reason;
    }
}
