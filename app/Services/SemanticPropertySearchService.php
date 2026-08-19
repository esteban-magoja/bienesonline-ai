<?php

namespace App\Services;

use App\Models\PropertyListing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;

class SemanticPropertySearchService
{
    private const EMBEDDING_CACHE_MINUTES = 1440;

    private const RESULT_CACHE_MINUTES = 10;

    private const RESULT_CACHE_LIMIT = 500;

    public function __construct(private readonly EmbeddingService $embeddingService)
    {
    }

    /**
     * Search active listings semantically within one country.
     */
    public function search(string $searchTerm, string $country, int $perPage = 20): LengthAwarePaginator
    {
        $searchTerm = $this->normalizeSearchTerm($searchTerm);
        $embedding = $this->getEmbedding($searchTerm);

        if ($embedding === null) {
            return $this->textSearch($searchTerm, $country, $perPage);
        }

        $matches = Cache::remember(
            $this->resultCacheKey($searchTerm, $country),
            now()->addMinutes(self::RESULT_CACHE_MINUTES),
            fn (): array => $this->findSemanticMatches($embedding, $country),
        );

        return $this->paginateMatches($matches, $country, $perPage);
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
     * @return array<int, array{id: int, similarity: float}>
     */
    private function findSemanticMatches(Vector $embedding, string $country): array
    {
        $threshold = (float) config('openai.search_distance_threshold', 0.7);

        return PropertyListing::query()
            ->active()
            ->where('country', $country)
            ->whereNotNull('embedding')
            ->select('id')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$embedding])
            ->whereRaw('(embedding <=> ?) <= ?', [$embedding, $threshold])
            ->orderByDesc('similarity')
            ->limit(self::RESULT_CACHE_LIMIT)
            ->get()
            ->map(fn (PropertyListing $listing): array => [
                'id' => (int) $listing->id,
                'similarity' => (float) $listing->similarity,
            ])
            ->all();
    }

    /**
     * @param array<int, array{id: int, similarity: float}> $matches
     */
    private function paginateMatches(array $matches, string $country, int $perPage): LengthAwarePaginator
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

    private function textSearch(string $searchTerm, string $country, int $perPage): LengthAwarePaginator
    {
        $terms = Str::of($searchTerm)
            ->explode(' ')
            ->filter(fn (string $term): bool => mb_strlen($term) >= 2)
            ->values();

        $query = PropertyListing::query()
            ->active()
            ->where('country', $country)
            ->with(['primaryImage', 'firstImage'])
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
            })
            ->orderByDesc('is_featured')
            ->latest('created_at');

        return $query->paginate($perPage)->withQueryString();
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
        return 'semantic-property-search:results:v2:' . sha1($country . '|' . $searchTerm);
    }
}
