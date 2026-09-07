<?php

namespace App\Services\Billing;

use App\Exceptions\PayPalException;
use App\Models\BillingCheckoutAttempt;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Wave\Plan;
use Wave\Subscription;

class PayPalSubscriptionService
{
    private const PROVIDER = 'paypal';

    private const VALID_CYCLES = ['month', 'year'];

    public function __construct(
        private readonly PayPalClient $client,
        private readonly SubscriptionLifecycleService $lifecycleService,
        private readonly DatabaseManager $database,
    ) {
    }

    public function createAttempt(User $user, Plan $plan, string $cycle): BillingCheckoutAttempt
    {
        $this->validateCycle($cycle);

        if (! $plan->active) {
            throw new PayPalException('The selected plan is not active.');
        }

        $priceId = $plan->externalPriceId(self::PROVIDER, $cycle);

        if (blank($priceId)) {
            throw new PayPalException('The selected plan is not configured for PayPal.', [
                'plan_id' => $plan->id,
                'cycle' => $cycle,
            ]);
        }

        return $this->database->transaction(function () use ($user, $plan, $cycle, $priceId): BillingCheckoutAttempt {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->subscriptions()->where('status', Subscription::STATUS_ACTIVE)->exists()) {
                throw new PayPalException('The user already has an active subscription.');
            }

            $existingAttempt = BillingCheckoutAttempt::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('provider', self::PROVIDER)
                ->where('plan_id', $plan->getKey())
                ->where('cycle', $cycle)
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->first();

            if ($existingAttempt) {
                return $existingAttempt;
            }

            $idempotencyKey = (string) Str::uuid();

            return BillingCheckoutAttempt::create([
                'user_id' => $lockedUser->getKey(),
                'plan_id' => $plan->getKey(),
                'provider' => self::PROVIDER,
                'cycle' => $cycle,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'metadata' => [
                    'custom_id' => 'checkout:'.$idempotencyKey,
                    'paypal_plan_id' => $priceId,
                ],
                'expires_at' => now()->addHour(),
            ]);
        }, attempts: 3);
    }

    public function completeCheckout(
        BillingCheckoutAttempt $attempt,
        string $subscriptionId,
        ?string $payerId = null,
    ): ?Subscription {
        $attempt = BillingCheckoutAttempt::query()->findOrFail($attempt->getKey());

        if ($attempt->provider !== self::PROVIDER) {
            throw new PayPalException('The checkout attempt does not belong to PayPal.');
        }

        if ($attempt->status === 'completed' && $attempt->external_id === $subscriptionId) {
            return $this->findSubscription($subscriptionId)
                ?? throw new PayPalException('The completed PayPal subscription is missing locally.');
        }

        if ($attempt->status !== 'pending') {
            throw new PayPalException('The PayPal checkout attempt is no longer available.');
        }

        if ($attempt->expires_at?->isPast()) {
            $attempt->update(['status' => 'expired']);

            throw new PayPalException('The PayPal checkout attempt has expired.');
        }

        if (filled($attempt->external_id) && $attempt->external_id !== $subscriptionId) {
            throw new PayPalException('The PayPal subscription does not match the checkout attempt.');
        }

        $paypalSubscription = $this->client->getSubscription($subscriptionId);
        $this->validateSubscription($attempt, $paypalSubscription, $payerId);

        $status = strtoupper((string) ($paypalSubscription['status'] ?? ''));

        if ($status !== 'ACTIVE') {
            if (in_array($status, ['CANCELLED', 'EXPIRED'], true)) {
                $attempt->update([
                    'external_id' => $subscriptionId,
                    'status' => 'failed',
                ]);
            }

            return null;
        }

        return $this->activateLocalSubscription($attempt, $paypalSubscription, ignoreExpiry: false);
    }

    /**
     * Process a verified PayPal event. Unknown subscriptions are ignored because
     * the webhook endpoint can receive events for subscriptions not created here.
     *
     * @param  array<string, mixed>  $payload
     */
    public function processWebhook(string $eventType, array $payload): void
    {
        $resource = $payload['resource'] ?? [];

        if (! is_array($resource)) {
            return;
        }

        $subscriptionId = $this->subscriptionIdFromEvent($eventType, $resource);

        if (blank($subscriptionId)) {
            return;
        }

        $attempt = $this->findAttempt($subscriptionId, $resource['custom_id'] ?? null);
        $subscription = $this->findSubscription($subscriptionId);

        $needsRemoteReconciliation = in_array($eventType, [
            'PAYMENT.SALE.COMPLETED',
            'BILLING.SUBSCRIPTION.CREATED',
            'BILLING.SUBSCRIPTION.ACTIVATED',
            'BILLING.SUBSCRIPTION.UPDATED',
        ], true);

        if (! $attempt && ! $subscription && $needsRemoteReconciliation) {
            $subscriptionDetails = $this->client->getSubscription($subscriptionId);
            $attempt = $this->findAttempt($subscriptionId, $subscriptionDetails['custom_id'] ?? null);

            if ($attempt) {
                $resource = [...$subscriptionDetails, ...$resource];
            }
        }

        if ($eventType === 'PAYMENT.SALE.COMPLETED') {
            if (! $subscription && $attempt) {
                $subscriptionDetails = $this->client->getSubscription($subscriptionId);

                if (strtoupper((string) ($subscriptionDetails['status'] ?? '')) === 'ACTIVE') {
                    $subscription = $this->activateLocalSubscription($attempt, $subscriptionDetails, ignoreExpiry: true);
                }
            }

            $this->processPaymentCompleted($subscription, $resource);

            return;
        }

        if ($eventType === 'BILLING.SUBSCRIPTION.ACTIVATED'
            || (($eventType === 'BILLING.SUBSCRIPTION.CREATED'
                || $eventType === 'BILLING.SUBSCRIPTION.UPDATED')
                && strtoupper((string) ($resource['status'] ?? '')) === 'ACTIVE')) {
            if ($attempt) {
                $details = $this->subscriptionDetailsForActivation($attempt, $resource, $subscriptionId);
                $this->activateLocalSubscription($attempt, $details, ignoreExpiry: true);
            } elseif ($subscription) {
                $this->syncSubscription($subscription, $resource);
            }

            return;
        }

        if (! $attempt && ! $subscription) {
            return;
        }

        if (! $subscription) {
            if (in_array($resource['status'] ?? null, ['CANCELLED', 'EXPIRED', 'SUSPENDED'], true)) {
                $attempt->update([
                    'external_id' => $subscriptionId,
                    'status' => 'failed',
                ]);
            }

            return;
        }

        match ($eventType) {
            'BILLING.SUBSCRIPTION.SUSPENDED' => $subscription->suspend(),
            'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED' => $subscription->cancel(),
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $subscription->markPastDue(),
            'BILLING.SUBSCRIPTION.UPDATED' => $this->syncSubscription($subscription, $resource),
            'PAYMENT.SALE.DENIED', 'PAYMENT.SALE.REVERSED' => $subscription->markPastDue(),
            default => null,
        };
    }

    private function activateLocalSubscription(
        BillingCheckoutAttempt $attempt,
        array $paypalSubscription,
        bool $ignoreExpiry,
    ): Subscription {
        $subscriptionId = $attempt->external_id;

        if (blank($subscriptionId)) {
            $subscriptionId = $paypalSubscription['id'] ?? null;
        }

        if (blank($subscriptionId)) {
            throw new PayPalException('The PayPal subscription ID is missing.');
        }

        if (filled($paypalSubscription['id'] ?? null)
            && (string) $paypalSubscription['id'] !== (string) $subscriptionId) {
            throw new PayPalException('The PayPal subscription response does not match the requested ID.');
        }

        $this->validateSubscription($attempt, $paypalSubscription);

        $subscription = $this->database->transaction(function () use (
            $attempt,
            $paypalSubscription,
            $subscriptionId,
            $ignoreExpiry,
        ): Subscription {
            User::query()->lockForUpdate()->findOrFail($attempt->user_id);

            $lockedAttempt = BillingCheckoutAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());

            if ($lockedAttempt->provider !== self::PROVIDER) {
                throw new PayPalException('The checkout attempt does not belong to PayPal.');
            }

            if ($lockedAttempt->status === 'completed' && $lockedAttempt->external_id === $subscriptionId) {
                return $this->findSubscription($subscriptionId)
                    ?? throw new PayPalException('The completed PayPal subscription is missing locally.');
            }

            if ($lockedAttempt->status !== 'pending') {
                throw new PayPalException('The PayPal checkout attempt is no longer available.');
            }

            if (! $ignoreExpiry && $lockedAttempt->expires_at?->isPast()) {
                $lockedAttempt->update(['status' => 'expired']);

                throw new PayPalException('The PayPal checkout attempt has expired.');
            }

            if (filled($lockedAttempt->external_id) && $lockedAttempt->external_id !== $subscriptionId) {
                throw new PayPalException('The PayPal subscription does not match the checkout attempt.');
            }

            $subscription = $this->lifecycleService->createOrActivateWithinTransaction([
                'billable_type' => 'user',
                'billable_id' => $lockedAttempt->user_id,
                'plan_id' => $lockedAttempt->plan_id,
                'vendor_slug' => self::PROVIDER,
                'vendor_product_id' => $lockedAttempt->metadata['paypal_plan_id']
                    ?? $paypalSubscription['plan_id']
                    ?? null,
                'vendor_customer_id' => $paypalSubscription['subscriber']['payer_id'] ?? null,
                'vendor_subscription_id' => $subscriptionId,
                'cycle' => $lockedAttempt->cycle,
                'status' => Subscription::STATUS_ACTIVE,
                'seats' => 1,
                ...$this->billingDateAttributes($paypalSubscription),
            ]);

            $lockedAttempt->update([
                'status' => 'completed',
                'external_id' => $subscriptionId,
            ]);

            return $subscription;
        }, attempts: 3);

        $this->lifecycleService->syncUser(User::query()->findOrFail($subscription->billable_id));

        return $subscription;
    }

    /**
     * @param  array<string, mixed>  $paypalSubscription
     */
    private function validateSubscription(
        BillingCheckoutAttempt $attempt,
        array $paypalSubscription,
        ?string $payerId = null,
    ): void {
        $expectedCustomId = (string) ($attempt->metadata['custom_id'] ?? '');
        $actualCustomId = (string) ($paypalSubscription['custom_id'] ?? '');

        if (blank($expectedCustomId) || blank($actualCustomId) || ! hash_equals($expectedCustomId, $actualCustomId)) {
            throw new PayPalException('The PayPal subscription does not match the checkout attempt.');
        }

        $expectedPlanId = (string) ($attempt->metadata['paypal_plan_id'] ?? '');
        $actualPlanId = (string) ($paypalSubscription['plan_id'] ?? '');

        if (blank($expectedPlanId) || blank($actualPlanId) || ! hash_equals($expectedPlanId, $actualPlanId)) {
            throw new PayPalException('The PayPal subscription plan does not match the selected plan.');
        }

        $actualPayerId = (string) ($paypalSubscription['subscriber']['payer_id'] ?? '');

        if (blank($actualPayerId)
            || (filled($payerId) && ! hash_equals($actualPayerId, $payerId))) {
            throw new PayPalException('The PayPal payer does not match the checkout attempt.');
        }

    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    private function subscriptionDetailsForActivation(
        BillingCheckoutAttempt $attempt,
        array $resource,
        string $subscriptionId,
    ): array {
        if (filled($resource['plan_id'] ?? null)
            && filled($resource['custom_id'] ?? null)
            && filled($resource['subscriber']['payer_id'] ?? null)) {
            return [...$resource, 'id' => $resource['id'] ?? $subscriptionId];
        }

        return $this->client->getSubscription($subscriptionId);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function processPaymentCompleted(?Subscription $subscription, array $resource): void
    {
        if (! $subscription) {
            return;
        }

        $subscription->vendor_transaction_id = $resource['id'] ?? $subscription->vendor_transaction_id;
        $subscription->last_payment_at = $resource['update_time']
            ?? $resource['create_time']
            ?? $subscription->last_payment_at;
        $subscription->save();
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function syncSubscription(Subscription $subscription, array $resource): void
    {
        $status = strtoupper((string) ($resource['status'] ?? ''));

        if ($status === 'SUSPENDED') {
            $subscription->suspend();

            return;
        }

        if (in_array($status, ['CANCELLED', 'EXPIRED'], true)) {
            $subscription->cancel();

            return;
        }

        if ($status === 'ACTIVE') {
            $this->lifecycleService->synchronize($subscription, [
                'vendor_customer_id' => $resource['subscriber']['payer_id']
                    ?? $subscription->vendor_customer_id,
                'status' => Subscription::STATUS_ACTIVE,
                'cancelled_at' => null,
                ...$this->billingDateAttributes($resource),
            ]);

            return;
        }

        if ($status !== '') {
            $subscription->markPastDue();
        }
    }

    /**
     * @param  array<string, mixed>  $paypalSubscription
     * @return array<string, string>
     */
    private function billingDateAttributes(array $paypalSubscription): array
    {
        $billingInfo = $paypalSubscription['billing_info'] ?? [];
        $attributes = [];

        if (filled($billingInfo['last_payment']['time'] ?? null)) {
            $attributes['last_payment_at'] = $billingInfo['last_payment']['time'];
        }

        if (filled($billingInfo['next_billing_time'] ?? null)) {
            $attributes['next_payment_at'] = $billingInfo['next_billing_time'];
        }

        return $attributes;
    }

    private function findSubscription(string $subscriptionId): ?Subscription
    {
        return Subscription::query()
            ->where('vendor_slug', self::PROVIDER)
            ->where('vendor_subscription_id', $subscriptionId)
            ->first();
    }

    private function findAttempt(string $subscriptionId, mixed $customId): ?BillingCheckoutAttempt
    {
        $attempt = BillingCheckoutAttempt::query()
            ->where('provider', self::PROVIDER)
            ->where('external_id', $subscriptionId)
            ->first();

        if ($attempt || blank($customId)) {
            return $attempt;
        }

        return BillingCheckoutAttempt::query()
            ->where('provider', self::PROVIDER)
            ->whereJsonContains('metadata->custom_id', (string) $customId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function subscriptionIdFromEvent(string $eventType, array $resource): ?string
    {
        if (str_starts_with($eventType, 'PAYMENT.SALE.')) {
            return $resource['billing_agreement_id'] ?? $resource['subscription_id'] ?? null;
        }

        return $resource['id'] ?? null;
    }

    private function validateCycle(string $cycle): void
    {
        if (! in_array($cycle, self::VALID_CYCLES, true)) {
            throw new PayPalException('Invalid billing cycle.');
        }
    }
}
