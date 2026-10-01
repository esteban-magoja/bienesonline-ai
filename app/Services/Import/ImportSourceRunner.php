<?php

namespace App\Services\Import;

use App\Jobs\FetchSourceFeedJob;
use App\Models\ImportJob;
use App\Models\ImportListingItem;
use App\Models\ImportSource;
use App\Models\PropertyListing;
use Illuminate\Support\Facades\Cache;

/**
 * Ciclo de vida de una importación/sincronización de una fuente.
 */
class ImportSourceRunner
{
    /**
     * Crea el ImportJob y encola la descarga del feed. Devuelve null si ya hay uno en curso o el usuario no es premium.
     */
    public function start(ImportSource $source, string $type = 'sync'): ?ImportJob
    {
        // Al perder el premium se detienen también los syncs automáticos
        if (! $source->user?->hasPremiumAccess() || $source->hasRunningJob()) {
            return null;
        }

        $job = ImportJob::create([
            'user_id'          => $source->user_id,
            'import_source_id' => $source->id,
            'status'           => 'pending',
            'type'             => $type,
        ]);

        FetchSourceFeedJob::dispatch($job->id);

        return $job;
    }

    public function markFailed(ImportJob $job, string $message): void
    {
        $job->update(['status' => 'failed', 'error_message' => $message]);

        $source = $job->importSource;
        if (! $source) {
            return;
        }

        $failures = $source->consecutive_failures + 1;

        $source->update([
            'last_sync_status'     => 'failed',
            'last_error'           => $message,
            'consecutive_failures' => $failures,
            // Los reintentos automáticos se detienen tras N fallos seguidos
            'status'               => $failures >= config('import.max_consecutive_failures', 5)
                ? ImportSource::STATUS_ERROR
                : $source->status,
            // Se marca la corrida igualmente para no reintentar en cada ejecución del scheduler
            'last_synced_at'       => now(),
        ]);
    }

    /**
     * Cierra la corrida: desactiva anuncios que dejaron de estar en el feed y actualiza la fuente.
     * Es idempotente: solo el primer llamado sobre un job en 'processing' finaliza.
     */
    public function complete(ImportJob $job): void
    {
        $claimed = ImportJob::where('id', $job->id)
            ->where('status', 'processing')
            ->update(['status' => 'completed']);

        if (! $claimed) {
            return;
        }

        $source = $job->importSource;
        if (! $source) {
            return;
        }

        $deactivated = $this->handleMissingListings($source, $job);
        $job->update(['deactivated_listings' => $deactivated]);

        $source->update([
            'last_synced_at'       => now(),
            'last_sync_status'     => 'completed',
            'last_error'           => null,
            'consecutive_failures' => 0,
        ]);

        Cache::forget("dashboard_listings_{$source->user_id}");
    }

    /**
     * Los anuncios ausentes del feed suman una corrida perdida; al llegar a la tolerancia
     * se desactivan (un feed vacío o roto un día no debe vaciar el portal del usuario).
     */
    private function handleMissingListings(ImportSource $source, ImportJob $job): int
    {
        $seen = [];
        ImportListingItem::where('import_job_id', $job->id)
            ->select('id', 'data')
            ->cursor()
            ->each(function ($item) use (&$seen) {
                $id = $item->data['id'] ?? null;
                if ($id !== null) {
                    $seen[(string) $id] = true;
                }
            });

        if ($seen === []) {
            return 0;
        }

        $tolerance = (int) config('import.missing_tolerance', 2);
        $deactivated = 0;

        PropertyListing::where('import_source_id', $source->id)
            ->whereNotNull('external_id')
            ->where('import_missing_count', '<', $tolerance)
            ->select('id', 'external_id', 'user_id', 'is_active', 'import_missing_count')
            ->chunkById(200, function ($listings) use ($seen, $tolerance, &$deactivated) {
                foreach ($listings as $listing) {
                    if (isset($seen[(string) $listing->external_id])) {
                        continue;
                    }

                    $missing = $listing->import_missing_count + 1;
                    $update = ['import_missing_count' => $missing];

                    if ($missing >= $tolerance && $listing->is_active) {
                        $update['is_active'] = false;
                        $deactivated++;
                    }

                    // update() dispara el observer: limpia caches; no regenera embedding (campos no modificados)
                    $listing->update($update);
                }
            });

        return $deactivated;
    }
}
