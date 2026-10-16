<?php

namespace App\Models;

use App\Helpers\PropertySlugHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchQuery extends Model
{
    /** @use HasFactory<\Database\Factories\SearchQueryFactory> */
    use HasFactory;

    public const MIN_LENGTH = 15;

    /** Máximo de saltos al seguir redirecciones encadenadas (evita ciclos) */
    private const MAX_REDIRECT_HOPS = 5;

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
        'redirect_to_id',
        'gsc_clicks',
        'gsc_impressions',
        'gsc_position',
        'gsc_imported_at',
    ];

    protected function casts(): array
    {
        return [
            'results_count' => 'integer',
            'active' => 'boolean',
            'gsc_clicks' => 'integer',
            'gsc_impressions' => 'integer',
            'gsc_position' => 'float',
            'gsc_imported_at' => 'datetime',
        ];
    }

    /**
     * Búsqueda principal a la que redirige esta variante.
     *
     * @return BelongsTo<self, $this>
     */
    public function redirectTarget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'redirect_to_id');
    }

    /**
     * Slug final al que debe redirigir (301) la búsqueda indexable de ese país + slug,
     * siguiendo redirecciones encadenadas. Null si no redirige.
     */
    public static function redirectSlugFor(string $country, string $slug): ?string
    {
        $search = static::query()->where('country', $country)->where('slug', $slug)->first(['id', 'slug', 'redirect_to_id']);
        $visited = [];

        while ($search?->redirect_to_id && count($visited) < self::MAX_REDIRECT_HOPS) {
            $visited[] = $search->id;
            $target = static::query()->find($search->redirect_to_id, ['id', 'slug', 'redirect_to_id']);

            if (! $target || in_array($target->id, $visited, true)) {
                break;
            }

            $search = $target;
        }

        return $search && $search->slug !== $slug ? $search->slug : null;
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
            $search = static::firstOrCreate(
                ['country' => $country, 'slug' => Str::limit($slug, 255, '')],
                ['query' => Str::limit($query, 255, ''), 'results_count' => $resultsCount],
            );

            // Las creadas por import-gsc nacen sin cantidad de anuncios: se completa al visitarlas
            if (! $search->wasRecentlyCreated && $search->results_count !== $resultsCount) {
                $search->update(['results_count' => $resultsCount]);
            }

            return $search;
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la búsqueda', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Búsquedas activas del mismo país ordenadas por palabras del slug en común
     * (ignorando stopwords y la "s" final) y luego por cantidad de anuncios,
     * así siempre se completan los lugares disponibles. Excluye las variantes que
     * redirigen a otra búsqueda. Cacheado 1 hora.
     *
     * @return Collection<int, array{query: string, slug: string}>
     */
    public static function related(string $country, string $slug, int $limit = 5): Collection
    {
        return Cache::remember(
            'related_searches_' . md5("{$country}|{$slug}|{$limit}"),
            3600,
            function () use ($country, $slug, $limit): Collection {
                $words = static::normalizedWords($slug);

                return static::query()
                    ->where('country', $country)
                    ->where('active', true)
                    ->whereNull('redirect_to_id')
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

    /**
     * Palabras significativas del slug: sin stopwords, sin la "s" final y sin repetir.
     * Dos búsquedas con las mismas palabras (en cualquier orden) son casi iguales.
     *
     * @return Collection<int, string>
     */
    public static function normalizedWords(string $slug): Collection
    {
        return collect(explode('-', $slug))
            ->reject(fn (string $word): bool => $word === '' || in_array($word, self::STOPWORDS, true))
            ->map(fn (string $word): string => preg_replace('/s$/', '', $word))
            ->unique()
            ->values();
    }

    /**
     * Enlaces de "Otras búsquedas" (texto + URL indexable en el locale dado)
     * para las búsquedas relacionadas con el slug.
     *
     * @return Collection<int, array{label: string, url: string}>
     */
    public static function relatedLinks(string $country, string $slug, string $locale, int $limit = 5): Collection
    {
        return static::related($country, $slug, $limit)
            ->map(fn (array $search): array => [
                'label' => Str::ucfirst($search['query']),
                'url' => route('property.semantic-search', [
                    'locale' => $locale,
                    'country' => PropertySlugHelper::normalize($country),
                    'searchPath' => $locale === 'en' ? 'search' : 'busqueda',
                    'query' => $search['slug'],
                ]),
            ]);
    }
}
