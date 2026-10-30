<?php

namespace App\Http\Controllers;

use App\Helpers\PropertySlugHelper;
use App\Helpers\SeoLocaleHelper;
use App\Models\SearchQuery;
use App\Services\ListingMapService;
use App\Services\SemanticPropertySearchService;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SemanticPropertySearchController extends Controller
{
    public function __construct(
        private readonly SemanticPropertySearchService $searchService,
        private readonly SeoService $seoService,
        private readonly ListingMapService $listingMap,
    ) {
    }

    public function index(
        Request $request,
        string $locale,
        string $country,
        string $searchPath,
        string $query,
    ): View|RedirectResponse {
        App::setLocale($locale);

        $countryName = PropertySlugHelper::validateCountry($country);

        if (! $countryName) {
            abort(404, "País no encontrado: {$country}");
        }

        $searchTerm = Str::of($query)->replace('-', ' ')->squish()->lower()->toString();
        $canonicalQuery = Str::slug($searchTerm);
        $expectedSearchPath = $locale === 'en' ? 'search' : 'busqueda';

        if ($searchTerm === '' || mb_strlen($searchTerm) < 3) {
            abort(404, 'La búsqueda es demasiado corta.');
        }

        $isMapView = $request->query('view') === 'map';

        if ($searchPath !== $expectedSearchPath || $query !== $canonicalQuery) {
            return redirect()->route('property.semantic-search', array_filter([
                'locale' => $locale,
                'country' => PropertySlugHelper::normalize($countryName),
                'searchPath' => $expectedSearchPath,
                'query' => $canonicalQuery,
                'view' => $isMapView ? 'map' : null,
            ]), 301);
        }

        $page = (int) $request->integer('page', 1);

        // Variante unificada desde el admin: 301 a la búsqueda principal
        if ($redirectSlug = SearchQuery::redirectSlugFor($countryName, $canonicalQuery)) {
            return redirect()->route('property.semantic-search', array_filter([
                'locale' => $locale,
                'country' => PropertySlugHelper::normalize($countryName),
                'searchPath' => $expectedSearchPath,
                'query' => $redirectSlug,
                'page' => $page > 1 ? $page : null,
                'view' => $isMapView ? 'map' : null,
            ]), 301);
        }

        if ($isMapView) {
            return $this->showMap($request, $locale, $countryName, $searchTerm, $canonicalQuery, $expectedSearchPath);
        }

        $properties = $this->searchService->search($searchTerm, $countryName);

        $savedSearch = SearchQuery::findAndRefresh($countryName, $searchTerm, $properties->total());

        $canonicalUrl = route('property.semantic-search', [
            'locale' => $locale,
            'country' => PropertySlugHelper::normalize($countryName),
            'searchPath' => $expectedSearchPath,
            'query' => $canonicalQuery,
        ]);

        if ($page > 1) {
            $canonicalUrl .= '?page=' . $page;
        }

        $alternateLocale = $locale === 'es' ? 'en' : 'es';
        $alternateSearchPath = $alternateLocale === 'en' ? 'search' : 'busqueda';
        $alternateUrl = route('property.semantic-search', [
            'locale' => $alternateLocale,
            'country' => PropertySlugHelper::normalize($countryName),
            'searchPath' => $alternateSearchPath,
            'query' => $canonicalQuery,
        ]);

        if ($page > 1) {
            $alternateUrl .= '?page=' . $page;
        }

        $title = __('seo.semantic_search.title', [
            'query' => Str::title($searchTerm),
            'country' => $countryName,
        ]);
        $description = __('seo.semantic_search.description', [
            'query' => $searchTerm,
            'country' => $countryName,
            'count' => number_format($properties->total()),
        ]);
        $isIndexable = $properties->total() > 0
            && (! config('search.noindex_inactive') || $savedSearch?->active);
        $robots = $isIndexable ? 'index,follow' : 'noindex,follow';

        $countryCode = PropertySlugHelper::getCountryCode($countryName);

        $seo = [
            'title' => $title,
            'description' => Str::limit($description, 160),
            'image' => url('/og_image.png'),
            'type' => 'website',
            'canonical' => $canonicalUrl,
            'robots' => $robots,
            'hreflang_tags' => [
                ['rel' => 'alternate', 'hreflang' => SeoLocaleHelper::getLanguageTag($locale, $countryCode), 'href' => $canonicalUrl],
                ['rel' => 'alternate', 'hreflang' => SeoLocaleHelper::getLanguageTag($alternateLocale, $countryCode), 'href' => $alternateUrl],
                ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $locale === 'es' ? $canonicalUrl : $alternateUrl],
            ],
            'html_lang' => SeoLocaleHelper::getLanguageTag($locale, $countryCode),
            'og_locale' => SeoLocaleHelper::getOpenGraphLocale($locale, $countryCode),
            'og_alternate_locales' => [SeoLocaleHelper::getOpenGraphLocale($alternateLocale, $countryCode)],
            'structured_data' => $this->structuredData($properties, $title),
        ];

        return view('semantic-property-search', [
            'properties' => $properties,
            'country' => $countryName,
            'searchTerm' => $searchTerm,
            'relatedSearches' => SearchQuery::relatedLinks($countryName, $canonicalQuery, $locale),
            'seo' => $seo,
            'breadcrumbs' => [
                ['label' => __('messages.home'), 'url' => route('home', ['locale' => $locale])],
                ['label' => $countryName, 'url' => route('property.listings', [
                    'locale' => $locale,
                    'country' => PropertySlugHelper::normalize($countryName),
                ])],
                ['label' => Str::title($searchTerm), 'url' => null],
            ],
            'locale' => $locale,
            // Botón del mapa si algún resultado de la página tiene ubicación (sin consultas extra)
            'mapUrl' => $properties->contains(fn ($property): bool => (float) $property->latitude != 0 && (float) $property->longitude != 0)
                ? $canonicalUrl . (str_contains($canonicalUrl, '?') ? '&' : '?') . 'view=map'
                : null,
        ]);
    }

    /**
     * Vista de mapa con todos los resultados de la búsqueda (misma vista que los listados), sin indexar.
     */
    private function showMap(
        Request $request,
        string $locale,
        string $countryName,
        string $searchTerm,
        string $canonicalQuery,
        string $searchPath,
    ): View {
        [$query] = $this->searchService->mapQuery($searchTerm, $countryName);
        $total = (clone $query)->count();
        $mappableCount = $this->listingMap->onlyMappable(clone $query)->count();
        $countrySlug = PropertySlugHelper::normalize($countryName);

        $searchUrl = route('property.semantic-search', [
            'locale' => $locale,
            'country' => $countrySlug,
            'searchPath' => $searchPath,
            'query' => $canonicalQuery,
        ]);

        $seo = [
            'title' => __('properties.map_view.page_title', [
                'title' => Str::title($searchTerm) . ' ' . __('properties.in') . ' ' . $countryName,
            ]),
            'description' => Str::limit(__('seo.semantic_search.description', [
                'query' => $searchTerm,
                'country' => $countryName,
                'count' => number_format($total),
            ]), 160),
            'image' => url('/og_image.png'),
            'type' => 'website',
            'canonical' => $searchUrl,
            'robots' => 'noindex,follow',
        ];

        // Otras búsquedas del país, también en el mapa
        $relatedSearches = SearchQuery::relatedLinks($countryName, $canonicalQuery, $locale)
            ->map(fn (array $link): array => [...$link, 'url' => $link['url'] . '?view=map', 'count' => null])
            ->all();

        return view('property-listing-map', [
            'seo' => $seo,
            'breadcrumbs' => [
                ['label' => __('messages.home'), 'url' => route('home', ['locale' => $locale])],
                ['label' => $countryName, 'url' => route('property.listings', ['locale' => $locale, 'country' => $countrySlug]) . '?view=map'],
                ['label' => Str::title($searchTerm), 'url' => $searchUrl],
                ['label' => __('properties.map_view.show_map'), 'url' => null],
            ],
            'navigationSections' => $relatedSearches
                ? [['title' => __('properties.search_results.other_searches'), 'items' => $relatedSearches]]
                : [],
            'total' => $total,
            'mappableCount' => $mappableCount,
            'listUrl' => $searchUrl,
            'mapConfig' => [
                'dataUrl' => route('property.semantic-search.map-data', [
                    'locale' => $locale,
                    'country' => $countrySlug,
                    'query' => $canonicalQuery,
                ]),
                'params' => [],
                'center' => $this->listingMap->parseMapCenter($request),
                'locale' => $locale,
            ],
            'locale' => $locale,
        ]);
    }

    /**
     * Endpoint JSON con los resultados ubicables de la búsqueda (mismo formato que los listados SEO).
     */
    public function mapData(Request $request, string $locale, string $country, string $query): JsonResponse
    {
        App::setLocale($locale);

        $countryName = PropertySlugHelper::validateCountry($country);
        $searchTerm = Str::of($query)->replace('-', ' ')->squish()->lower()->toString();

        if (! $countryName || mb_strlen($searchTerm) < 3) {
            abort(404);
        }

        [$listings, $applyOrder] = $this->searchService->mapQuery($searchTerm, $countryName);

        return $this->listingMap->response(
            $listings,
            $request,
            $locale,
            $applyOrder,
            'map_data_search_' . md5(json_encode([$locale, $countryName, $searchTerm]))
        );
    }

    private function structuredData($properties, string $title): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $title,
            'numberOfItems' => $properties->count(),
            'itemListElement' => $properties->values()->map(function ($property, int $index): array {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $this->seoService->generatePropertyUrl($property, app()->getLocale()),
                    'name' => $property->getTranslation('title', app()->getLocale()) ?: $property->title,
                ];
            })->all(),
        ];
    }
}
