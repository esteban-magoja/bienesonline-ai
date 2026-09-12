<?php

namespace Wave\Http\Livewire\Billing;

use App\Exceptions\PayPalException;
use App\Models\BillingCheckoutAttempt;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Component;
use Stripe\StripeClient;
use App\Services\Billing\PayPalSubscriptionService;
use App\Services\Billing\SubscriptionLifecycleService;
use Wave\Actions\Billing\Paddle\AddSubscriptionIdFromTransaction;
use Wave\Plan;
use Wave\Subscription;
use Throwable;

class Checkout extends Component
{
    public $billing_cycle_available = 'month'; // month, year, or both;

    public $billing_cycle_selected = 'month';

    public $billing_provider;

    public $paddle_url;

    public $change = false;

    public $userSubscription = null;

    public $userPlan = null;

    public function mount(): void
    {
        $this->billing_provider = config('wave.billing_provider', 'stripe');
        $this->paddle_url = (config('wave.paddle.env') == 'sandbox') ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
        $this->updateCycleBasedOnPlans();

        if ($this->change) {
            // if we are changing the user plan as opposecd to checking out the first time.
            $this->userSubscription = auth()->user()->subscription;
            $this->userPlan = auth()->user()->subscription->plan;
        }
    }

    public function redirectToStripeCheckout(Plan $plan)
    {
        abort_unless(in_array('stripe', config('wave.billing_providers', ['stripe']), true), 404);
        abort_unless(! auth()->user()->hasActiveSubscription(), 403);
        abort_unless($plan->active, 404);

        $this->billing_cycle_selected = in_array($this->billing_cycle_selected, ['month', 'year'], true)
            ? $this->billing_cycle_selected
            : 'month';

        $stripe = new StripeClient(config('wave.stripe.secret_key'));

        $price_id = $plan->externalPriceId('stripe', $this->billing_cycle_selected);

        if (blank($price_id)) {
            throw new \InvalidArgumentException('The selected plan is not configured for Stripe.');
        }

        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => [[
                'price' => $price_id,
                'quantity' => 1,
            ]],
            'metadata' => [
                'billable_type' => 'user',
                'billable_id' => auth()->user()->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $this->billing_cycle_selected,
            ],
            'subscription_data' => [
                'metadata' => [
                    'billable_type' => 'user',
                    'billable_id' => auth()->user()->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $this->billing_cycle_selected,
                ],
            ],
            'allow_promotion_codes' => true,
            'mode' => 'subscription',
            'success_url' => url('subscription/welcome'),
            'cancel_url' => url('settings/subscription'),
        ]);

        return redirect()->to($checkout_session->url);
    }

    /**
     * @return array{attempt_id: int, custom_id: string, paypal_plan_id: string}
     */
    public function createPayPalAttempt(
        PayPalSubscriptionService $subscriptionService,
        int $planId,
        ?string $cycle = null,
    ): array {
        abort_unless(in_array('paypal', config('wave.billing_providers', []), true), 404);
        abort_unless(! auth()->user()->hasActiveSubscription(), 403);

        $plan = Plan::query()->whereKey($planId)->where('active', true)->firstOrFail();
        $attempt = $subscriptionService->createAttempt(
            auth()->user(),
            $plan,
            $cycle ?? $this->billing_cycle_selected,
        );

        return [
            'attempt_id' => $attempt->id,
            'custom_id' => $attempt->metadata['custom_id'],
            'paypal_plan_id' => $attempt->metadata['paypal_plan_id'],
        ];
    }

    public function completePayPalSubscription(
        PayPalSubscriptionService $subscriptionService,
        int $attemptId,
        string $subscriptionId,
        ?string $payerId = null,
    ): bool {
        $attempt = BillingCheckoutAttempt::query()
            ->whereKey($attemptId)
            ->where('user_id', auth()->id())
            ->where('provider', 'paypal')
            ->firstOrFail();

        try {
            $subscription = $subscriptionService->completeCheckout($attempt, $subscriptionId, $payerId);
        } catch (PayPalException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return false;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('No fue posible confirmar la suscripción de PayPal. Inténtalo nuevamente.')
                ->danger()
                ->send();

            return false;
        }

        return $subscription !== null;
    }

    public function retryPayPalSubscription(
        PayPalSubscriptionService $subscriptionService,
        int $attemptId,
        string $subscriptionId,
        ?string $payerId = null,
    ): bool {
        $attempt = BillingCheckoutAttempt::query()
            ->whereKey($attemptId)
            ->where('user_id', auth()->id())
            ->where('provider', 'paypal')
            ->firstOrFail();

        try {
            return $subscriptionService->completeCheckout($attempt, $subscriptionId, $payerId) !== null;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function updateCycleBasedOnPlans(): void
    {
        $plans = Plan::query()->where('active', true)->with('billingPrices')->get();
        $hasMonthly = false;
        $hasYearly = false;
        foreach ($plans as $plan) {
            foreach (config('wave.billing_providers', ['stripe']) as $provider) {
                if (! blank($plan->externalPriceId($provider, 'month'))) {
                    $hasMonthly = true;
                }
                if (! blank($plan->externalPriceId($provider, 'year'))) {
                    $hasYearly = true;
                }
            }
        }
        if ($hasMonthly && $hasYearly) {
            $this->billing_cycle_available = 'both';
        } elseif ($hasMonthly) {
            $this->billing_cycle_available = 'month';
        } elseif ($hasYearly) {
            $this->billing_cycle_available = 'year';
            $this->billing_cycle_selected = 'year';
        }
    }

    #[On('savePaddleSubscription')]
    public function savePaddleSubscription($transactionId)
    {
        abort_unless(auth()->user()->hasActiveSubscription(), 403);

        $subscription = app(AddSubscriptionIdFromTransaction::class)($transactionId);
        if (! is_null($subscription)) {
            return redirect()->to('/subscription/welcome');
        }

        $this->js('closeLoader()');
        Notification::make()
            ->title('Unable to obtain subscription information from payment provider.')
            ->danger()
            ->send();
    }

    #[On('verifyPaddleTransaction')]
    public function verifyPaddleTransaction($transactionId)
    {
        abort_unless(! auth()->user()->hasActiveSubscription(), 403);


        $transaction = null;

        $response = Http::withToken(config('wave.paddle.api_key'))->get($this->paddle_url.'/transactions/'.$transactionId);

        if ($response->successful()) {
            $resBody = json_decode($response->body());
            if (isset($resBody->data->status) && ($resBody->data->status == 'paid' || $resBody->data->status == 'completed')) {
                $transaction = $resBody->data;
            }
        }

        if ($transaction) {
            // Proceed with processing the transaction

            $user = auth()->user();

            if ($this->billing_cycle_selected == 'month') {
                $plan = Plan::where('monthly_price_id', $transaction->items[0]->price->id)->first();
            } else {
                $plan = Plan::where('yearly_price_id', $transaction->items[0]->price->id)->first();
            }

            if (! isset($plan->id)) {
                $this->js('Paddle.Checkout.close()');
                Notification::make()
                    ->title('Plan Price ID not found. Something went wrong during the checkout process')
                    ->success()
                    ->send();

                return;
            }

            app(SubscriptionLifecycleService::class)->createOrActivate([
                'billable_type' => 'user',
                'billable_id' => auth()->user()->id,
                'plan_id' => $plan->id,
                'vendor_slug' => 'paddle',
                'vendor_transaction_id' => $transactionId,
                'vendor_customer_id' => $transaction->customer_id,
                'vendor_subscription_id' => $transaction->subscription_id,
                'cycle' => $this->billing_cycle_selected,
                'status' => Subscription::STATUS_ACTIVE,
                'seats' => 1,
            ]);

            $this->js('savePaddleSubscription("'.$transactionId.'")');

        } else {
            $this->js('Paddle.Checkout.close()');
            Notification::make()
                ->title('Error processing the transaction. Please try again.')
                ->danger()
                ->send();
        }

        // if we got here something went wrong and we need to let the user know.

    }

    public function switchPlan(Plan $plan)
    {
        abort_unless(auth()->user()->hasActiveSubscription(), 403);

        $subscription = auth()->user()->subscription;

        $price_id = ($this->billing_cycle_selected == 'month') ? $plan->monthly_price_id : $plan->yearly_price_id ?? null;

        $response = Http::withToken(config('wave.paddle.api_key'))->patch(
            $this->paddle_url.'/subscriptions/'.$subscription->vendor_subscription_id,
            [
                'items' => [
                    [
                        'price_id' => $price_id,
                        'quantity' => 1,
                    ],
                ],
                'proration_billing_mode' => 'prorated_immediately',
            ]
        );

        if ($response->successful()) {
            $subscription->plan_id = $plan->id;
            $subscription->cycle = $this->billing_cycle_selected;
            $subscription->save();
            $subscription->user->switchPlans($plan);

            return redirect()->to('/settings/subscription')->with(['update' => true]);
        }
    }

    public function render()
    {
        return view('wave::livewire.billing.checkout', [
            'plans' => Plan::where('active', 1)->with(['role', 'billingPrices'])->get(),
            'paypalEnabled' => in_array('paypal', config('wave.billing_providers', []), true)
                && filled(config('wave.paypal.client_id'))
                && filled(config('wave.paypal.client_secret')),
        ]);
    }
}
