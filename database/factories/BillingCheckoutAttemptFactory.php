<?php

namespace Database\Factories;

use App\Models\BillingCheckoutAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Wave\Plan;

/**
 * @extends Factory<BillingCheckoutAttempt>
 */
class BillingCheckoutAttemptFactory extends Factory
{
    protected $model = BillingCheckoutAttempt::class;

    public function definition(): array
    {
        $idempotencyKey = (string) Str::uuid();

        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'provider' => 'paypal',
            'cycle' => 'month',
            'idempotency_key' => $idempotencyKey,
            'status' => 'pending',
            'metadata' => [
                'custom_id' => 'checkout:'.$idempotencyKey,
                'paypal_plan_id' => 'P-'.fake()->unique()->bothify('?????????????????????????'),
            ],
            'expires_at' => now()->addHour(),
        ];
    }
}
