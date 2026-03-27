<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_listing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->json('data');
            $table->enum('status', ['pending', 'done', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['import_job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_listing_items');
    }
};
