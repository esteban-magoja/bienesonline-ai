<?php

namespace App\Jobs;

use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsAppInbox\WhatsAppWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un aviso guardado del webhook de WhatsApp (mensajes entrantes y estados de entrega).
 */
class ProcessWhatsAppWebhook implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public int $timeout = 300;

    public function __construct(public readonly int $webhookEventId)
    {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function uniqueId(): string
    {
        return 'whatsapp-webhook-'.$this->webhookEventId;
    }

    public function handle(WhatsAppWebhookProcessor $processor): void
    {
        $claimed = WhatsAppWebhookEvent::query()
            ->whereKey($this->webhookEventId)
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'failed'])
                    ->orWhere(function ($stale): void {
                        $stale->where('status', 'processing')
                            ->where('updated_at', '<', now()->subMinutes(10));
                    });
            })
            ->update([
                'status' => 'processing',
                'error_message' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        $event = WhatsAppWebhookEvent::findOrFail($this->webhookEventId);

        try {
            $processor->process($event->payload ?? []);

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        WhatsAppWebhookEvent::whereKey($this->webhookEventId)->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage(),
        ]);

        Log::error('WhatsApp: no se pudo procesar un aviso del webhook.', [
            'webhook_event_id' => $this->webhookEventId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
