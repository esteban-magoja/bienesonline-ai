<?php

namespace App\Http\Controllers\Billing;

use App\Jobs\ProcessPayPalWebhook;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\PayPalClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\UniqueConstraintViolationException;

class PayPalWebhookController extends Controller
{
    public function __construct(
        private readonly PayPalClient $client,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->client->verifyWebhookSignature($request, $payload)) {
            Log::warning('Rejected PayPal webhook with an invalid signature.');

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)
            || blank($event['id'] ?? null)
            || blank($event['event_type'] ?? null)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $eventId = (string) $event['id'];

        try {
            $webhookEvent = BillingWebhookEvent::firstOrCreate(
                [
                    'provider' => 'paypal',
                    'external_event_id' => $eventId,
                ],
                [
                    'event_type' => $event['event_type'],
                    'resource_type' => $event['resource_type'] ?? null,
                    'payload' => $event,
                    'status' => 'pending',
                ],
            );
        } catch (UniqueConstraintViolationException) {
            $webhookEvent = BillingWebhookEvent::query()
                ->where('provider', 'paypal')
                ->where('external_event_id', $eventId)
                ->firstOrFail();
        }

        if ($webhookEvent->wasRecentlyCreated || $webhookEvent->status !== 'processed') {
            ProcessPayPalWebhook::dispatch($webhookEvent->id);
        }

        return response()->json(['message' => 'Webhook received.']);
    }
}
