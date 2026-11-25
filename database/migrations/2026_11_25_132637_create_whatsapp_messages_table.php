<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mensajes de la bandeja de WhatsApp: los que escriben los usuarios (in) y las respuestas
     * enviadas desde el admin (out). Los avisos automáticos siguen en whatsapp_message_logs.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('wamid', 191)->nullable()->unique();
            $table->string('direction', 3); // in, out
            $table->string('type', 30);     // text, image, audio, video, document, sticker, location, button, interactive, reaction, template, ...
            $table->text('body')->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('media_id', 100)->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_filename')->nullable();
            $table->string('context_wamid', 191)->nullable();
            $table->foreignId('whatsapp_message_log_id')->nullable()->constrained('whatsapp_message_logs')->nullOnDelete();
            $table->string('status', 20); // received, sent, delivered, read, failed
            $table->text('error_message')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
