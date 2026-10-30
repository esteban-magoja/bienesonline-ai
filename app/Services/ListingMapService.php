<?php

namespace App\Services;

use App\Models\PropertyImage;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Lógica común de las vistas de mapa (listados SEO y perfiles de inmobiliaria):
 * anuncios con coordenadas, zona visible (bbox) y JSON de marcadores.
 */
class ListingMapService
{
    /** Máximo de marcadores por respuesta del mapa (con más resultados se pide acercar el mapa). */
    public const MARKER_LIMIT = 1000;

    public function __construct(private SeoService $seoService) {}

    /**
     * Restringe la query a anuncios con coordenadas utilizables (descarta nulas y 0,0).
     */
    public function onlyMappable(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0);
    }

    /**
     * Centro inicial opcional del mapa (?lat=&lng=&zoom=), p. ej. desde la ficha de un anuncio.
     *
     * @return array{lat: float, lng: float, zoom: int}|null
     */
    public function parseMapCenter(Request $request): ?array
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return null;
        }

        $zoom = is_numeric($request->query('zoom')) ? (int) $request->query('zoom') : 14;

        return ['lat' => (float) $lat, 'lng' => (float) $lng, 'zoom' => max(3, min(18, $zoom))];
    }

    /**
     * Respuesta JSON de marcadores para la query dada (sin ordenar), acotada a ?bbox= si viene.
     * Solo se cachea la carga inicial (sin bbox): las zonas del mapa varían demasiado.
     *
     * @param  callable(Builder): Builder  $applyOrder
     */
    public function response(Builder $query, Request $request, string $locale, callable $applyOrder, string $cacheKey): JsonResponse
    {
        $request->validate(['bbox' => ['nullable', 'string', 'max:100']]);

        $bounds = $this->parseBoundingBox($request->query('bbox'));
        if ($request->filled('bbox') && !$bounds) {
            abort(422, 'bbox inválido');
        }

        $build = fn (): array => $this->payload($query, $bounds, $locale, $applyOrder);

        return response()->json($bounds ? $build() : Cache::remember($cacheKey, 600, $build));
    }

    /**
     * @param  array{south: float, west: float, north: float, east: float}|null  $bounds
     * @param  callable(Builder): Builder  $applyOrder
     * @return array{total: int, limit: int, listings: array<int, array<string, mixed>>}
     */
    private function payload(Builder $query, ?array $bounds, string $locale, callable $applyOrder): array
    {
        $query = $this->onlyMappable($query);

        if ($bounds) {
            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }

        $total = (clone $query)->count();

        $listings = $applyOrder($query)
            ->select([
                'id', 'title', 'title_i18n', 'price', 'currency', 'property_type', 'transaction_type',
                'bedrooms', 'bathrooms', 'area', 'city', 'state', 'country',
                'latitude', 'longitude', 'is_featured',
            ])
            ->addSelect(['image_path' => PropertyImage::select('image_path')
                ->whereColumn('property_listing_id', 'property_listings.id')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(1)])
            ->limit(self::MARKER_LIMIT)
            ->get();

        $labels = [];
        $label = function (string $class, ?string $value) use (&$labels, $locale): ?string {
            if (!$value) {
                return null;
            }

            return $labels[$class][$value] ??= $class::getLabel($value, $locale);
        };

        return [
            'total' => $total,
            'limit' => self::MARKER_LIMIT,
            'listings' => $listings->map(fn (PropertyListing $listing): array => [
                'id' => $listing->id,
                'lat' => (float) $listing->latitude,
                'lng' => (float) $listing->longitude,
                'title' => $locale === 'es'
                    ? $listing->title
                    : ($listing->getTranslation('title', $locale) ?: $listing->title),
                'price' => $listing->price !== null ? (float) $listing->price : null,
                'currency' => $listing->currency,
                'type' => $label(PropertyType::class, $listing->property_type),
                'operation' => $label(TransactionType::class, $listing->transaction_type),
                'bedrooms' => $listing->bedrooms,
                'bathrooms' => $listing->bathrooms,
                'area' => $listing->area ? (int) round((float) $listing->area) : null,
                'location' => collect([$listing->city, $listing->state])->filter()->implode(', '),
                'featured' => (bool) $listing->is_featured,
                'image' => $listing->image_path ? Storage::url($listing->image_path) : null,
                'url' => $this->seoService->generatePropertyUrl($listing, $locale),
            ])->all(),
        ];
    }

    /**
     * @return array{south: float, west: float, north: float, east: float}|null
     */
    private function parseBoundingBox(?string $bbox): ?array
    {
        if (!$bbox) {
            return null;
        }

        $parts = explode(',', $bbox);
        if (count($parts) !== 4 || array_filter($parts, fn ($part) => !is_numeric($part))) {
            return null;
        }

        [$south, $west, $north, $east] = array_map('floatval', $parts);

        if ($south > $north || $west > $east || $south < -90 || $north > 90 || $west < -180 || $east > 180) {
            return null;
        }

        return compact('south', 'west', 'north', 'east');
    }
}
