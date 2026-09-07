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
        if (Schema::hasTable('billing_checkout_attempts')) {
            return;
        }

        Schema::create('billing_checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('plan_id');
            $table->string('provider', 50);
            $table->string('cycle', 20);
            $table->string('external_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->unique(['provider', 'external_id']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_checkout_attempts');
    }
};
