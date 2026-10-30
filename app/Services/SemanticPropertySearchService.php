<?php

namespace App\Services;

use App\Helpers\PropertySlugHelper;
use App\Models\PropertyListing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;

class SemanticPropertySearchService
{
    private const EMBEDDING_CACHE_MINUTES = 1440;

    private const RESULT_CACHE_MINUTES = 10;

    private const RESULT_CACHE_LIMIT = 200;

    private const LOCATION_CACHE_MINUTES = 60;

    public function __construct(
        private readonly EmbeddingService $embeddingService,
        private readonly SearchTermTypeResolver $typeResolver,
    ) {
    }

    /**
     * Search active listings semantically within one country.
     */
    public function search(string $searchTerm, string $country, int $perPage = 20): LengthAwarePaginator
    {
        $searchTerm = $this->normalizeSearchTerm($searchTerm);
        $types = $this->typeResolver->resolve($searchTerm);
        $embedding = $this->getEmbedding($searchTerm);

        if ($embedding === null) {
            return $this->textSearch($searchTerm, $country, $types, $perPage);
        }

        $matches = Cache::remember(
            $this->resultCacheKey($searchTerm, $country),
            now()->addMinutes(self::RESULT_CACHE_MINUTES),
            fn (): array => $this->findSemanticMatches($embedding, $country, $types),
        );

        // Un nombre de lugar suelto ("valparaiso") queda lejos de todo embedding
        if ($matches === []) {
            return $this->locationSearch($searchTerm, $country, $types, $perPage);
        }

        return $this->paginateMatches($matches, $country, $perPage, $this->mentionedLocations($searchTerm, $country));
    }

    /**
     * Todos los resultados de la búsqueda (sin paginar) para la vista de mapa: la query sin
     * ordenar y la función que aplica el orden (similitud, o destacados + recientes).
     *
     * @return array{0: Builder, 1: callable(Builder): Builder}
     */
    public function mapQuery(string $searchTerm, string $country): array
    {
        $searchTerm = $this->normalizeSearchTerm($searchTerm);
        $types = $this->typeResolver->resolve($searchTerm);
        $embedding = $this->getEmbedding($searchTerm);
        $latestFirst = fn (Builder $query): Builder => $query->orderByDesc('is_featured')->latest('created_at');

        if ($embedding === null) {
            return [$this->textSearchQuery($searchTerm, $country, $types), $latestFirst];
        }

        $matches = Cache::remember(
            $this->resultCacheKey($searchTerm, $country),
            now()->addMinutes(self::RESULT_CACHE_MINUTES),
            fn (): array => $this->findSemanticMatches($embedding, $country, $types),
        );

        if ($matches === []) {
            return [$this->locationSearchQuery($searchTerm, $country, $types), $latestFirst];
        }

        $listingIds = array_column($matches, 'id');

        return [
            PropertyListing::query()->active()->where('country', $country)->whereIn('id', $listingIds),
            fn (Builder $query): Builder => $query->orderByRaw('array_position(?::bigint[], id)', ['{' . implode(',', $listingIds) . '}']),
        ];
    }

    private function getEmbedding(string $searchTerm): ?Vector
    {
        $embedding = Cache::remember(
            $this->embeddingCacheKey($searchTerm),
            now()->addMinutes(self::EMBEDDING_CACHE_MINUTES),
            function () use ($searchTerm): array {
                $vector = $this->embeddingService->generate([$searchTerm]);

                return $vector?->toArray() ?? [];
            },
        );

        return is_array($embedding) && $embedding !== []
            ? new Vector($embedding)
            : null;
    }

    /**
     * Anuncios con similitud mínima absoluta y, además, a menos de
     * `search_relative_margin` del mejor resultado. Si el texto menciona un tipo
     * de inmueble u operación, solo se consideran anuncios de ese tipo/operación.
     *
     * @param  array{property_types: list<string>, transaction_types: list<string>}  $types
     * @return array<int, array{id: int, similarity: float}>
     */
    private function findSemanticMatches(Vector $embedding, string $country, array $types): array
    {
        $threshold = (float) config('openai.search_distance_threshold', 0.5);
        $margin = (float) config('openai.search_relative_margin', 0.10);

        $matches = PropertyListing::query()
            ->active()
            ->where('country', $country)
            ->whereNotNull('embedding')
            ->tap(fn (Builder $query) => $this->applyTypeFilters($query, $types))
            ->select('id')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$embedding])
            ->whereRaw('(embedding <=> ?) <= ?', [$embedding, $threshold])
            ->orderByDesc('similarity')
            ->limit(self::RESULT_CACHE_LIMIT)
            ->get()
            ->map(fn (PropertyListing $listing): array => [
                'id' => (int) $listing->id,
                'similarity' => (float) $listing->similarity,
            ]);

        $minimumSimilarity = ($matches->first()['similarity'] ?? 0) - $margin;

        return $matches
            ->filter(fn (array $match): bool => $match['similarity'] >= $minimumSimilarity)
            ->values()
            ->all();
    }

    /**
     * @param  array{property_types: list<string>, transaction_types: list<string>}  $types
     */
    private function applyTypeFilters(Builder $query, array $types): void
    {
        if ($types['property_types'] !== []) {
            $query->whereIn(DB::raw('LOWER(property_type)'), $types['property_types']);
        }

        if ($types['transaction_types'] !== []) {
            $query->whereIn(DB::raw('LOWER(transaction_type)'), $types['transaction_types']);
        }
    }

    /**
     * Slugs de las ciudades y regiones del país (con anuncios activos) que aparecen
     * como palabras completas en el texto de búsqueda.
     *
     * @return list<string>
     */
    private function mentionedLocations(string $searchTerm, string $country): array
    {
        $locations = Cache::remember(
            'semantic-property-search:locations:v1:' . sha1($country),
            now()->addMinutes(self::LOCATION_CACHE_MINUTES),
            fn (): array => PropertyListing::query()
                ->active()
                ->where('country', $country)
                ->select('city', 'state')
                ->distinct()
                ->get()
                ->flatMap(fn (PropertyListing $listing): array => [$listing->city, $listing->state])
                ->map(fn (?string $location): string => PropertySlugHelper::normalize((string) $location))
                ->filter(fn (string $slug): bool => mb_strlen($slug) >= 3)
                ->unique()
                ->values()
                ->all(),
        );

        $termSlug = '-' . PropertySlugHelper::normalize($searchTerm) . '-';

        return array_values(array_filter(
            $locations,
            fn (string $slug): bool => str_contains($termSlug, '-' . $slug . '-'),
        ));
    }

    /**
     * Los anuncios de una ciudad o región mencionada en la búsqueda van primero;
     * dentro de cada grupo se mantiene el orden por similitud.
     *
     * @param  array<int, array{id: int, similarity: float}>  $matches
     * @param  list<string>  $locations
     */
    private function paginateMatches(array $matches, string $country, int $perPage, array $locations = []): LengthAwarePaginator
    {
        $page = LengthAwarePaginatorInstance::resolveCurrentPage();
        $listingIds = array_column($matches, 'id');

        $listings = PropertyListing::query()
            ->active()
            ->where('country', $country)
            ->whereIn('id', $listingIds)
            ->with(['primaryImage', 'firstImage'])
            ->get()
            ->keyBy('id');

        $validMatches = collect($matches)
            ->filter(fn (array $match): bool => $listings->has($match['id']))
            ->values();

        if ($locations !== []) {
            [$inLocation, $elsewhere] = $validMatches->partition(function (array $match) use ($listings, $locations): bool {
                $listing = $listings->get($match['id']);

                return in_array(PropertySlugHelper::normalize((string) $listing->city), $locations, true)
                    || in_array(PropertySlugHelper::normalize((string) $listing->state), $locations, true);
            });

            $validMatches = $inLocation->concat($elsewhere)->values();
        }

        $items = $validMatches
            ->slice(($page - 1) * $perPage, $perPage)
            ->map(function (array $match) use ($listings): ?PropertyListing {
                $listing = $listings->get($match['id']);

                if (! $listing) {
                    return null;
                }

                $listing->setAttribute('similarity', round($match['similarity'] * 100, 2));

                return $listing;
            })
            ->filter()
            ->values();

        return new LengthAwarePaginatorInstance(
            $items,
            $validMatches->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array{property_types: list<string>, transaction_types: list<string>}  $types
     */
    private function textSearch(string $searchTerm, string $country, array $types, int $perPage): LengthAwarePaginator
    {
        return $this->textSearchQuery($searchTerm, $country, $types)
            ->with(['primaryImage', 'firstImage'])
            ->orderByDesc('is_featured')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{property_types: list<string>, transaction_types: list<string>}  $types
     */
    private function textSearchQuery(string $searchTerm, string $country, array $types): Builder
    {
        $terms = Str::of($searchTerm)
            ->explode(' ')
            ->filter(fn (string $term): bool => mb_strlen($term) >= 2)
            ->values();

        return PropertyListing::query()
            ->active()
            ->where('country', $country)
            ->tap(fn (Builder $query) => $this->applyTypeFilters($query, $types))
            ->when($terms->isNotEmpty(), function (Builder $query) use ($terms): void {
                $query->where(function (Builder $query) use ($terms): void {
                    foreach ($terms as $term) {
                        $likeTerm = '%' . addcslashes($term, '%_\\') . '%';

                        $query
                            ->orWhereRaw('unaccent(title) ILIKE unaccent(?) ESCAPE \'\\\'', [$likeTerm])
                            ->orWhereRaw('unaccent(description) ILIKE unaccent(?) ESCAPE \'\\\'', [$likeTerm])
                            ->orWhereRaw('unaccent(city) ILIKE unaccent(?) ESCAPE \'\\\'', [$likeTerm])
                            ->orWhereRaw('unaccent(state) ILIKE unaccent(?) ESCAPE \'\\\'', [$likeTerm]);
                    }
                });
            });
    }

    /**
     * Anuncios cuya ciudad o región contiene el texto completo de la búsqueda.
     *
     * @param  array{property_types: list<string>, transaction_types: list<string>}  $types
     */
    private function locationSearch(string $searchTerm, string $country, array $types, int $perPage): LengthAwarePaginator
    {
        return $this->locationSearchQuery($searchTerm, $country, $types)
            ->with(['primaryImage', 'firstImage'])
            ->orderByDesc('is_featured')
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{property_types: list<string>, transaction_types: list<string>}  $types
     */
    private function locationSearchQuery(string $searchTerm, string $country, array $types): Builder
    {
        $likeTerm = '%' . addcslashes($searchTerm, '%_\\') . '%';

        return PropertyListing::query()
            ->active()
            ->where('country', $country)
            ->tap(fn (Builder $query) => $this->applyTypeFilters($query, $types))
            ->where(function (Builder $query) use ($likeTerm): void {
                $query
                    ->whereRaw('unaccent(city) ILIKE unaccent(?) ESCAPE \'\\\'', [$likeTerm])
                    ->orWhereRaw('unaccent(state) ILIKE unaccent(?) ESCAPE \'\\\'', [$likeTerm]);
            });
    }

    private function normalizeSearchTerm(string $searchTerm): string
    {
        return Str::of($searchTerm)->squish()->lower()->toString();
    }

    private function embeddingCacheKey(string $searchTerm): string
    {
        return 'semantic-property-search:embedding:v1:' . sha1($searchTerm);
    }

    private function resultCacheKey(string $searchTerm, string $country): string
    {
        return 'semantic-property-search:results:v3:' . sha1($country . '|' . $searchTerm);
    }
}
