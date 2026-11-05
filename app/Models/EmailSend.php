<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Envío de un mensaje a un grupo de destinatarios (con copia del asunto y cuerpo enviados).
 */
class EmailSend extends Model
{
    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'email_template_id',
        'subject',
        'body',
        'status',
        'sent_by',
        'finished_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_SENDING,
    ];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailSendRecipient::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
