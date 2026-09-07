<?php

namespace App\Services\Billing;

use App\Exceptions\PayPalException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

class PayPalClient
{
    private const TOKEN_CACHE_KEY = 'billing.paypal.access_token';

    public function createSubscription(
        string $planId,
        string $customId,
        string $returnUrl,
        string $cancelUrl,
        ?string $requestId = null,
    ): array {
        return $this->request()
            ->withHeaders([
                'Prefer' => 'return=representation',
                'PayPal-Request-Id' => $requestId ?? (string) Str::uuid(),
            ])
            ->post('/v1/billing/subscriptions', [
                'plan_id' => $planId,
                'custom_id' => $customId,
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'es-ES',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->request()
            ->get('/v1/billing/subscriptions/'.$subscriptionId)
            ->throw()
            ->json();
    }

    public function cancelSubscription(string $subscriptionId, string $reason, ?string $requestId = null): void
    {
        $request = $this->request();

        if (filled($requestId)) {
            $request = $request->withHeader('PayPal-Request-Id', $requestId);
        }

        $request
            ->post('/v1/billing/subscriptions/'.$subscriptionId.'/cancel', [
                'reason' => $reason,
            ])
            ->throw();
    }

    public function suspendSubscription(string $subscriptionId, string $reason, ?string $requestId = null): void
    {
        $request = $this->request();

        if (filled($requestId)) {
            $request = $request->withHeader('PayPal-Request-Id', $requestId);
        }

        $request
            ->post('/v1/billing/subscriptions/'.$subscriptionId.'/suspend', [
                'reason' => $reason,
            ])
            ->throw();
    }

    public function activateSubscription(string $subscriptionId, string $reason, ?string $requestId = null): void
    {
        $request = $this->request();

        if (filled($requestId)) {
            $request = $request->withHeader('PayPal-Request-Id', $requestId);
        }

        $request
            ->post('/v1/billing/subscriptions/'.$subscriptionId.'/activate', [
                'reason' => $reason,
            ])
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionTransactions(
        string $subscriptionId,
        string $startTime,
        string $endTime,
    ): array {
        return $this->request()
            ->get('/v1/billing/subscriptions/'.$subscriptionId.'/transactions', [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ])
            ->throw()
            ->json();
    }

    public function verifyWebhookSignature(Request $request, string $payload): bool
    {
        $verificationPayload = [
            'transmission_id' => $request->header('paypal-transmission-id'),
            'transmission_time' => $request->header('paypal-transmission-time'),
            'cert_url' => $request->header('paypal-cert-url'),
            'auth_algo' => $request->header('paypal-auth-algo'),
            'transmission_sig' => $request->header('paypal-transmission-sig'),
            'webhook_id' => config('wave.paypal.webhook_id'),
        ];

        if (in_array(null, $verificationPayload, true) || in_array('', $verificationPayload, true)) {
            return false;
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return false;
        }

        try {
            $response = $this->authenticatedRequest()
                ->post('/v1/notifications/verify-webhook-signature', [
                    ...$verificationPayload,
                    'webhook_event' => $event,
                ])
                ->throw();

            return $response->json('verification_status') === 'SUCCESS';
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function request(): PendingRequest
    {
        return $this->authenticatedRequest()
            ->throw();
    }

    private function authenticatedRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->timeout(10)
            ->connectTimeout(5)
            ->retry([200, 500, 1000], when: $this->shouldRetry(...), throw: false);
    }

    private function accessToken(): string
    {
        $clientId = config('wave.paypal.client_id');
        $cacheKey = self::TOKEN_CACHE_KEY.'.'.config('wave.paypal.mode').'.'.sha1((string) $clientId);
        $token = Cache::get($cacheKey);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $clientSecret = config('wave.paypal.client_secret');

        if (blank($clientId) || blank($clientSecret)) {
            throw new PayPalException('PayPal credentials are not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->retry([200, 500, 1000], when: $this->shouldRetry(...), throw: false)
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new PayPalException('Unable to obtain a PayPal access token.', [
                'status' => $response->status(),
            ]);
        }

        $expiresIn = max((int) $response->json('expires_in', 300) - 60, 60);
        $accessToken = (string) $response->json('access_token');

        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));

        return $accessToken;
    }

    private function baseUrl(): string
    {
        return config('wave.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response?->status();

        return $status === 429 || ($status !== null && $status >= 500);
    }
}
