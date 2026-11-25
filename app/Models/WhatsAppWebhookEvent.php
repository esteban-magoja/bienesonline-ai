<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * POST recibido en /webhook/whatsapp, guardado tal como llegó y procesado en la cola.
 */
class WhatsAppWebhookEvent extends Model
{
    use MassPrunable;

    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'payload',
        'status',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Los avisos procesados se borran a los 30 días (model:prune diario en routes/console.php).
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('status', 'processed')
            ->where('created_at', '<', now()->subDays(30));
    }
}
