<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\ImportListingItem;
use App\Services\Import\ImportSourceRunner;
use App\Services\Import\ListingImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Procesa por lotes los items de una importación de fuente externa (crea/actualiza anuncios).
 * Cada chunk despacha el siguiente hasta agotar los items pendientes.
 */
class ProcessSourceImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        private readonly int $importJobId,
        private readonly int $chunkSize = 20,
    ) {}

    public function handle(ListingImporter $importer, ImportSourceRunner $runner): void
    {
        $job = ImportJob::with('importSource')->find($this->importJobId);

        if (! $job || $job->status !== 'processing' || ! $job->importSource) {
            return;
        }

        $items = ImportListingItem::where('import_job_id', $job->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($this->chunkSize)
            ->get();

        if ($items->isEmpty()) {
            $runner->complete($job);

            return;
        }

        foreach ($items as $item) {
            try {
                $result = $importer->import($job->importSource, $item->data);

                $item->update(['status' => 'done']);

                match ($result) {
                    ListingImporter::CREATED   => $job->increment('imported_listings'),
                    ListingImporter::UPDATED   => $job->increment('updated_listings'),
                    default                    => $job->increment('skipped_listings'),
                };
            } catch (\Throwable $e) {
                Log::warning('ProcessSourceImportChunkJob: error en item', [
                    'item_id'    => $item->id,
                    'import_job' => $job->id,
                    'error'      => $e->getMessage(),
                ]);
                $item->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);
                $job->increment('failed_listings');
            }
        }

        static::dispatch($this->importJobId, $this->chunkSize);
    }

    /**
     * Si el chunk muere (timeout/fatal), se continúa la cadena.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessSourceImportChunkJob: chunk falló', [
            'import_job' => $this->importJobId,
            'error'      => $exception->getMessage(),
        ]);

        $job = ImportJob::find($this->importJobId);
        if (! $job || $job->status !== 'processing') {
            return;
        }

        // El item más antiguo aún pendiente es el que mató el chunk: se descarta para no entrar en bucle
        $culprit = ImportListingItem::where('import_job_id', $job->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->first();

        if ($culprit) {
            $culprit->update(['status' => 'failed', 'error_message' => 'Tiempo de espera agotado al procesar el anuncio']);
            $job->increment('failed_listings');
        }

        static::dispatch($this->importJobId, $this->chunkSize);
    }
}
