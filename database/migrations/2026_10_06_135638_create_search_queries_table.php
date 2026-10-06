<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('country', 100);
            $table->string('query', 255);
            $table->string('slug', 255);
            $table->unsignedInteger('results_count')->default(0);
            $table->boolean('active')->default(false);
            $table->timestamps();

            // Una búsqueda por país + slug: evita repetidas
            $table->unique(['country', 'slug']);
            // "Otras búsquedas": activas del mismo país
            $table->index(['country', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
