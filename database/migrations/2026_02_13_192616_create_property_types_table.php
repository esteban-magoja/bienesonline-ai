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
        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 10)->index(); // ISO2 o 'INTL'
            $table->string('value', 50); // valor para BD: casa, departamento, etc
            $table->string('label', 100); // etiqueta visible para usuario
            $table->string('value_en', 50); // valor en inglés para matching: house, apartment
            $table->integer('order')->default(0); // orden de visualización
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Índices
            $table->unique(['country_code', 'value']);
            $table->index('value_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_types');
    }
};
