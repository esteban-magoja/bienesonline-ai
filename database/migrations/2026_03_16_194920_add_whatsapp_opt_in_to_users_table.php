<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_in')->default(false)->after('locale');
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_opt_in', 'whatsapp_opt_in_at']);
        });
    }
};
