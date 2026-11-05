<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Contacto no registrado (posible usuario) para los correos enviados desde el admin.
 */
class EmailContact extends Model
{
    /** @use HasFactory<\Database\Factories\EmailContactFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'agency',
        'email',
        'country',
        'whatsapp',
        'source',
        'notes',
        'user_id',
    ];

    protected static function booted(): void
    {
        // Vincula el contacto con el usuario registrado que tenga el mismo email
        static::saving(function (EmailContact $contact): void {
            if ($contact->isDirty('email') || $contact->user_id === null) {
                $contact->user_id = User::whereRaw('LOWER(email) = ?', [$contact->email])->value('id');
            }
        });
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : Str::lower(trim($value)));
    }

    /**
     * Nombre para mostrar: nombre, inmobiliaria o email.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->name ?: ($this->agency ?: $this->email));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailRecipients(): HasMany
    {
        return $this->hasMany(EmailSendRecipient::class);
    }
}
