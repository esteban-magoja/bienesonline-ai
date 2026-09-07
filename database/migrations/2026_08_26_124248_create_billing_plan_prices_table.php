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
        if (Schema::hasTable('billing_plan_prices')) {
            return;
        }

        Schema::create('billing_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('plan_id');
            $table->string('provider', 50);
            $table->string('cycle', 20);
            $table->string('external_id');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'provider', 'cycle']);
            $table->unique(['provider', 'external_id']);
            $table->index(['provider', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_plan_prices');
    }
};
