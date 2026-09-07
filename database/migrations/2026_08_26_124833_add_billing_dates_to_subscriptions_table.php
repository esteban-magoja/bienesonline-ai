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
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('subscriptions', 'last_payment_at')) {
                $table->timestamp('last_payment_at')->nullable();
            }
            if (! Schema::hasColumn('subscriptions', 'next_payment_at')) {
                $table->timestamp('next_payment_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('subscriptions', 'cancelled_at') ? 'cancelled_at' : null,
            Schema::hasColumn('subscriptions', 'last_payment_at') ? 'last_payment_at' : null,
            Schema::hasColumn('subscriptions', 'next_payment_at') ? 'next_payment_at' : null,
        ]));

        if ($columns !== []) {
            Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
