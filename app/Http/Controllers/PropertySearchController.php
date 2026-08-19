<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Services\SemanticPropertySearchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertySearchController extends Controller
{
    public function __construct(private readonly SemanticPropertySearchService $searchService)
    {
    }

    public function index(Request $request, string $locale): View
    {
        $startTime = microtime(true);
        
        $searchTerm = trim($request->get('search', ''));
        $selectedCountry = $request->get('country', '');
        
        // Check if this is a search request (has search parameters)
        $isSearchRequest = $request->has('search') || $request->has('country');
        
        // Validation - only validate if user is actually searching
        $validationErrors = [];
        $hasValidSearch = false;
        
        if ($isSearchRequest) {
            // Both country and search term are required when searching
            if (empty($selectedCountry)) {
                $validationErrors[] = __('messages.validation.country_required');
            }
            
            if (empty($searchTerm)) {
                $validationErrors[] = __('messages.validation.search_term_required');
            } elseif (strlen($searchTerm) < 5) {
                $validationErrors[] = __('messages.validation.search_term_min', ['min' => 5]);
            }
            
            // Only proceed if both validations pass
            if (empty($validationErrors)) {
                $hasValidSearch = true;
            }
        }
        
        // Get available countries
        $countries = PropertyListing::distinct('country')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->where('is_active', true)
            ->pluck('country')
            ->sort()
            ->values()
            ->toArray();

        $properties = collect();

        if ($hasValidSearch && empty($validationErrors)) {
            $properties = $this->searchService->search($searchTerm, $selectedCountry);
        }

        $searchTime = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds
        $totalResults = $properties instanceof LengthAwarePaginator
            ? $properties->total()
            : $properties->count();

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
}
