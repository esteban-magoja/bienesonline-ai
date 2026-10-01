<?php

namespace App\Console\Commands;

use App\Models\ImportSource;
use App\Services\Import\ImportSourceRunner;
use Illuminate\Console\Command;

class SyncImportSources extends Command
{
    protected $signature = 'imports:sync
        {--source= : ID de una fuente concreta (ignora la frecuencia)}
        {--limit=50 : Máximo de fuentes a encolar por ejecución}';

    protected $description = 'Encola la sincronización de las fuentes de importación cuyo intervalo venció';

    public function handle(ImportSourceRunner $runner): int
    {
        $query = $this->option('source')
            ? ImportSource::whereKey($this->option('source'))
            : ImportSource::dueForSync();

        $queued = 0;

        $query->orderBy('last_synced_at')->limit((int) $this->option('limit'))->get()
            ->each(function (ImportSource $source) use ($runner, &$queued) {
                if ($runner->start($source, 'sync')) {
                    $queued++;
                }
            });

        $this->info("Sincronizaciones encoladas: {$queued}");

        return self::SUCCESS;
    }
}
