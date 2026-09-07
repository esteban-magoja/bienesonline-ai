<?php

declare(strict_types=1);

use App\Models\User;
use App\Exceptions\PayPalException;
use App\Models\BillingCheckoutAttempt;
use App\Models\BillingPlanPrice;
use App\Services\Billing\PayPalClient;
use App\Services\Billing\PayPalSubscriptionService;
use App\Services\Billing\PremiumRoleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Wave\Plan;
use Wave\Subscription;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->premiumRole = Role::query()->firstOrCreate([
        'name' => 'premium',
        'guard_name' => 'web',
    ]);
    $this->userRole = Role::query()->firstOrCreate([
        'name' => 'registered',
        'guard_name' => 'web',
    ]);
    Role::query()->firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);
    $this->plan = Plan::query()->firstOrCreate(
        ['name' => 'Premium'],
        [
            'description' => 'Premium test plan',
            'features' => 'Test feature',
            'role_id' => $this->premiumRole->id,
            'active' => true,
            'default' => true,
            'monthly_price' => '10',
            'yearly_price' => '99',
        ],
    );
});

function billingUser(): User
{
    $user = User::factory()->create();
    $user->syncRoles(['registered']);

    return $user->fresh();
}

function billingSubscription(User $user, array $attributes = []): Subscription
{
    $plan = Plan::query()->where('name', 'Premium')->firstOrFail();

    return Subscription::query()->create(array_merge([
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.fake()->unique()->bothify('??????????'),
        'status' => Subscription::STATUS_ACTIVE,
        'cycle' => 'month',
        'seats' => 1,
    ], $attributes));
}

it('grants premium for an active subscription', function (): void {
    $user = billingUser();
    $subscription = billingSubscription($user);

    app(PremiumRoleService::class)->sync($user);

    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($user->fresh()->hasRole('premium'))->toBeTrue()
        ->and($user->fresh()->hasActiveSubscription())->toBeTrue();
});

it('removes premium after the last active subscription is canceled', function (): void {
    $user = billingUser();
    $subscription = billingSubscription($user);

    app(PremiumRoleService::class)->sync($user);
    $subscription->cancel();

    expect($subscription->fresh()->status)->toBe(Subscription::STATUS_CANCELED)
        ->and($user->fresh()->hasRole('premium'))->toBeFalse()
        ->and($user->fresh()->hasActiveSubscription())->toBeFalse();
});

it('keeps premium while another provider subscription remains active', function (): void {
    $user = billingUser();
    $stripeSubscription = billingSubscription($user);
    billingSubscription($user, [
        'vendor_slug' => 'paypal',
        'vendor_subscription_id' => 'I-'.fake()->unique()->bothify('??????????'),
    ]);

    app(PremiumRoleService::class)->sync($user);
    $stripeSubscription->cancel();

    expect($user->fresh()->hasRole('premium'))->toBeTrue()
        ->and($user->fresh()->hasActiveSubscription())->toBeTrue();
});

it('removes only premium and preserves administrator access', function (): void {
    $user = billingUser();
    $user->assignRole('admin');
    $subscription = billingSubscription($user);

    app(PremiumRoleService::class)->sync($user);
    $subscription->suspend();

    $user = $user->fresh();

    expect($user->hasRole('premium'))->toBeFalse()
        ->and($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasPremiumAccess())->toBeTrue();
});

it('removes a manually assigned premium role when its subscription is canceled', function (): void {
    $user = billingUser();
    $user->assignRole('premium');
    $subscription = billingSubscription($user);

    $subscription->cancel();

    expect($user->fresh()->hasRole('premium'))->toBeFalse();
});

it('uses the definitive premium prices', function (): void {
    expect((string) $this->plan->fresh()->monthly_price)->toBe('10')
        ->and((string) $this->plan->fresh()->yearly_price)->toBe('99');
});

it('creates one reusable PayPal checkout attempt for a plan and cycle', function (): void {
    $user = billingUser();
    BillingPlanPrice::query()->create([
        'plan_id' => $this->plan->id,
        'provider' => 'paypal',
        'cycle' => 'month',
        'external_id' => 'P-PREMIUM-MONTHLY',
        'amount' => '10.00',
        'currency' => 'USD',
        'active' => true,
    ]);

    $service = app(PayPalSubscriptionService::class);
    $firstAttempt = $service->createAttempt($user, $this->plan, 'month');
    $secondAttempt = $service->createAttempt($user, $this->plan, 'month');

    expect($secondAttempt->id)->toBe($firstAttempt->id)
        ->and($firstAttempt->metadata['paypal_plan_id'])->toBe('P-PREMIUM-MONTHLY')
        ->and($firstAttempt->idempotency_key)->not->toBeEmpty();
});

it('activates a PayPal subscription only when the remote subscription matches the attempt', function (): void {
    $user = billingUser();
    BillingPlanPrice::query()->create([
        'plan_id' => $this->plan->id,
        'provider' => 'paypal',
        'cycle' => 'month',
        'external_id' => 'P-PREMIUM-MONTHLY-VALID',
        'amount' => '10.00',
        'currency' => 'USD',
        'active' => true,
    ]);
    $attempt = app(PayPalSubscriptionService::class)->createAttempt($user, $this->plan, 'month');
    $remoteSubscription = [
        'id' => 'I-VALID',
        'status' => 'ACTIVE',
        'plan_id' => $attempt->metadata['paypal_plan_id'],
        'custom_id' => $attempt->metadata['custom_id'],
        'subscriber' => [
            'payer_id' => 'PAYER-VALID',
            'email_address' => $user->email,
        ],
        'billing_info' => [
            'next_billing_time' => now()->addMonth()->toIso8601String(),
        ],
    ];
    $client = \Mockery::mock(PayPalClient::class);
    $client->expects('getSubscription')->once()->with('I-VALID')->andReturn($remoteSubscription);
    $this->app->instance(PayPalClient::class, $client);

    $subscription = app(PayPalSubscriptionService::class)->completeCheckout(
        $attempt,
        'I-VALID',
        'PAYER-VALID',
    );

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscription->vendor_slug)->toBe('paypal')
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($user->fresh()->hasRole('premium'))->toBeTrue();
});

it('rejects an expired or mismatched PayPal checkout attempt', function (): void {
    $user = billingUser();
    BillingPlanPrice::query()->create([
        'plan_id' => $this->plan->id,
        'provider' => 'paypal',
        'cycle' => 'month',
        'external_id' => 'P-PREMIUM-MONTHLY-EXPIRED',
        'amount' => '10.00',
        'currency' => 'USD',
        'active' => true,
    ]);
    $attempt = app(PayPalSubscriptionService::class)->createAttempt($user, $this->plan, 'month');
    $attempt->update(['expires_at' => now()->subMinute()]);
    $client = \Mockery::mock(PayPalClient::class);
    $this->app->instance(PayPalClient::class, $client);

    expect(fn () => app(PayPalSubscriptionService::class)->completeCheckout($attempt, 'I-EXPIRED'))
        ->toThrow(PayPalException::class);

    $attempt = BillingCheckoutAttempt::query()->create([
        'user_id' => $user->id,
        'plan_id' => $this->plan->id,
        'provider' => 'paypal',
        'cycle' => 'month',
        'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'pending',
        'metadata' => [
            'custom_id' => 'checkout:mismatch',
            'paypal_plan_id' => 'P-PREMIUM-MONTHLY-EXPIRED',
        ],
        'expires_at' => now()->addHour(),
    ]);
    $client->expects('getSubscription')->once()->with('I-MISMATCH')->andReturn([
        'id' => 'I-MISMATCH',
        'status' => 'ACTIVE',
        'plan_id' => $attempt->metadata['paypal_plan_id'],
        'custom_id' => 'checkout:another-attempt',
        'subscriber' => ['payer_id' => 'PAYER'],
    ]);

    expect(fn () => app(PayPalSubscriptionService::class)->completeCheckout($attempt, 'I-MISMATCH'))
        ->toThrow(PayPalException::class);
});

it('activates a PayPal subscription from an out of order activation webhook', function (): void {
    $user = billingUser();
    BillingPlanPrice::query()->create([
        'plan_id' => $this->plan->id,
        'provider' => 'paypal',
        'cycle' => 'month',
        'external_id' => 'P-PREMIUM-MONTHLY-WEBHOOK',
        'amount' => '10.00',
        'currency' => 'USD',
        'active' => true,
    ]);
    $attempt = app(PayPalSubscriptionService::class)->createAttempt($user, $this->plan, 'month');
    $payload = [
        'resource' => [
            'id' => 'I-WEBHOOK',
            'status' => 'ACTIVE',
            'plan_id' => $attempt->metadata['paypal_plan_id'],
            'custom_id' => $attempt->metadata['custom_id'],
            'subscriber' => [
                'payer_id' => 'PAYER-WEBHOOK',
                'email_address' => 'sandbox-buyer@example.test',
            ],
        ],
    ];

    app(PayPalSubscriptionService::class)->processWebhook(
        'BILLING.SUBSCRIPTION.ACTIVATED',
        $payload,
    );

    expect($user->fresh()->hasRole('premium'))->toBeTrue()
        ->and(Subscription::query()
            ->where('vendor_slug', 'paypal')
            ->where('vendor_subscription_id', 'I-WEBHOOK')
            ->where('status', Subscription::STATUS_ACTIVE)
            ->exists())->toBeTrue();
});

it('accepts a sandbox buyer with a different email address', function (): void {
    $user = billingUser();
    BillingPlanPrice::query()->create([
        'plan_id' => $this->plan->id,
        'provider' => 'paypal',
        'cycle' => 'month',
        'external_id' => 'P-PREMIUM-MONTHLY-EMAIL',
        'amount' => '10.00',
        'currency' => 'USD',
        'active' => true,
    ]);
    $attempt = app(PayPalSubscriptionService::class)->createAttempt($user, $this->plan, 'month');

    app(PayPalSubscriptionService::class)->processWebhook(
        'BILLING.SUBSCRIPTION.ACTIVATED',
        [
            'resource' => [
                'id' => 'I-DIFFERENT-BUYER',
                'status' => 'ACTIVE',
                'plan_id' => $attempt->metadata['paypal_plan_id'],
                'custom_id' => $attempt->metadata['custom_id'],
                'subscriber' => [
                    'payer_id' => 'PAYER-DIFFERENT-BUYER',
                    'email_address' => 'different-sandbox-account@example.test',
                ],
            ],
        ],
    );

    expect($user->fresh()->hasActiveSubscription())->toBeTrue()
        ->and($attempt->fresh()->status)->toBe('completed');
});

it('retries a pending PayPal checkout after the remote subscription becomes active', function (): void {
    $user = billingUser();
    BillingPlanPrice::query()->create([
        'plan_id' => $this->plan->id,
        'provider' => 'paypal',
        'cycle' => 'month',
        'external_id' => 'P-PREMIUM-MONTHLY-RETRY',
        'amount' => '10.00',
        'currency' => 'USD',
        'active' => true,
    ]);
    $attempt = app(PayPalSubscriptionService::class)->createAttempt($user, $this->plan, 'month');
    $client = \Mockery::mock(PayPalClient::class);
    $client->expects('getSubscription')->twice()->with('I-RETRY')->andReturn(
        [
            'id' => 'I-RETRY',
            'status' => 'APPROVAL_PENDING',
            'plan_id' => $attempt->metadata['paypal_plan_id'],
            'custom_id' => $attempt->metadata['custom_id'],
            'subscriber' => ['payer_id' => 'PAYER-RETRY'],
        ],
        [
            'id' => 'I-RETRY',
            'status' => 'ACTIVE',
            'plan_id' => $attempt->metadata['paypal_plan_id'],
            'custom_id' => $attempt->metadata['custom_id'],
            'subscriber' => ['payer_id' => 'PAYER-RETRY'],
        ],
    );
    $this->app->instance(PayPalClient::class, $client);
    $service = app(PayPalSubscriptionService::class);

    expect($service->completeCheckout($attempt, 'I-RETRY', 'PAYER-RETRY'))->toBeNull()
        ->and($service->completeCheckout($attempt, 'I-RETRY', 'PAYER-RETRY'))->toBeInstanceOf(Subscription::class)
        ->and($attempt->fresh()->status)->toBe('completed');
});
