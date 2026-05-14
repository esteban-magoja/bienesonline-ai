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
        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');
            $table->string('phone', 30);
            $table->string('notification_class', 150);
            $table->string('event_type', 50)->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('language_code', 10)->nullable();
            $table->foreignId('property_listing_id')->nullable()->constrained('property_listings')->nullOnDelete();
            $table->foreignId('property_request_id')->nullable()->constrained('property_requests')->nullOnDelete();
            $table->string('status', 20); // sent, failed, disabled, no_phone
            $table->string('whatsapp_message_id', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};
