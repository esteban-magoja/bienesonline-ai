<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchQuery extends Model
{
    /** @use HasFactory<\Database\Factories\SearchQueryFactory> */
    use HasFactory;

    public const MIN_LENGTH = 10;

    /**
     * Palabras que no aportan para comparar búsquedas similares.
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'a', 'al', 'and', 'con', 'de', 'del', 'el', 'en', 'for', 'in', 'la', 'las', 'lo', 'los',
        'of', 'or', 'para', 'por', 'sin', 'the', 'to', 'un', 'una', 'with', 'y',
    ];

    protected $fillable = [
        'country',
        'query',
        'slug',
        'results_count',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'results_count' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Registra una búsqueda de texto si tiene al menos MIN_LENGTH caracteres
     * y no existe ya para ese país (país + slug únicos). Un fallo al registrarla
     * no debe romper la búsqueda: se loguea y devuelve null.
     */
    public static function record(string $country, string $searchTerm, int $resultsCount): ?self
    {
        $query = Str::of($searchTerm)->squish()->lower()->toString();
        $slug = Str::slug($query);

        if ($country === '' || mb_strlen($query) < self::MIN_LENGTH || $slug === '') {
            return null;
        }

        try {
            return static::firstOrCreate(
                ['country' => $country, 'slug' => Str::limit($slug, 255, '')],
                ['query' => Str::limit($query, 255, ''), 'results_count' => $resultsCount],
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la búsqueda', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Búsquedas activas del mismo país ordenadas por palabras del slug en común
     * (ignorando stopwords y la "s" final) y luego por cantidad de anuncios,
     * así siempre se completan los lugares disponibles. Cacheado 1 hora.
     *
     * @return Collection<int, array{query: string, slug: string}>
     */
    public static function related(string $country, string $slug, int $limit = 5): Collection
    {
        return Cache::remember(
            'related_searches_' . md5("{$country}|{$slug}|{$limit}"),
            3600,
            function () use ($country, $slug, $limit): Collection {
                $words = collect(explode('-', $slug))
                    ->reject(fn (string $word): bool => $word === '' || in_array($word, self::STOPWORDS, true))
                    ->map(fn (string $word): string => preg_replace('/s$/', '', $word))
                    ->unique()
                    ->values();

                return static::query()
                    ->where('country', $country)
                    ->where('active', true)
                    ->where('slug', '!=', $slug)
                    ->orderByRaw(
                        "(SELECT COUNT(*) FROM unnest(string_to_array(slug, '-')) AS word WHERE regexp_replace(word, 's$', '') = ANY(?::text[])) DESC",
                        ['{' . $words->implode(',') . '}'],
                    )
                    ->orderByDesc('results_count')
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get(['query', 'slug'])
                    ->map(fn (self $search): array => ['query' => $search->query, 'slug' => $search->slug]);
            },
        );
    }
}
