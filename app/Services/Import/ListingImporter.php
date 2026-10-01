<?php

namespace App\Services\Import;

use App\Models\ImportSource;
use App\Models\PropertyImage;
use App\Models\PropertyListing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Crea o actualiza un anuncio a partir de un payload normalizado de una fuente.
 *
 * La actualización es un merge de tres vías por campo: el valor nuevo de la fuente solo se aplica
 * si la fuente lo cambió respecto de la última importación (import_data) y el usuario no editó
 * ese campo a mano en el anuncio.
 */
class ListingImporter
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const UNCHANGED = 'unchanged';

    /** Campos del anuncio gobernados por la fuente. */
    public const FIELDS = [
        'title', 'description', 'property_type', 'transaction_type', 'price', 'currency',
        'bedrooms', 'bathrooms', 'parking_spaces', 'area', 'lotsize', 'address',
        'city', 'state', 'country', 'postal_code', 'latitude', 'longitude',
    ];

    /**
     * @param  array<string, mixed>  $data  payload normalizado del driver
     * @return self::CREATED|self::UPDATED|self::UNCHANGED
     *
     * @throws ImportSourceException si faltan datos obligatorios
     */
    public function import(ImportSource $source, array $data): string
    {
        $externalId = (string) ($data['id'] ?? '');

        foreach (['title', 'property_type', 'transaction_type'] as $required) {
            if (empty($data[$required])) {
                throw new ImportSourceException("Anuncio {$externalId}: dato obligatorio ausente ({$required})");
            }
        }

        $payload = collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => $data[$f] ?? null])->all();
        $images = array_values(array_unique($data['images'] ?? []));

        $listing = PropertyListing::where('import_source_id', $source->id)
            ->where('external_id', $externalId)
            ->first();

        if (! $listing) {
            $listing = PropertyListing::create($this->withDefaults($payload) + [
                'user_id'              => $source->user_id,
                'external_id'          => $externalId,
                'source'               => $source->driver,
                'import_source_id'     => $source->id,
                'import_data'          => $payload,
                'import_missing_count' => 0,
                'is_active'            => true,
                'is_featured'          => false,
            ]);

            $this->syncImages($listing, $images);

            return self::CREATED;
        }

        $changes = $this->mergeChanges($listing, $payload);

        // Volvió a aparecer en el feed: reactivar solo si lo había desactivado la importación
        if (! $listing->is_active && $listing->import_missing_count >= config('import.missing_tolerance', 2)) {
            $changes['is_active'] = true;
        }

        $status = self::UNCHANGED;
        if ($changes !== []) {
            $status = self::UPDATED;
        }

        $listing->fill($changes);
        $listing->import_data = $payload;
        $listing->import_missing_count = 0;
        $listing->save();

        if ($this->syncImages($listing, $images)) {
            $status = self::UPDATED;
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeChanges(PropertyListing $listing, array $payload): array
    {
        $previous = $listing->import_data ?? [];
        $changes = [];

        foreach (self::FIELDS as $field) {
            $incoming = $payload[$field];
            $lastImported = $previous[$field] ?? null;

            if ($this->same($incoming, $lastImported)) {
                continue; // la fuente no cambió este campo
            }

            $current = $listing->getAttribute($field);
            $neverImported = $previous === [];

            // Sin edición manual del usuario (o sin base de comparación): se aplica el valor de la fuente
            if ($neverImported || $this->same($current, $lastImported)) {
                $changes[$field] = $incoming;
            }
        }

        return $this->withDefaults($changes);
    }

    /**
     * Las columnas numéricas no aceptan null en la tabla.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withDefaults(array $values): array
    {
        foreach (['price', 'bedrooms', 'bathrooms', 'parking_spaces', 'area'] as $numeric) {
            if (array_key_exists($numeric, $values) && $values[$numeric] === null) {
                $values[$numeric] = 0;
            }
        }

        foreach (['description', 'city', 'state', 'country', 'currency'] as $text) {
            if (array_key_exists($text, $values) && $values[$text] === null) {
                $values[$text] = '';
            }
        }

        return $values;
    }

    private function same(mixed $a, mixed $b): bool
    {
        $a = ($a === '' ? null : $a);
        $b = ($b === '' ? null : $b);

        if ($a === null || $b === null) {
            return $a === $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return trim((string) $a) === trim((string) $b);
    }

    /**
     * Sincroniza las imágenes vinculadas a la fuente. Las subidas por el usuario (sin source_url)
     * no se tocan.
     *
     * @param  array<int, string>  $urls
     * @return bool si hubo cambios
     */
    private function syncImages(PropertyListing $listing, array $urls): bool
    {
        $existing = $listing->images()->get();
        $changed = false;

        foreach ($existing as $image) {
            if ($image->source_url !== null && ! in_array($image->source_url, $urls, true)) {
                $this->deleteImage($image);
                $changed = true;
            }
        }

        $known = $existing->pluck('source_url')->filter()->all();
        $hasPrimary = $existing->contains(fn ($i) => $i->is_primary && ($i->source_url === null || in_array($i->source_url, $urls, true)));

        foreach ($urls as $index => $url) {
            if (in_array($url, $known, true)) {
                continue;
            }

            $image = $this->downloadImage($listing, $url, $index, ! $hasPrimary);
            if ($image) {
                $hasPrimary = true;
                $changed = true;
            }
        }

        if (! $hasPrimary) {
            $first = $listing->images()->orderBy('sort_order')->orderBy('id')->first();
            $first?->update(['is_primary' => true]);
        }

        return $changed;
    }

    private function deleteImage(PropertyImage $image): void
    {
        if ($image->image_path) {
            Storage::disk('public')->delete($image->image_path);
        }
        $image->delete();
    }

    private function downloadImage(PropertyListing $listing, string $url, int $order, bool $primary): ?PropertyImage
    {
        try {
            $response = Http::timeout(config('import.image_timeout', 60))
                ->withHeaders(['User-Agent' => 'BienesOnline-Import/1.0'])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('ListingImporter: imagen no disponible', ['url' => $url]);

                return null;
            }

            if (strlen($response->body()) > config('import.max_image_size', 10 * 1024 * 1024)) {
                Log::warning('ListingImporter: imagen demasiado grande', ['url' => $url]);

                return null;
            }

            $filename = 'property_images/' . Str::uuid() . '.' . $this->extension($response->header('Content-Type'), $url);
            Storage::disk('public')->put($filename, $response->body());

            return PropertyImage::create([
                'property_listing_id' => $listing->id,
                'image_path'          => $filename,
                // Ruta relativa (igual que las subidas manuales): no depende de APP_URL
                'image_url'           => parse_url(Storage::disk('public')->url($filename), PHP_URL_PATH),
                'source_url'          => $url,
                'alt_text'            => $listing->title,
                'is_primary'          => $primary,
                'sort_order'          => $order,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ListingImporter: falló descarga de imagen', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function extension(?string $contentType, string $url): string
    {
        $map = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

        if ($contentType) {
            $ct = strtolower(trim(explode(';', $contentType)[0]));
            if (isset($map[$ct])) {
                return $map[$ct];
            }
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
    }
}
