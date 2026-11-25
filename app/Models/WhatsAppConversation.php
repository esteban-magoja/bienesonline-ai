<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Conversación de la bandeja de WhatsApp con un número (wa_id).
 */
class WhatsAppConversation extends Model
{
    use HasFactory;

    /** Horas en las que Meta permite responder con texto libre después del último mensaje del usuario. */
    public const SERVICE_WINDOW_HOURS = 24;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'wa_id',
        'profile_name',
        'user_id',
        'last_message_at',
        'last_inbound_at',
        'last_message_preview',
        'unread_count',
        'opted_out_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }

    /**
     * Momento en que se cierra la ventana de atención (null si el usuario nunca escribió).
     */
    public function serviceWindowExpiresAt(): ?Carbon
    {
        return $this->last_inbound_at?->copy()->addHours(self::SERVICE_WINDOW_HOURS);
    }

    /**
     * Dentro de la ventana se puede responder con texto libre; fuera, solo con un template aprobado.
     */
    public function isServiceWindowOpen(): bool
    {
        $expiresAt = $this->serviceWindowExpiresAt();

        return $expiresAt !== null && $expiresAt->isFuture();
    }

    /**
     * Nombre para mostrar: usuario vinculado, nombre de perfil de WhatsApp o número.
     */
    public function displayName(): string
    {
        return $this->user?->name ?: ($this->profile_name ?: '+'.$this->wa_id);
    }
}
