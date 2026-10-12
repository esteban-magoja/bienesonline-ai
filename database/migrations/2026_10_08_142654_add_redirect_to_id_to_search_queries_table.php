<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            // Búsqueda principal a la que redirige (301) esta variante; si se borra la principal, la variante vuelve a mostrarse
            $table->foreignId('redirect_to_id')->nullable()->after('active')->constrained('search_queries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('search_queries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('redirect_to_id');
        });
    }
};
