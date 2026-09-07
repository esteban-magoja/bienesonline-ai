<?php

namespace App\Jobs;

use App\Models\BillingWebhookEvent;
use App\Services\Billing\PayPalSubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPayPalWebhook implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public int $timeout = 120;

    public function __construct(public readonly int $webhookEventId)
    {
    }

    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function uniqueId(): string
    {
        return 'paypal-webhook-'.$this->webhookEventId;
    }

    /**
     * Keep retries for the same event from updating its subscription concurrently.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $event = BillingWebhookEvent::query()->find($this->webhookEventId);
        $resource = is_array($event?->payload['resource'] ?? null)
            ? $event->payload['resource']
            : [];
        $subscriptionId = $resource['billing_agreement_id']
            ?? $resource['subscription_id']
            ?? $resource['id']
            ?? $this->webhookEventId;

        return [
            (new WithoutOverlapping('paypal-subscription-'.$subscriptionId))
                ->shared()
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function handle(PayPalSubscriptionService $subscriptionService): void
    {
        $event = BillingWebhookEvent::findOrFail($this->webhookEventId);

        $claimed = BillingWebhookEvent::query()
            ->whereKey($event->getKey())
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

        $event->refresh();

        try {
            $subscriptionService->processWebhook($event->event_type, $event->payload);
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
        BillingWebhookEvent::whereKey($this->webhookEventId)->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage(),
        ]);

        Log::error('PayPal webhook processing failed.', [
            'webhook_event_id' => $this->webhookEventId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
