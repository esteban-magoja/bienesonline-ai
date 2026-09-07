<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_checkout_attempts')
            || Schema::hasColumn('billing_checkout_attempts', 'idempotency_key')) {
            return;
        }

        Schema::table('billing_checkout_attempts', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable();
            $table->unique(['provider', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('billing_checkout_attempts')
            || ! Schema::hasColumn('billing_checkout_attempts', 'idempotency_key')) {
            return;
        }

        Schema::table('billing_checkout_attempts', function (Blueprint $table): void {
            $table->dropUnique('billing_checkout_attempts_provider_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
