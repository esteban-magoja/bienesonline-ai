<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\ImportListingItem;
use App\Models\PropertyImage;
use App\Models\PropertyListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nnjeim\World\Models\Country;

class ProcessImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        private readonly int $importJobId,
        private readonly int $chunkSize = 20
    ) {}

    public function handle(): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (!$importJob || $importJob->status === 'failed') {
            return;
        }

        // Pasar a 'processing' si todavía está en 'pending'
        if ($importJob->status === 'pending') {
            $importJob->update(['status' => 'processing']);
        }

        // Tomar el siguiente lote de items pendientes con lock para evitar concurrencia
        $items = DB::transaction(function () {
            return ImportListingItem::where('import_job_id', $this->importJobId)
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit($this->chunkSize)
                ->lockForUpdate()
                ->get();
        });

        if ($items->isEmpty()) {
            $this->maybeComplete($importJob);
            return;
        }

        $source = config('import.source_name', 'legacy');

        foreach ($items as $item) {
            try {
                $this->processItem($item, $source, $importJob);
            } catch (\Exception $e) {
                Log::error('ProcessImportChunkJob: error en item', [
                    'item_id'    => $item->id,
                    'import_job' => $this->importJobId,
                    'error'      => $e->getMessage(),
                ]);
                $item->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $importJob->increment('failed_listings');
            }
        }

        // Despachar siguiente chunk (se ejecuta tanto si hay más items como si no)
        static::dispatch($this->importJobId, $this->chunkSize);
    }

    private function processItem(ImportListingItem $item, string $source, ImportJob $importJob): void
    {
        $data = $item->data;
        $externalId = (string) ($data['id'] ?? '');

        // Saltar si ya fue importado por este mismo usuario
        if ($externalId && PropertyListing::where('external_id', $externalId)->where('source', $source)->where('user_id', $importJob->user_id)->exists()) {
            $item->update(['status' => 'done']);
            $importJob->increment('skipped_listings');
            return;
        }

        $listing = PropertyListing::create([
            'user_id'          => $importJob->user_id,
            'external_id'      => $externalId ?: null,
            'source'           => $source,
            'title'            => $data['title'] ?? '',
            'description'      => $data['description'] ?? '',
            'property_type'    => $data['property_type'] ?? '',
            'transaction_type' => $data['transaction_type'] ?? '',
            'price'            => $data['price'] ?? 0,
            'currency'         => $this->normalizeCurrency($data['currency'] ?? '', $data['country'] ?? ''),
            'bedrooms'         => $data['bedrooms'] ?? 0,
            'bathrooms'        => $data['bathrooms'] ?? 0,
            'parking_spaces'   => $data['parking_spaces'] ?? 0,
            'area'             => $data['area'] ?? 0,
            'lotsize'          => $data['lotsize'] ?? null,
            'address'          => $data['address'] ?? null,
            'city'             => $data['city'] ?? '',
            'state'            => $this->resolveState($data['state'] ?? '', $data['country'] ?? ''),
            'country'          => $data['country'] ?? '',
            'postal_code'      => $data['postal_code'] ?? null,
            'latitude'         => $data['latitude'] ?? null,
            'longitude'        => $data['longitude'] ?? null,
            'conditions'       => $data['conditions'] ?? null,
            'is_active'        => $data['is_active'] ?? true,
            'is_featured'      => false,
        ]);

        // Marcar como done antes de descargar imágenes: si el job se interrumpe
        // durante la descarga, en el reintento el external_id ya existe y se salta
        // correctamente en lugar de intentar crear un listing duplicado.
        $item->update(['status' => 'done']);
        $importJob->increment('imported_listings');

        foreach ($data['images'] ?? [] as $imageData) {
            $this->importImage($listing, $imageData);
        }
    }

    /**
     * Marca el ImportJob como completado solo si no quedan items pendientes o en error.
     */
    private function maybeComplete(ImportJob $importJob): void
    {
        $pendingCount = ImportListingItem::where('import_job_id', $this->importJobId)
            ->where('status', 'pending')
            ->count();

        if ($pendingCount === 0) {
            $importJob->update(['status' => 'completed']);
        }
    }

    private function importImage(PropertyListing $listing, array $imageData): void
    {
        $url = $imageData['url'] ?? '';
        if (empty($url)) {
            return;
        }

        try {
            $response = Http::timeout(config('import.image_timeout', 60))
                ->withHeaders(['User-Agent' => 'BienesOnline-Import/1.0'])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('ProcessImportChunkJob: imagen no disponible', ['url' => $url]);
                return;
            }

            if (strlen($response->body()) > config('import.max_image_size', 10 * 1024 * 1024)) {
                Log::warning('ProcessImportChunkJob: imagen demasiado grande', ['url' => $url]);
                return;
            }

            $extension = $this->guessExtension($response->header('Content-Type'), $url);
            $filename  = 'property_images/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($filename, $response->body());

            PropertyImage::create([
                'property_listing_id' => $listing->id,
                'image_path'          => $filename,
                'image_url'           => Storage::disk('public')->url($filename),
                'alt_text'            => $listing->title,
                'is_primary'          => $imageData['is_primary'] ?? false,
                'sort_order'          => $imageData['sort_order'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::warning('ProcessImportChunkJob: falló descarga de imagen', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function guessExtension(?string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if ($contentType) {
            $ct = strtolower(trim(explode(';', $contentType)[0]));
            if (isset($map[$ct])) {
                return $map[$ct];
            }
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])
            ? ($ext === 'jpeg' ? 'jpg' : $ext)
            : 'jpg';
    }

    /**
     * Para Chile convierte la provincia (campo "state" del legacy) a la Región correspondiente.
     * Para el resto de países devuelve el valor sin modificar.
     */
    private function resolveState(string $state, string $country): string
    {
        if (strtolower(trim($country)) !== 'chile') {
            return $state;
        }

        return $this->chileProvinceToRegion($state) ?? $state;
    }

    /**
     * Devuelve la Región chilena para una provincia dada, o null si no se reconoce.
     * La comparación se hace de forma normalizada (sin acentos, minúsculas).
     */
    private function chileProvinceToRegion(string $province): ?string
    {
        $map = [
            // Arica y Parinacota
            'arica'                       => 'Arica y Parinacota',
            'parinacota'                  => 'Arica y Parinacota',
            // Tarapacá
            'el tamarugal'                => 'Tarapacá',
            'iquique'                     => 'Tarapacá',
            // Antofagasta
            'antofagasta'                 => 'Antofagasta',
            'el loa'                      => 'Antofagasta',
            'tocopilla'                   => 'Antofagasta',
            // Atacama
            'chanaral'                    => 'Atacama',
            'copiapo'                     => 'Atacama',
            'huasco'                      => 'Atacama',
            // Coquimbo
            'elqui'                       => 'Coquimbo',
            'limari'                      => 'Coquimbo',
            'choapa'                      => 'Coquimbo',
            // Valparaíso
            'isla de pascua'              => 'Valparaíso',
            'petorca'                     => 'Valparaíso',
            'valparaiso'                  => 'Valparaíso',
            'san felipe de aconcagua'     => 'Valparaíso',
            'los andes'                   => 'Valparaíso',
            'quillota'                    => 'Valparaíso',
            'san antonio'                 => 'Valparaíso',
            'marga marga'                 => 'Valparaíso',
            // Metropolitana de Santiago
            'santiago'                    => 'Metropolitana de Santiago',
            'cordillera'                  => 'Metropolitana de Santiago',
            'chacabuco'                   => 'Metropolitana de Santiago',
            'maipo'                       => 'Metropolitana de Santiago',
            'melipilla'                   => 'Metropolitana de Santiago',
            'talagante'                   => 'Metropolitana de Santiago',
            // Libertador General Bernardo O'Higgins
            'cachapoal'                   => "Libertador General Bernardo O'Higgins",
            'cardenal caro'               => "Libertador General Bernardo O'Higgins",
            'colchagua'                   => "Libertador General Bernardo O'Higgins",
            // Maule
            'curico'                      => 'Maule',
            'talca'                       => 'Maule',
            'cauquenes'                   => 'Maule',
            'linares'                     => 'Maule',
            // Ñuble
            'itata'                       => 'Ñuble',
            'diguillin'                   => 'Ñuble',
            'punilla'                     => 'Ñuble',
            // Biobío
            'arauco'                      => 'Biobío',
            'biobio'                      => 'Biobío',
            'concepcion'                  => 'Biobío',
            'nuble'                       => 'Biobío',
            // La Araucanía
            'cautin'                      => 'La Araucanía',
            'malleco'                     => 'La Araucanía',
            // Los Ríos
            'valdivia'                    => 'Los Ríos',
            'ranco'                       => 'Los Ríos',
            // Los Lagos
            'llanquihue'                  => 'Los Lagos',
            'osorno'                      => 'Los Lagos',
            'palena'                      => 'Los Lagos',
            'chiloe'                      => 'Los Lagos',
            // Aysén del General Carlos Ibáñez del Campo
            'coihaique'                   => 'Aysén del General Carlos Ibáñez del Campo',
            'general carrera'             => 'Aysén del General Carlos Ibáñez del Campo',
            'capitan prat'                => 'Aysén del General Carlos Ibáñez del Campo',
            'aisen'                       => 'Aysén del General Carlos Ibáñez del Campo',
            'aysen'                       => 'Aysén del General Carlos Ibáñez del Campo',
            // Magallanes y de la Antártica Chilena
            'magallanes'                  => 'Magallanes y de la Antártica Chilena',
            'tierra del fuego'            => 'Magallanes y de la Antártica Chilena',
            'ultima esperanza'            => 'Magallanes y de la Antártica Chilena',
        ];

        $normalized = $this->normalizeString($province);

        foreach ($map as $key => $region) {
            if ($this->normalizeString($key) === $normalized) {
                return $region;
            }
        }

        Log::info('ProcessImportChunkJob: provincia chilena no reconocida, se guarda el valor original', [
            'province' => $province,
        ]);

        return null;
    }

    /**
     * Convierte a minúsculas y elimina acentos/diacríticos para comparación normalizada.
     */
    private function normalizeString(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return $value;
    }

    private function normalizeCurrency(string $currency, string $country = ''): string
    {
        $map = [
            'colones' => 'CRC', 'colon' => 'CRC', 'colón' => 'CRC', 'crc' => 'CRC', '₡' => 'CRC',
            'dolares' => 'USD', 'dólares' => 'USD', 'dolar' => 'USD', 'dólar' => 'USD',
            'u$d' => 'USD', 'u$s' => 'USD', 'usd' => 'USD', '$' => 'USD',
            'euros' => 'EUR', 'euro' => 'EUR', 'eur' => 'EUR', '€' => 'EUR',
            'quetzales' => 'GTQ', 'quetzal' => 'GTQ', 'gtq' => 'GTQ',
            'soles' => 'PEN', 'sol' => 'PEN', 'pen' => 'PEN',
            'guaranies' => 'PYG', 'guaraníes' => 'PYG', 'guarani' => 'PYG', 'guaraní' => 'PYG', 'pyg' => 'PYG',
            'uf' => 'CLF', 'ufs' => 'CLF', 'unidad de fomento' => 'CLF', 'clf' => 'CLF',
            'bolivares' => 'VES', 'bolívares' => 'VES', 'bolivar' => 'VES', 'bolívar' => 'VES', 'ves' => 'VES',
            'lempiras' => 'HNL', 'lempira' => 'HNL', 'hnl' => 'HNL',
            'balboas' => 'PAB', 'balboa' => 'PAB', 'pab' => 'PAB',
            'mxn' => 'MXN', 'ars' => 'ARS', 'cop' => 'COP', 'clp' => 'CLP', 'uyu' => 'UYU', 'dop' => 'DOP',
        ];

        $normalized = strtolower(trim($currency));

        if (in_array($normalized, ['pesos', 'peso'])) {
            return $this->pesosByCountry($country);
        }

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if (preg_match('/^[a-zA-Z]{2,3}$/', $currency)) {
            return strtoupper($currency);
        }

        Log::warning('ProcessImportChunkJob: moneda desconocida, fallback USD', [
            'currency' => $currency,
            'country'  => $country,
        ]);
        return 'USD';
    }

    private function pesosByCountry(string $country): string
    {
        $countryMap = [
            'argentina'            => 'ARS',
            'mexico'               => 'MXN',
            'méxico'               => 'MXN',
            'colombia'             => 'COP',
            'chile'                => 'CLP',
            'uruguay'              => 'UYU',
            'republica dominicana' => 'DOP',
            'república dominicana' => 'DOP',
            'dominican republic'   => 'DOP',
            'cuba'                 => 'CUP',
            'paraguay'             => 'PYG',
            'bolivia'              => 'BOB',
        ];

        $key = strtolower(trim($country));
        if (isset($countryMap[$key])) {
            return $countryMap[$key];
        }

        Log::warning('ProcessImportChunkJob: "pesos" sin país reconocido, fallback USD', [
            'country' => $country,
        ]);
        return 'USD';
    }

    /**
     * Si el job falla (timeout, error fatal), despachar el siguiente chunk para continuar.
     * Así la cadena no se rompe aunque un chunk individual falle.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessImportChunkJob: chunk falló, despachando siguiente', [
            'import_job' => $this->importJobId,
            'error'      => $exception->getMessage(),
        ]);

        $importJob = ImportJob::find($this->importJobId);
        if (!$importJob || $importJob->status === 'failed') {
            return;
        }

        // Verificar si aún hay items pendientes antes de seguir
        $hasPending = ImportListingItem::where('import_job_id', $this->importJobId)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            static::dispatch($this->importJobId, $this->chunkSize);
        } else {
            $importJob->update(['status' => 'completed']);
        }
    }
}
