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
        Schema::create('property_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('property_listings')->cascadeOnDelete();
            $table->foreignId('visitor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['whatsapp', 'phone']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'created_at']);
            $table->index('listing_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_contacts');
    }
};
