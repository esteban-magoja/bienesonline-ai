<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Destinatario de un envío, con su estado individual.
 */
class EmailSendRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * Estados que cuentan como "ya recibió (o va a recibir) este mensaje".
     *
     * @var list<string>
     */
    public const DELIVERED_OR_QUEUED = [self::STATUS_PENDING, self::STATUS_SENDING, self::STATUS_SENT];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_SENDING => 'Enviando',
        self::STATUS_SENT => 'Enviado',
        self::STATUS_FAILED => 'Fallido',
        self::STATUS_SKIPPED => 'Omitido',
    ];

    /** @var array<string, string> */
    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'gray',
        self::STATUS_SENDING => 'info',
        self::STATUS_SENT => 'success',
        self::STATUS_FAILED => 'danger',
        self::STATUS_SKIPPED => 'warning',
    ];

    protected $fillable = [
        'email_send_id',
        'email',
        'name',
        'agency',
        'user_id',
        'email_contact_id',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function emailSend(): BelongsTo
    {
        return $this->belongsTo(EmailSend::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(EmailContact::class, 'email_contact_id');
    }
}
