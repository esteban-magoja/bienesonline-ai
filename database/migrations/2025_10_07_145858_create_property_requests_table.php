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
        Schema::create('property_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->string('property_type'); // casa, departamento, local, terreno, etc.
            $table->string('transaction_type'); // venta, alquiler
            $table->decimal('min_budget', 15, 2)->nullable();
            $table->decimal('max_budget', 15, 2);
            $table->string('currency')->default('USD');
            $table->integer('min_bedrooms')->nullable();
            $table->integer('min_bathrooms')->nullable();
            $table->integer('min_parking_spaces')->nullable();
            $table->integer('min_area')->nullable(); // en metros cuadrados mínimos
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_requests');
    }
};
