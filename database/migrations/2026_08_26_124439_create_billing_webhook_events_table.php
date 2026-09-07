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
        if (Schema::hasTable('billing_webhook_events')) {
            return;
        }

        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('external_event_id');
            $table->string('event_type', 150);
            $table->string('resource_type', 100)->nullable();
            $table->json('payload');
            $table->string('status', 30)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id']);
            $table->index(['provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
