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
            $table->json('title_i18n')->nullable()->after('title');
            $table->json('description_i18n')->nullable()->after('description');
            $table->json('features_i18n')->nullable()->after('features');
            $table->json('location_details_i18n')->nullable()->after('location_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            $table->dropColumn(['title_i18n', 'description_i18n', 'features_i18n', 'location_details_i18n']);
        });
    }
};
