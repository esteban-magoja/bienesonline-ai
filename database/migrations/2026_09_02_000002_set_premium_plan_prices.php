<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the single Premium plan's displayed prices aligned with the public offer.
     */
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        $premiumPlan = DB::table('plans')
            ->where('name', 'Premium')
            ->orderBy('id')
            ->first(['id', 'monthly_price', 'yearly_price']);

        if (! $premiumPlan) {
            return;
        }

        if ((string) $premiumPlan->monthly_price === '10'
            && (string) $premiumPlan->yearly_price === '99') {
            return;
        }

        DB::table('plans')
            ->where('id', $premiumPlan->id)
            ->update([
                'monthly_price' => '10',
                'yearly_price' => '99',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The previous prices are not known reliably across installations.
    }
};
