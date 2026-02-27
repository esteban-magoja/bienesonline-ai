<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('id');
            $table->string('source')->nullable()->after('external_id');
            $table->index(['external_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            $table->dropIndex(['external_id', 'source']);
            $table->dropColumn(['external_id', 'source']);
        });
    }
};
