<?php

namespace App\Console\Commands;

use App\Models\PropertyListing;
use App\Services\SeoService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ExportLegacyListingIds extends Command
{
    protected $signature = 'listings:export-legacy-ids
                            {path? : Ruta del archivo CSV de salida}
                            {--locale=es : Locale que se incluirá en la URL}
                            {--chunk=500 : Cantidad de anuncios procesados por lote}';

    protected $description = 'Exporta los anuncios que tienen un ID legacy al final de la descripción';

    public function __construct(private readonly SeoService $seoService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->resolveOutputPath($this->argument('path'));
        $locale = (string) $this->option('locale');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("No se pudo crear el directorio de salida: {$directory}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            $this->error("No se pudo abrir el archivo de salida: {$path}");

            return self::FAILURE;
        }

        $exported = 0;
        $scanned = 0;

        try {
            fputcsv($handle, ['country_slug', 'legacy_id', 'url'], ',', '"', '');

            PropertyListing::query()
                ->select(['id', 'title', 'description', 'city', 'country'])
                ->chunkById($chunkSize, function (Collection $listings) use ($handle, $locale, &$exported, &$scanned): void {
                    foreach ($listings as $listing) {
                        $scanned++;
                        $legacyId = $this->extractLegacyId($listing->getRawOriginal('description'));

                        if ($legacyId === null) {
                            continue;
                        }

                        $url = parse_url($this->seoService->generatePropertyUrl($listing, $locale), PHP_URL_PATH);

                        if (! is_string($url)) {
                            throw new \RuntimeException("No se pudo obtener la ruta del anuncio {$listing->id}.");
                        }

                        fputcsv($handle, [
                            \Illuminate\Support\Str::slug((string) $listing->country),
                            $legacyId,
                            $url,
                        ], ',', '"', '');

                        $exported++;
                    }
                });
        } finally {
            fclose($handle);
        }

        $this->info("Archivo generado: {$path}");
        $this->info("Anuncios recorridos: {$scanned}. Registros exportados: {$exported}.");

        return self::SUCCESS;
    }

    private function resolveOutputPath(?string $path): string
    {
        $path ??= 'storage/app/exports/legacy-listings.csv';

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    private function extractLegacyId(mixed $description): ?string
    {
        if (! is_string($description)) {
            return null;
        }

        preg_match('/ID\s+Anuncio\s*:\s*([A-Z0-9]+)\s*$/i', $description, $matches);

        return $matches[1] ?? null;
    }
}
