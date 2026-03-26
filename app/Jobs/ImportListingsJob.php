<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\PropertyImage;
use App\Models\PropertyListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nnjeim\World\Models\Country;

class ImportListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        private readonly int $importJobId,
        private readonly int $userId,
        private readonly array $listings
    ) {}

    public function handle(): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (!$importJob) {
            return;
        }

        $importJob->update(['status' => 'processing']);

        $source = config('import.source_name', 'legacy');

        foreach ($this->listings as $data) {
            try {
                $this->importListing($data, $source, $importJob);
            } catch (\Exception $e) {
                Log::error('ImportListingsJob: error importando listing', [
                    'external_id' => $data['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $importJob->increment('failed_listings');
            }
        }

        $importJob->update(['status' => 'completed']);
    }

    private function importListing(array $data, string $source, ImportJob $importJob): void
    {
        $externalId = (string) ($data['id'] ?? '');

        // Saltar si ya fue importado antes
        if ($externalId && PropertyListing::where('external_id', $externalId)->where('source', $source)->exists()) {
            $importJob->increment('skipped_listings');
            return;
        }

        // Resolver country_id desde el nombre del país
        $countryId = $this->resolveCountryId($data['country'] ?? '');

        // Crear el listing (el Observer genera el embedding automáticamente)
        $listing = PropertyListing::create([
            'user_id'          => $this->userId,
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
            'state'            => $data['state'] ?? '',
            'country'          => $data['country'] ?? '',
            'postal_code'      => $data['postal_code'] ?? null,
            'latitude'         => $data['latitude'] ?? null,
            'longitude'        => $data['longitude'] ?? null,
            'conditions'       => $data['conditions'] ?? null,
            'is_active'        => $data['is_active'] ?? true,
            'is_featured'      => false,
        ]);

        // Importar imágenes
        $images = $data['images'] ?? [];
        foreach ($images as $imageData) {
            $this->importImage($listing, $imageData);
        }

        $importJob->increment('imported_listings');
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
                Log::warning('ImportListingsJob: imagen no disponible', ['url' => $url, 'status' => $response->status()]);
                return;
            }

            $maxSize = config('import.max_image_size', 10 * 1024 * 1024);
            if (strlen($response->body()) > $maxSize) {
                Log::warning('ImportListingsJob: imagen demasiado grande', ['url' => $url]);
                return;
            }

            // Determinar extensión desde Content-Type o URL
            $extension = $this->guessExtension($response->header('Content-Type'), $url);
            $filename = 'property_images/' . Str::uuid() . '.' . $extension;

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
            Log::warning('ImportListingsJob: falló descarga de imagen', [
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

        if ($contentType && isset($map[strtolower(trim(explode(';', $contentType)[0]))])) {
            return $map[strtolower(trim(explode(';', $contentType)[0]))];
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
    }

    /**
     * Normaliza el valor de moneda del legacy a un código ISO 4217 de 3 caracteres.
     * Usa el país para resolver ambigüedades (ej: "pesos" en México → MXN, en Argentina → ARS).
     */
    private function normalizeCurrency(string $currency, string $country = ''): string
    {
        $map = [
            // Colones costarricenses
            'colones'      => 'CRC',
            'colon'        => 'CRC',
            'colón'        => 'CRC',
            'crc'          => 'CRC',
            '₡'            => 'CRC',
            // Dólares (variantes del legacy)
            'dolares'      => 'USD',
            'dólares'      => 'USD',
            'dolar'        => 'USD',
            'dólar'        => 'USD',
            'u$d'          => 'USD',
            'u$s'          => 'USD',
            'usd'          => 'USD',
            '$'            => 'USD',
            // Euros
            'euros'        => 'EUR',
            'euro'         => 'EUR',
            'eur'          => 'EUR',
            '€'            => 'EUR',
            // Quetzales guatemaltecos
            'quetzales'    => 'GTQ',
            'quetzal'      => 'GTQ',
            'gtq'          => 'GTQ',
            // Soles peruanos
            'soles'        => 'PEN',
            'sol'          => 'PEN',
            'pen'          => 'PEN',
            // Guaraníes paraguayos
            'guaranies'    => 'PYG',
            'guaraníes'    => 'PYG',
            'guarani'      => 'PYG',
            'guaraní'      => 'PYG',
            'pyg'          => 'PYG',
            // UF chilena (Unidad de Fomento)
            'uf'           => 'CLF',
            'ufs'          => 'CLF',
            'unidad de fomento' => 'CLF',
            'clf'          => 'CLF',
            // Bolívares venezolanos
            'bolivares'    => 'VES',
            'bolívares'    => 'VES',
            'bolivar'      => 'VES',
            'bolívar'      => 'VES',
            'ves'          => 'VES',
            // Lempiras hondureñas
            'lempiras'     => 'HNL',
            'lempira'      => 'HNL',
            'hnl'          => 'HNL',
            // Balboas panameñas
            'balboas'      => 'PAB',
            'balboa'       => 'PAB',
            'pab'          => 'PAB',
            // Códigos ISO explícitos de pesos
            'mxn'          => 'MXN',
            'ars'          => 'ARS',
            'cop'          => 'COP',
            'clp'          => 'CLP',
            'uyu'          => 'UYU',
            'dop'          => 'DOP',
        ];

        $normalized = strtolower(trim($currency));

        // Resolver "pesos" / "peso" usando el país de origen
        if (in_array($normalized, ['pesos', 'peso'])) {
            return $this->pesosByCountry($country);
        }

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        // Si ya es un código ISO de 2-3 letras, devolverlo en mayúsculas
        if (preg_match('/^[a-zA-Z]{2,3}$/', $currency)) {
            return strtoupper($currency);
        }

        // Fallback: USD
        Log::warning('ImportListingsJob: moneda desconocida, usando USD como fallback', [
            'currency' => $currency,
            'country'  => $country,
        ]);
        return 'USD';
    }

    /**
     * Resuelve el código ISO del peso según el nombre del país.
     */
    private function pesosByCountry(string $country): string
    {
        $countryMap = [
            // Argentina
            'argentina'            => 'ARS',
            // México
            'mexico'               => 'MXN',
            'méxico'               => 'MXN',
            // Colombia
            'colombia'             => 'COP',
            // Chile
            'chile'                => 'CLP',
            // Uruguay
            'uruguay'              => 'UYU',
            // República Dominicana
            'republica dominicana' => 'DOP',
            'república dominicana' => 'DOP',
            'dominican republic'   => 'DOP',
            // Cuba
            'cuba'                 => 'CUP',
            // Paraguay (usa guaraníes, pero por si acaso)
            'paraguay'             => 'PYG',
            // Bolivia (usa bolivianos)
            'bolivia'              => 'BOB',
        ];

        $key = strtolower(trim($country));
        if (isset($countryMap[$key])) {
            return $countryMap[$key];
        }

        Log::warning('ImportListingsJob: "pesos" sin país reconocido, usando USD como fallback', [
            'country' => $country,
        ]);
        return 'USD';
    }

    private function resolveCountryId(string $countryName): ?int
    {
        if (empty($countryName)) {
            return null;
        }

        $country = Country::where('name', $countryName)->first();
        return $country?->id;
    }

    public function failed(\Throwable $exception): void
    {
        ImportJob::where('id', $this->importJobId)->update([
            'status'        => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
