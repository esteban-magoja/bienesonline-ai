<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_contacts', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('property_contacts', function (Blueprint $table) {
            $table->dropColumn('seen_at');
        });
    }
};
