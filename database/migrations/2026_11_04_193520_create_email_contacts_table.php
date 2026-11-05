<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contactos no registrados (posibles usuarios) a los que se envían correos desde el admin.
     */
    public function up(): void
    {
        Schema::create('email_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('agency')->nullable();
            $table->string('email')->unique(); // siempre en minúsculas
            $table->string('country', 100)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('source', 100)->nullable();
            $table->text('notes')->nullable();
            // Se completa cuando existe (o se registra) un usuario con el mismo email
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_contacts');
    }
};
