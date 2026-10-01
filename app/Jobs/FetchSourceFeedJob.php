<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\ImportSourceException;
use App\Services\Import\ImportSourceRegistry;
use App\Services\Import\ImportSourceRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Descarga y normaliza el feed de una fuente, guarda los anuncios como items pendientes
 * y encadena ProcessSourceImportChunkJob.
 */
class FetchSourceFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(private readonly int $importJobId) {}

    public function handle(ImportSourceRegistry $registry, ImportSourceRunner $runner): void
    {
        $job = ImportJob::with('importSource')->find($this->importJobId);

        if (! $job || $job->status !== 'pending' || ! $job->importSource) {
            return;
        }

        $source = $job->importSource;
        $job->update(['status' => 'processing']);

        try {
            $listings = $registry->get($source->driver)->fetch($source->config);
        } catch (ImportSourceException $e) {
            $runner->markFailed($job, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            Log::error('FetchSourceFeedJob: error inesperado', ['import_job' => $job->id, 'error' => $e->getMessage()]);
            $runner->markFailed($job, __('import.feed_unreachable'));

            return;
        }

        if ($listings === []) {
            // Un feed vacío no cierra la corrida como éxito: evita desactivar todo por un feed roto
            $runner->markFailed($job, __('import.no_listings_in_feed'));

            return;
        }

        $listings = array_slice($listings, 0, (int) config('import.max_listings_per_feed', 2000));

        $now = now();
        $rows = array_map(fn (array $listing) => [
            'import_job_id' => $job->id,
            'data'          => json_encode($listing),
            'status'        => 'pending',
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $listings);

        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('import_listing_items')->insert($batch);
        }

        $job->update(['total_listings' => count($listings)]);

        ProcessSourceImportChunkJob::dispatch($job->id, (int) config('import.chunk_size', 20));
    }

    public function failed(\Throwable $exception): void
    {
        $job = ImportJob::with('importSource')->find($this->importJobId);

        if ($job && in_array($job->status, ['pending', 'processing'], true)) {
            app(ImportSourceRunner::class)->markFailed($job, __('import.feed_unreachable'));
        }
    }
}
