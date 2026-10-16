<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            // Métricas de Google Search Console importadas con search-queries:import-gsc
            $table->unsignedInteger('gsc_clicks')->nullable()->after('redirect_to_id');
            $table->unsignedInteger('gsc_impressions')->nullable()->after('gsc_clicks');
            $table->decimal('gsc_position', 6, 2)->nullable()->after('gsc_impressions');
            $table->timestamp('gsc_imported_at')->nullable()->after('gsc_position');
        });
    }

    public function down(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            $table->dropColumn(['gsc_clicks', 'gsc_impressions', 'gsc_position', 'gsc_imported_at']);
        });
    }
};
