<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('driver', 50);
            $table->string('name')->nullable();
            $table->json('config');
            $table->string('status', 20)->default('active'); // active | paused | error
            $table->boolean('sync_enabled')->default(true);
            $table->unsignedSmallInteger('sync_interval_hours')->default(24);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable(); // completed | failed
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'driver']);
            $table->index(['status', 'sync_enabled', 'last_synced_at']);
        });

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->foreignId('import_source_id')->nullable()->after('user_id')
                ->constrained('import_sources')->cascadeOnDelete();
            $table->string('type', 20)->default('initial')->after('status'); // initial | sync
            $table->integer('updated_listings')->default(0)->after('imported_listings');
            $table->integer('deactivated_listings')->default(0)->after('failed_listings');
            $table->index(['import_source_id', 'created_at']);
        });

        Schema::table('property_listings', function (Blueprint $table) {
            $table->foreignId('import_source_id')->nullable()->after('source')
                ->constrained('import_sources')->nullOnDelete();
            // Último payload normalizado importado (base del merge de tres vías en cada sync)
            $table->json('import_data')->nullable()->after('import_source_id');
            $table->unsignedSmallInteger('import_missing_count')->default(0)->after('import_data');
            $table->index(['import_source_id', 'external_id']);
        });

        Schema::table('property_images', function (Blueprint $table) {
            $table->string('source_url', 2048)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('property_images', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });

        Schema::table('property_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_source_id');
            $table->dropColumn(['import_data', 'import_missing_count']);
        });

        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_source_id');
            $table->dropColumn(['type', 'updated_listings', 'deactivated_listings']);
        });

        Schema::dropIfExists('import_sources');
    }
};
