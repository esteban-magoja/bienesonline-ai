<?php

namespace Database\Factories;

use App\Models\BillingPlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Wave\Plan;

/**
 * @extends Factory<BillingPlanPrice>
 */
class BillingPlanPriceFactory extends Factory
{
    protected $model = BillingPlanPrice::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'provider' => 'paypal',
            'cycle' => 'month',
            'external_id' => 'P-'.fake()->unique()->bothify('?????????????????????????'),
            'amount' => '10.00',
            'currency' => 'USD',
            'active' => true,
        ];
    }
}
