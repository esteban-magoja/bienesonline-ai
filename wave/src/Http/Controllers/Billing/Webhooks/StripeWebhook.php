<?php

namespace Wave\Http\Controllers\Billing\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\BillingWebhookEvent;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;
use UnexpectedValueException;
use Wave\Plan;
use Wave\Subscription;
use Stripe\Exception\SignatureVerificationException;
use Throwable;

class StripeWebhook extends Controller
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycleService,
        private readonly DatabaseManager $database,
    ) {
    }

    public function handler(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        try {
            $event = Webhook::constructEvent(
                $payload,
                $request->header('Stripe-Signature'),
                config('wave.stripe.webhook_secret'),
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Rejected Stripe webhook.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid webhook.'], 400);
        }

        $eventPayload = json_decode($payload, true);
        $eventId = (string) ($eventPayload['id'] ?? $event->id ?? '');

        if (! is_array($eventPayload) || blank($eventId)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $webhookEvent = $this->recordEvent($eventId, $event->type, $eventPayload);

        if (! $this->claimEvent($webhookEvent)) {
            return response()->json(['message' => 'Webhook already being processed.']);
        }

        try {
            $this->processEvent($event);
            $webhookEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $webhookEvent->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            report($exception);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['message' => 'Webhook received.']);
    }

    private function processEvent(object $event): void
    {
        $object = $event->data->object;

        match ($event->type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->fulfillCheckout($object->id),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->syncStripeSubscription($object),
            'customer.subscription.deleted' => $this->cancelStripeSubscription($object->id),
            default => null,
        };
    }

    private function fulfillCheckout(string $sessionId): void
    {
        Stripe::setApiKey(config('wave.stripe.secret_key'));

        $checkoutSession = Session::retrieve($sessionId);

        if (data_get($checkoutSession, 'payment_status') === 'unpaid'
            || data_get($checkoutSession, 'status') !== 'complete') {
            return;
        }

        $subscriptionId = (string) data_get($checkoutSession, 'subscription', '');
        $billableId = (int) data_get($checkoutSession, 'metadata.billable_id', 0);
        $planId = (int) data_get($checkoutSession, 'metadata.plan_id', 0);
        $cycle = (string) data_get($checkoutSession, 'metadata.billing_cycle', '');

        if (blank($subscriptionId)
            || $billableId < 1
            || $planId < 1
            || ! in_array($cycle, ['month', 'year'], true)) {
            throw new UnexpectedValueException('Stripe checkout metadata is incomplete.');
        }

        $user = User::query()->findOrFail($billableId);
        $plan = Plan::query()->findOrFail($planId);

        $stripeSubscription = StripeSubscription::retrieve($subscriptionId);
        $stripeStatus = (string) ($stripeSubscription->status ?? '');

        if (! in_array($stripeStatus, ['active', 'trialing'], true)) {
            return;
        }

        $stripePriceId = data_get($stripeSubscription, 'items.data.0.price.id')
            ?? data_get($checkoutSession, 'line_items.data.0.price.id');
        $configuredPriceId = $plan->externalPriceId('stripe', $cycle);

        if (filled($configuredPriceId) && $stripePriceId !== $configuredPriceId) {
            throw new UnexpectedValueException('Stripe checkout price does not match the selected plan.');
        }

        $this->lifecycleService->createOrActivate([
            'billable_type' => 'user',
            'billable_id' => $user->getKey(),
            'plan_id' => $plan->getKey(),
            'vendor_slug' => 'stripe',
            'vendor_customer_id' => data_get($checkoutSession, 'customer'),
            'vendor_subscription_id' => $subscriptionId,
            'vendor_product_id' => $stripePriceId,
            'cycle' => $cycle,
            'status' => Subscription::STATUS_ACTIVE,
            'seats' => 1,
        ]);
    }

    private function syncStripeSubscription(object $stripeSubscription): void
    {
        $subscription = Subscription::query()
            ->where('vendor_slug', 'stripe')
            ->where('vendor_subscription_id', $stripeSubscription->id)
            ->first();

        if (! $subscription) {
            return;
        }

        $cycle = data_get($stripeSubscription, 'items.data.0.price.recurring.interval')
            ?? data_get($stripeSubscription, 'plan.interval')
            ?? $subscription->cycle;
        $priceId = data_get($stripeSubscription, 'items.data.0.price.id')
            ?? data_get($stripeSubscription, 'plan.id');
        $plan = $this->planForStripePrice($priceId, $cycle) ?? $subscription->plan;
        $cancelAt = data_get($stripeSubscription, 'cancel_at');
        $attributes = [
            'cycle' => in_array($cycle, ['month', 'year'], true) ? $cycle : $subscription->cycle,
            'vendor_product_id' => $priceId ?: $subscription->vendor_product_id,
            'ends_at' => $cancelAt ? Carbon::createFromTimestamp((int) $cancelAt) : null,
        ];

        if ($plan) {
            $attributes['plan_id'] = $plan->getKey();
        }

        $subscription = $this->lifecycleService->synchronize($subscription, $attributes);
        $status = (string) data_get($stripeSubscription, 'status', '');

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $this->lifecycleService->cancel($subscription);

            return;
        }

        if ($status === 'paused') {
            $this->lifecycleService->suspend($subscription);

            return;
        }

        if (in_array($status, ['active', 'trialing'], true)) {
            $this->lifecycleService->synchronize($subscription, [
                'status' => Subscription::STATUS_ACTIVE,
            ]);

            return;
        }

        $this->lifecycleService->synchronize($subscription, ['status' => $status ?: Subscription::STATUS_PAST_DUE]);
    }

    private function cancelStripeSubscription(string $subscriptionId): void
    {
        $subscription = Subscription::query()
            ->where('vendor_slug', 'stripe')
            ->where('vendor_subscription_id', $subscriptionId)
            ->first();

        if ($subscription) {
            $this->lifecycleService->cancel($subscription);
        }
    }

    private function planForStripePrice(?string $priceId, string $cycle): ?Plan
    {
        if (blank($priceId)) {
            return null;
        }

        return Plan::query()
            ->whereHas('billingPrices', function ($query) use ($priceId, $cycle): void {
                $query->where('provider', 'stripe')
                    ->where('cycle', $cycle)
                    ->where('external_id', $priceId)
                    ->where('active', true);
            })
            ->orWhere(function ($query) use ($priceId, $cycle): void {
                $query->where($cycle === 'year' ? 'yearly_price_id' : 'monthly_price_id', $priceId);
            })
            ->first();
    }

    private function recordEvent(string $eventId, string $eventType, array $payload): BillingWebhookEvent
    {
        try {
            return BillingWebhookEvent::firstOrCreate(
                [
                    'provider' => 'stripe',
                    'external_event_id' => $eventId,
                ],
                [
                    'event_type' => $eventType,
                    'resource_type' => 'stripe.event',
                    'payload' => $payload,
                    'status' => 'pending',
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return BillingWebhookEvent::query()
                ->where('provider', 'stripe')
                ->where('external_event_id', $eventId)
                ->firstOrFail();
        }
    }

    private function claimEvent(BillingWebhookEvent $webhookEvent): bool
    {
        return $this->database->transaction(function () use ($webhookEvent): bool {
            $event = BillingWebhookEvent::query()->lockForUpdate()->findOrFail($webhookEvent->getKey());

            if ($event->status === 'processed') {
                return false;
            }

            if ($event->status === 'processing'
                && $event->updated_at?->isAfter(now()->subMinutes(10))) {
                return false;
            }

            $event->update([
                'status' => 'processing',
                'error_message' => null,
            ]);

            return true;
        }, attempts: 3);
    }
}
