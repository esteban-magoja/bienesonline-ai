<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_images', function (Blueprint $table) {
            $table->index('property_listing_id');
        });

        Schema::table('property_messages', function (Blueprint $table) {
            $table->index('property_listing_id');
        });
    }

    public function down(): void
    {
        Schema::table('property_images', function (Blueprint $table) {
            $table->dropIndex(['property_listing_id']);
        });

        Schema::table('property_messages', function (Blueprint $table) {
            $table->dropIndex(['property_listing_id']);
        });
    }
};
