<?php

namespace App\Services\Billing;

use App\Models\User;
use Wave\Subscription;

class PremiumRoleService
{
    public const PREMIUM_ROLE = 'premium';

    /**
     * Synchronize the Premium role with the user's subscription state.
     *
     * Only the Premium role is touched here. Other roles, including an
     * administrator's role, remain untouched so Filament can continue to
     * manage them manually.
     */
    public function sync(User $user): bool
    {
        $hasActiveSubscription = $user->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->exists();

        if ($hasActiveSubscription) {
            $user->grantPremiumRole();
        } else {
            $user->revokePremiumRole();
        }

        return $hasActiveSubscription;
    }

    public function grant(User $user): void
    {
        $this->sync($user);
    }

    public function revoke(User $user): void
    {
        $this->sync($user);
    }
}
