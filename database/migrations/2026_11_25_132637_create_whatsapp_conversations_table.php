<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una conversación por número de WhatsApp (wa_id: solo dígitos, como lo informa Meta).
     * last_inbound_at abre la ventana de 24 h en la que se puede responder con texto libre.
     */
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id', 30)->unique();
            $table->string('profile_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamps();

            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
