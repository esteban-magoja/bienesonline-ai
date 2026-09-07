<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy legacy Stripe price references only when the normalized row is missing.
     * Existing provider configuration is intentionally never overwritten or deleted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('plans') || ! Schema::hasTable('billing_plan_prices')) {
            return;
        }

        DB::table('plans')
            ->select(['id', 'monthly_price_id', 'yearly_price_id', 'monthly_price', 'yearly_price'])
            ->orderBy('id')
            ->get()
            ->each(function (object $plan): void {
                foreach ([
                    'month' => [
                        'external_id' => $plan->monthly_price_id,
                        'amount' => $plan->monthly_price,
                    ],
                    'year' => [
                        'external_id' => $plan->yearly_price_id,
                        'amount' => $plan->yearly_price,
                    ],
                ] as $cycle => $price) {
                    if (blank($price['external_id'])) {
                        continue;
                    }

                    $exists = DB::table('billing_plan_prices')
                        ->where('plan_id', $plan->id)
                        ->where('provider', 'stripe')
                        ->where('cycle', $cycle)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $externalIdAlreadyUsed = DB::table('billing_plan_prices')
                        ->where('provider', 'stripe')
                        ->where('external_id', $price['external_id'])
                        ->exists();

                    if ($externalIdAlreadyUsed) {
                        continue;
                    }

                    DB::table('billing_plan_prices')->insert([
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
            });
    }

    public function down(): void
    {
        // Provider price rows may have been edited after this migration; preserve them.
    }
};
