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
        Schema::table('property_requests', function (Blueprint $table) {
            $table->string('source')->nullable()->after('expires_at'); // e.g. 'whatsapp_contact', 'manual'
            $table->foreignId('source_listing_id')->nullable()->after('source')->constrained('property_listings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->dropForeign(['source_listing_id']);
            $table->dropColumn(['source', 'source_listing_id']);
        });
    }
};
