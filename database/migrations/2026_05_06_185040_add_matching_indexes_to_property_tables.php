<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            $table->index(['is_active', 'country', 'property_type', 'transaction_type'], 'pl_matching_idx');
        });

        Schema::table('property_requests', function (Blueprint $table) {
            $table->index(['is_active', 'country', 'property_type', 'transaction_type'], 'pr_matching_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            $table->dropIndex('pl_matching_idx');
        });

        Schema::table('property_requests', function (Blueprint $table) {
            $table->dropIndex('pr_matching_idx');
        });
    }
};
