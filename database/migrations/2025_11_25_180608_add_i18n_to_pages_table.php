<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Agregar columnas JSON para traducciones
            $table->json('title_i18n')->nullable()->after('title');
            $table->json('excerpt_i18n')->nullable()->after('excerpt');
            $table->json('body_i18n')->nullable()->after('body');
            $table->json('meta_description_i18n')->nullable()->after('meta_description');
            $table->json('meta_keywords_i18n')->nullable()->after('meta_keywords');
        });

        // Migrar datos existentes
        DB::statement("
            UPDATE pages 
            SET 
                title_i18n = json_build_object('es', title),
                excerpt_i18n = json_build_object('es', excerpt),
                body_i18n = json_build_object('es', body),
                meta_description_i18n = json_build_object('es', meta_description),
                meta_keywords_i18n = json_build_object('es', meta_keywords)
            WHERE title_i18n IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn([
                'title_i18n',
                'excerpt_i18n',
                'body_i18n',
                'meta_description_i18n',
                'meta_keywords_i18n'
            ]);
        });
    }
};
