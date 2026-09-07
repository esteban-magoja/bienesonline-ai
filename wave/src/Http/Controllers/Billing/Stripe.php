<?php

namespace Wave\Http\Controllers\Billing;

use Stripe\StripeClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class Stripe extends Controller
{
    public function redirect_to_customer_portal(): RedirectResponse
    {

        $latest_active_subscription = auth()->user()->latestSubscription();

        abort_unless($latest_active_subscription?->vendor_slug === 'stripe', 404);
        abort_unless(filled($latest_active_subscription->vendor_customer_id), 404);
        $stripe = new StripeClient(config('wave.stripe.secret_key'));

        $billingPortal = $stripe->billingPortal->sessions->create([
            'customer' => $latest_active_subscription->vendor_customer_id,
            'return_url' => route('settings.subscription'),
        ]);

        return redirect()->to($billingPortal->url);

    }
}
