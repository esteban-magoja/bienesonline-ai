<?php

namespace Wave\Http\Livewire\Billing;

use App\Services\Billing\PayPalClient;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Wave\Actions\Billing\Paddle\AddSubscriptionIdFromTransaction;
use Wave\Subscription;

class Update extends Component
{
    public $update_url;

    public $cancel_url;

    public $paddle_url;

    public $cancellation_scheduled = false;

    public $subscription_ends_at;

    public $error_retrieving_data = false;

    public $provider;

    public $subscription;

    public function mount(): void
    {
        $this->subscription = auth()->user()->subscription;
        $this->provider = $this->subscription?->vendor_slug ?? config('wave.billing_provider');

        if ($this->provider == 'paddle' && auth()->user()->hasActiveSubscription()) {
            $subscription = $this->subscription;

            if (is_null($this->subscription->vendor_subscription_id)) {
                // If we did not obtain the user subscription id, try to get it again.
                $subscription = app(AddSubscriptionIdFromTransaction::class)($this->subscription->vendor_transaction_id);
                if (is_null($subscription)) {
                    $this->error_retrieving_data = true;

                    return;
                }
            }

            $this->paddle_url = (config('wave.paddle.env') == 'sandbox') ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

            if (isset($subscription->id)) {
                try {
                    $response = Http::withToken(config('wave.paddle.api_key'))->get($this->paddle_url.'/subscriptions/'.$subscription->vendor_subscription_id, []);
                    $paddle_subscription = json_decode($response->body());
                    $paddle_subscription = $paddle_subscription->data;
                } catch (Exception $e) {
                    $this->error_retrieving_data = true;

                    return;
                }

                if (isset($paddle_subscription->scheduled_change->action) && $paddle_subscription->scheduled_change->action == 'cancel') {
                    $this->cancellation_scheduled = true;
                }

                $this->subscription_ends_at = $paddle_subscription->current_billing_period->ends_at;

                $this->cancel_url = $paddle_subscription->management_urls->cancel;
                $this->update_url = $paddle_subscription->management_urls->update_payment_method;
            }
        } elseif ($this->provider == 'stripe') {
            // Correctly fetch Stripe's `ends_at`
            $this->subscription_ends_at = $this->subscription?->ends_at;
        } elseif ($this->provider == 'paypal') {
            $this->subscription_ends_at = $this->subscription?->next_payment_at ?? $this->subscription?->ends_at;
        }
    }

    public function cancel(): void
    {
        $subscription = auth()->user()->latestSubscription();

        abort_unless($subscription?->vendor_slug === 'paddle', 404);

        $this->paddle_url ??= (config('wave.paddle.env') === 'sandbox')
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';
        $response = Http::withToken(config('wave.paddle.api_key'))->post($this->paddle_url.'/subscriptions/'.$subscription->vendor_subscription_id.'/cancel', [
            'reason' => 'Customer requested cancellation',
        ]);

        if ($response->successful()) {
            $this->cancellation_scheduled = true;

            $responseObject = json_decode($response->body());
            $subscription->ends_at = $responseObject->data->current_billing_period->ends_at;
            $subscription->save();

            $this->js("window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'cancel-modal' }}));");
            Notification::make()
                ->title('Cancellation scheduled.')
                ->success()
                ->send();
        }
    }

    public function cancelPayPal(PayPalClient $paypalClient): void
    {
        $subscription = auth()->user()->latestSubscription();

        abort_unless($subscription?->vendor_slug === 'paypal', 404);

        $paypalClient->cancelSubscription(
            $subscription->vendor_subscription_id,
            'Customer requested cancellation',
            'cancel-subscription-'.$subscription->getKey(),
        );

        $subscription->cancel();

        $this->redirectRoute('settings.subscription');
    }

    public function cancelImmediately()
    {
        $subscription = auth()->user()->subscription;

        abort_unless($subscription?->vendor_slug === 'paddle', 404);

        $this->paddle_url ??= (config('wave.paddle.env') === 'sandbox')
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';

        $response = Http::withToken(config('wave.paddle.api_key'))->post($this->paddle_url.'/subscriptions/'.$subscription->vendor_subscription_id.'/cancel', [
            'effective_from' => 'immediately',
        ]);

        if ($response->successful()) {
            $subscription->cancel();

            return redirect()->to('/settings/subscription');
        }
    }

    public function render()
    {
        return view('wave::livewire.billing.update');
    }
}
