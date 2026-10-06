<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Models\SearchQuery;
use App\Services\SemanticPropertySearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertySearchController extends Controller
{
    private const SEARCH_TERM_MAX_LENGTH = 100;

    private const SEARCH_TERM_MAX_WORDS = 12;

    /** Proporción mínima de letras sobre los caracteres que no son espacios */
    private const SEARCH_TERM_MIN_LETTER_RATIO = 0.5;

    public function __construct(private readonly SemanticPropertySearchService $searchService)
    {
    }

    public function index(Request $request, string $locale): View
    {
        $startTime = microtime(true);
        
        // Bots envían arrays u otros tipos en los parámetros: solo se aceptan strings
        $searchTerm = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $selectedCountry = is_string($request->query('country')) ? $request->query('country') : '';
        
        // Check if this is a search request (has search parameters)
        $isSearchRequest = $request->has('search') || $request->has('country');
        
        // Validation - only validate if user is actually searching
        $validationErrors = [];
        $hasValidSearch = false;
        
        // Get available countries
        $countries = PropertyListing::distinct('country')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->where('is_active', true)
            ->pluck('country')
            ->sort()
            ->values()
            ->toArray();

        if ($isSearchRequest) {
            // Both country and search term are required when searching
            if (empty($selectedCountry)) {
                $validationErrors[] = __('messages.validation.country_required');
            } elseif (! in_array($selectedCountry, $countries, true)) {
                $validationErrors[] = __('messages.validation.country_invalid');
                $selectedCountry = '';
            }

            if (empty($searchTerm)) {
                $validationErrors[] = __('messages.validation.search_term_required');
            } elseif (mb_strlen($searchTerm) < 5) {
                $validationErrors[] = __('messages.validation.search_term_min', ['min' => 5]);
            } elseif ($error = $this->searchTermError($searchTerm)) {
                $validationErrors[] = $error;
            }

            // Only proceed if both validations pass
            if (empty($validationErrors)) {
                $hasValidSearch = true;
            }
        }

        $properties = collect();

        if ($hasValidSearch && empty($validationErrors)) {
            $properties = $this->searchService->search($searchTerm, $selectedCountry);
        }

        $searchTime = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds
        $totalResults = $properties instanceof LengthAwarePaginator
            ? $properties->total()
            : $properties->count();

        if ($hasValidSearch) {
            SearchQuery::record($selectedCountry, $searchTerm, $totalResults);
        }

        // SEO data
        $seo = (object) [
            'title' => __('seo.property_search.title'),
            'description' => __('seo.property_search.description'),
            'image' => url('/og_image.png'),
            'type' => 'website',
            'canonical' => route_localized('property.search', [], $locale),
            'hreflang_tags' => [
                ['rel' => 'alternate', 'hreflang' => 'es', 'href' => route_localized('property.search', [], 'es')],
                ['rel' => 'alternate', 'hreflang' => 'en', 'href' => route_localized('property.search', [], 'en')],
                ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => route_localized('property.search', [], 'es')],
            ],
            'og_locale' => $locale === 'es' ? 'es_ES' : 'en_US',
            'og_alternate_locales' => $locale === 'es' ? ['en_US'] : ['es_ES'],
        ];

        return view('property-search', [
            'properties' => $properties,
            'searchTerm' => $searchTerm,
            'selectedCountry' => $selectedCountry,
            'countries' => $countries,
            'totalResults' => $totalResults,
            'searchTime' => $searchTime,
            'validationErrors' => $validationErrors,
            'hasValidSearch' => $hasValidSearch,
            'isSearchRequest' => $isSearchRequest,
            'seo' => $seo,
        ]);
    }

    /**
     * Rechaza textos que claramente no son una búsqueda inmobiliaria (spam, URLs, código).
     */
    private function searchTermError(string $searchTerm): ?string
    {
        if (mb_strlen($searchTerm) > self::SEARCH_TERM_MAX_LENGTH) {
            return __('messages.validation.search_term_max', ['max' => self::SEARCH_TERM_MAX_LENGTH]);
        }

        $nonSpaceLength = mb_strlen(preg_replace('/\s+/u', '', $searchTerm));
        $letterCount = preg_match_all('/\p{L}/u', $searchTerm);

        $isInvalid = count(preg_split('/\s+/u', $searchTerm)) > self::SEARCH_TERM_MAX_WORDS
            || preg_match('~(https?:|www\.|://|@|\.(com|net|org|info|ru|cn|xyz|top)\b)~i', $searchTerm)
            || preg_match('/[<>{}\[\];=\\\\|`$^*]/', $searchTerm)
            || preg_match('/([^\p{N}\s])\1{3,}/u', $searchTerm)
            || $letterCount < self::SEARCH_TERM_MIN_LETTER_RATIO * $nonSpaceLength
            || preg_match_all('/\p{L}{3,}/u', $searchTerm) < 2;

        return $isInvalid ? __('messages.validation.search_term_invalid') : null;
    }
}
