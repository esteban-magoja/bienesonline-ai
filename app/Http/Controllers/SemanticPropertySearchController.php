<?php

namespace App\Http\Controllers;

use App\Helpers\PropertySlugHelper;
use App\Services\SemanticPropertySearchService;
use App\Services\SeoService;
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

        if ($searchPath !== $expectedSearchPath || $query !== $canonicalQuery) {
            return redirect()->route('property.semantic-search', [
                'locale' => $locale,
                'country' => PropertySlugHelper::normalize($countryName),
                'searchPath' => $expectedSearchPath,
                'query' => $canonicalQuery,
            ], 301);
        }

        $properties = $this->searchService->search($searchTerm, $countryName);
        $canonicalUrl = route('property.semantic-search', [
            'locale' => $locale,
            'country' => PropertySlugHelper::normalize($countryName),
            'searchPath' => $expectedSearchPath,
            'query' => $canonicalQuery,
        ]);
        $page = (int) $request->integer('page', 1);

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
        $robots = $properties->total() > 0 ? 'index,follow' : 'noindex,follow';

        $seo = [
            'title' => $title,
            'description' => Str::limit($description, 160),
            'image' => url('/og_image.png'),
            'type' => 'website',
            'canonical' => $canonicalUrl,
            'robots' => $robots,
            'hreflang_tags' => [
                ['rel' => 'alternate', 'hreflang' => $locale, 'href' => $canonicalUrl],
                ['rel' => 'alternate', 'hreflang' => $alternateLocale, 'href' => $alternateUrl],
                ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $locale === 'es' ? $canonicalUrl : $alternateUrl],
            ],
            'og_locale' => $locale === 'es' ? 'es_ES' : 'en_US',
            'og_alternate_locales' => [$locale === 'es' ? 'en_US' : 'es_ES'],
            'structured_data' => $this->structuredData($properties, $title),
        ];

        return view('semantic-property-search', [
            'properties' => $properties,
            'country' => $countryName,
            'searchTerm' => $searchTerm,
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
        ]);
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
