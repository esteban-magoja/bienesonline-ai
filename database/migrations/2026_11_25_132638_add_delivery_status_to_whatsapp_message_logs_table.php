<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de entrega que informa el webhook de Meta para los avisos automáticos
     * (delivered, read, failed). `status` sigue indicando si Meta aceptó el envío.
     */
    public function up(): void
    {
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->string('delivery_status', 20)->nullable()->after('status');
            $table->timestamp('delivery_status_at')->nullable()->after('delivery_status');
            $table->text('delivery_error')->nullable()->after('delivery_status_at');

            $table->index('whatsapp_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_message_id']);
            $table->dropColumn(['delivery_status', 'delivery_status_at', 'delivery_error']);
        });
    }
};
