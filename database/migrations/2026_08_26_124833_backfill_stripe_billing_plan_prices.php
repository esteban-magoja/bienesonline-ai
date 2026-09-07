<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $plans = DB::table('plans')
            ->select(['id', 'monthly_price_id', 'yearly_price_id', 'monthly_price', 'yearly_price'])
            ->get();

        foreach ($plans as $plan) {
            $prices = [
                'month' => [
                    'external_id' => $plan->monthly_price_id,
                    'amount' => $plan->monthly_price,
                ],
                'year' => [
                    'external_id' => $plan->yearly_price_id,
                    'amount' => $plan->yearly_price,
                ],
            ];

            foreach ($prices as $cycle => $price) {
                if (blank($price['external_id'])) {
                    continue;
                }

                DB::table('billing_plan_prices')->insertOrIgnore([
                    'plan_id' => $plan->id,
                    'provider' => 'stripe',
                    'cycle' => $cycle,
                    'external_id' => $price['external_id'],
                    'amount' => $price['amount'],
                    'currency' => 'USD',
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Provider price rows may have been edited after this backfill; preserve them.
    }
};
