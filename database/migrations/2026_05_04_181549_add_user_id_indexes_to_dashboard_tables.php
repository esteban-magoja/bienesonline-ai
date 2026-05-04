<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Composite index for dashboard count: WHERE user_id = X AND is_active = true
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->index(['user_id', 'is_active'], 'property_listings_user_id_is_active_index');
        });

        Schema::table('property_requests', function (Blueprint $table): void {
            $table->index(['user_id', 'is_active'], 'property_requests_user_id_is_active_index');
        });

        // Index for ImportJob::where('user_id')->latest()->first()
        Schema::table('import_jobs', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'import_jobs_user_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropIndex('property_listings_user_id_is_active_index');
        });

        Schema::table('property_requests', function (Blueprint $table): void {
            $table->dropIndex('property_requests_user_id_is_active_index');
        });

        Schema::table('import_jobs', function (Blueprint $table): void {
            $table->dropIndex('import_jobs_user_id_created_at_index');
        });
    }
};
