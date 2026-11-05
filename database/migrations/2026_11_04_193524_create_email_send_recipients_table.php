<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un registro por destinatario de cada envío.
     */
    public function up(): void
    {
        Schema::create('email_send_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_send_id')->constrained()->cascadeOnDelete();
            $table->string('email'); // en minúsculas
            $table->string('name')->nullable();
            $table->string('agency')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, sending, sent, failed, skipped
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['email_send_id', 'status']);
            $table->index(['email', 'status']);
            $table->index('user_id');
            $table->index('email_contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_recipients');
    }
};
