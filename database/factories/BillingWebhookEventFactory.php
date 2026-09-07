<?php

namespace Database\Factories;

use App\Models\BillingWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingWebhookEvent>
 */
class BillingWebhookEventFactory extends Factory
{
    protected $model = BillingWebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => 'paypal',
            'external_event_id' => fake()->unique()->uuid(),
            'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource_type' => 'subscription',
            'payload' => [],
            'status' => 'pending',
        ];
    }
}
