<?php

declare(strict_types=1);

use App\Services\Billing\PayPalClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('retries transient PayPal requests before succeeding', function (): void {
    config([
        'wave.paypal.mode' => 'sandbox',
        'wave.paypal.client_id' => 'test-client-id',
        'wave.paypal.client_secret' => 'test-client-secret',
    ]);

    Cache::forget('billing.paypal.access_token.sandbox.'.sha1('test-client-id'));

    $oauthUrl = 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
    $subscriptionUrl = 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions';

    Http::preventStrayRequests();
    Http::fake([
        $oauthUrl => Http::sequence()
            ->pushStatus(500)
            ->pushStatus(503)
            ->push([
                'access_token' => 'test-access-token',
                'expires_in' => 300,
            ]),
        $subscriptionUrl => Http::sequence()
            ->pushStatus(500)
            ->pushStatus(502)
            ->push([
                'id' => 'I-TEST-SUBSCRIPTION',
                'status' => 'ACTIVE',
            ]),
    ]);

    $subscription = app(PayPalClient::class)->createSubscription(
        'P-TEST-PLAN',
        'checkout:test',
        'https://example.test/paypal/success',
        'https://example.test/paypal/cancel',
        'request-test-123',
    );

    expect($subscription['id'])->toBe('I-TEST-SUBSCRIPTION');

    $oauthRequests = Http::recorded(
        fn (Request $request, Response $response): bool => $request->url() === $oauthUrl,
    );
    $subscriptionRequests = Http::recorded(
        fn (Request $request, Response $response): bool => $request->url() === $subscriptionUrl,
    );

    expect($oauthRequests)->toHaveCount(3)
        ->and($subscriptionRequests)->toHaveCount(3);
});
