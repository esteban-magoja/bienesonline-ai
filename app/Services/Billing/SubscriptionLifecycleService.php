<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Wave\Subscription;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly PremiumRoleService $premiumRoleService,
    ) {
    }

    /**
     * Create or activate a provider subscription atomically.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createOrActivate(array $attributes): Subscription
    {
        $subscription = $this->database->transaction(function () use (
            $attributes,
        ): Subscription {
            return $this->createOrActivateWithinTransaction($attributes);
        }, attempts: 3);

        $this->premiumRoleService->sync(User::query()->findOrFail($subscription->billable_id));

        return $subscription->fresh(['user']);
    }

    /**
     * Create or activate a subscription while the caller owns the transaction.
     * This lets provider-specific records, such as checkout attempts, commit
     * atomically with the subscription row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createOrActivateWithinTransaction(array $attributes): Subscription
    {
        $provider = (string) ($attributes['vendor_slug'] ?? '');
        $externalId = (string) ($attributes['vendor_subscription_id'] ?? '');
        $billableType = (string) ($attributes['billable_type'] ?? 'user');
        $userId = (int) ($attributes['billable_id'] ?? 0);

        if (blank($provider)
            || blank($externalId)
            || $billableType !== 'user'
            || $userId < 1) {
            throw ValidationException::withMessages([
                'subscription' => 'A provider, external subscription ID, and user billable type are required.',
            ]);
        }

        $user = User::query()->lockForUpdate()->findOrFail($userId);
        $subscription = Subscription::query()
            ->where('vendor_slug', $provider)
            ->where('vendor_subscription_id', $externalId)
            ->lockForUpdate()
            ->first();

        if ($subscription && (int) $subscription->billable_id !== $user->getKey()) {
            throw ValidationException::withMessages([
                'subscription' => 'The provider subscription is already linked to another user.',
            ]);
        }

        $hasDifferentActiveSubscription = Subscription::query()
            ->where('billable_type', 'user')
            ->where('billable_id', $user->getKey())
            ->where('status', Subscription::STATUS_ACTIVE)
            ->when($subscription, fn ($query) => $query->whereKeyNot($subscription->getKey()))
            ->exists();

        if ($hasDifferentActiveSubscription) {
            throw ValidationException::withMessages([
                'subscription' => 'The user already has an active subscription.',
            ]);
        }

        if (! $subscription) {
            return Subscription::create([
                ...$attributes,
                'status' => Subscription::STATUS_ACTIVE,
            ]);
        }

        $subscription->fill([
            ...$attributes,
            'status' => Subscription::STATUS_ACTIVE,
            'cancelled_at' => null,
        ]);
        $subscription->save();

        return $subscription;
    }

    /**
     * Persist provider fields and a normalized state for an existing subscription.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function synchronize(Subscription $subscription, array $attributes): Subscription
    {
        $updatedSubscription = $this->database->transaction(function () use (
            $subscription,
            $attributes,
        ): Subscription {
            User::query()->lockForUpdate()->findOrFail($subscription->billable_id);

            $lockedSubscription = Subscription::query()
                ->lockForUpdate()
                ->findOrFail($subscription->getKey());

            if (($attributes['status'] ?? null) === Subscription::STATUS_ACTIVE
                && Subscription::query()
                    ->where('billable_type', 'user')
                    ->where('billable_id', $lockedSubscription->billable_id)
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->whereKeyNot($lockedSubscription->getKey())
                    ->exists()) {
                throw ValidationException::withMessages([
                    'subscription' => 'The user already has another active subscription.',
                ]);
            }

            $lockedSubscription->fill($attributes);
            $lockedSubscription->save();

            return $lockedSubscription;
        }, attempts: 3);

        $this->premiumRoleService->sync(User::query()->findOrFail($updatedSubscription->billable_id));

        return $updatedSubscription->fresh(['user']);
    }

    public function activate(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, Subscription::STATUS_ACTIVE);
    }

    public function markPastDue(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, Subscription::STATUS_PAST_DUE);
    }

    public function suspend(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, Subscription::STATUS_SUSPENDED);
    }

    public function cancel(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, Subscription::STATUS_CANCELED, [
            'cancelled_at' => $subscription->cancelled_at ?? now(),
        ]);
    }

    public function syncUser(User $user): bool
    {
        return $this->premiumRoleService->sync($user);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(
        Subscription $subscription,
        string $status,
        array $attributes = [],
    ): Subscription {
        $updatedSubscription = $this->database->transaction(function () use (
            $subscription,
            $status,
            $attributes,
        ): Subscription {
            User::query()->lockForUpdate()->findOrFail($subscription->billable_id);

            $lockedSubscription = Subscription::query()
                ->lockForUpdate()
                ->findOrFail($subscription->getKey());

            $lockedSubscription->fill([
                ...$attributes,
                'status' => $status,
            ]);

            if ($status === Subscription::STATUS_ACTIVE) {
                $lockedSubscription->cancelled_at = null;
            }

            $lockedSubscription->save();

            return $lockedSubscription;
        }, attempts: 3);

        $this->premiumRoleService->sync(User::query()->findOrFail($updatedSubscription->billable_id));

        return $updatedSubscription->fresh(['user']);
    }
}
