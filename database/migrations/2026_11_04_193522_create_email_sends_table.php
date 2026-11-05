<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada envío de un mensaje a un grupo de destinatarios. Guarda una copia del asunto y del cuerpo
     * tal como salieron, aunque después se edite el mensaje.
     */
    public function up(): void
    {
        Schema::create('email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->string('status', 20)->default('sending'); // sending, completed
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['email_template_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_sends');
    }
};
