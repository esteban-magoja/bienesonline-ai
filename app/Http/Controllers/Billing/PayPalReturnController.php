<?php

namespace App\Http\Controllers\Billing;

use App\Models\BillingCheckoutAttempt;
use App\Services\Billing\PayPalSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\PayPalException;

class PayPalReturnController extends Controller
{
    public function __construct(
        private readonly PayPalSubscriptionService $subscriptionService,
    ) {
    }

    public function show(Request $request, BillingCheckoutAttempt $attempt): RedirectResponse
    {
        abort_unless(
            $attempt->provider === 'paypal'
                && $attempt->user_id === $request->user()->id,
            404,
        );

        $subscriptionId = $request->string('subscription_id')->toString();
        $payerId = $request->string('payer_id')->toString() ?: null;

        if (blank($subscriptionId)) {
            return redirect()->route('settings.subscription')->with('error', 'La aprobación de PayPal fue cancelada.');
        }

        try {
            $subscription = $this->subscriptionService->completeCheckout($attempt, $subscriptionId, $payerId);
        } catch (PayPalException $exception) {
            report($exception);

            return redirect()->route('settings.subscription')->with('error', 'No fue posible confirmar la suscripción de PayPal.');
        }

        return redirect()
            ->route($subscription ? 'subscription.welcome' : 'settings.subscription')
            ->with($subscription ? 'success' : 'error', $subscription
                ? 'La suscripción fue activada correctamente.'
                : 'La suscripción de PayPal todavía está pendiente de confirmación.');
    }
}
