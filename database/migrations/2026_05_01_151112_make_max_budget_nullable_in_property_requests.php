<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->decimal('max_budget', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_requests', function (Blueprint $table) {
            $table->decimal('max_budget', 15, 2)->nullable(false)->change();
        });
    }
};
