<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje de la bandeja de WhatsApp: recibido de un usuario (in) o enviado desde el admin (out).
 */
class WhatsAppMessage extends Model
{
    use HasFactory;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    /** Orden de los estados de entrega: un estado nunca retrocede (Meta puede avisarlos desordenados). */
    public const STATUS_RANK = [
        'sent' => 1,
        'delivered' => 2,
        'read' => 3,
    ];

    /** Tipos de mensaje que traen un archivo descargable. */
    public const MEDIA_TYPES = ['image', 'audio', 'video', 'document', 'sticker'];

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'whatsapp_conversation_id',
        'wamid',
        'direction',
        'type',
        'body',
        'template_name',
        'media_id',
        'media_path',
        'media_mime',
        'media_filename',
        'context_wamid',
        'whatsapp_message_log_id',
        'status',
        'error_message',
        'sent_by',
        'payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Aviso automático (template) al que responde este mensaje, si lo hay.
     */
    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessageLog::class, 'whatsapp_message_log_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_IN;
    }

    public function hasMedia(): bool
    {
        return $this->media_path !== null;
    }
}
