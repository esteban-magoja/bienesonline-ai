<?php

namespace App\Http\Controllers;

use App\Models\PropertyRequest;
use App\Services\EmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestSearchController extends Controller
{
    public function __construct(private EmbeddingService $embeddingService) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        
        $startTime = microtime(true);
        
        $searchTerm = trim($request->get('search', ''));
        $selectedCountry = $request->get('country', '');
        
        // Check if this is a search request
        $isSearchRequest = $request->has('search') || $request->has('country');
        
        // Validation errors array
        $validationErrors = [];
        
        // Get unique countries from property requests
        $countries = PropertyRequest::where('is_active', true)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->sort()
            ->values();
        
        // Validation
        if ($isSearchRequest) {
            if (empty($selectedCountry)) {
                $validationErrors[] = 'Debes seleccionar un país.';
            }
            
            if (!empty($searchTerm) && strlen($searchTerm) < 5) {
                $validationErrors[] = 'El término de búsqueda debe tener al menos 5 caracteres.';
            }
            
            if (empty($searchTerm)) {
                $validationErrors[] = 'Debes escribir un término de búsqueda.';
            }
        }
        
        $requests = collect();
        $totalResults = 0;
        $searchTime = 0;
        
        // Only search if validation passes
        if ($isSearchRequest && empty($validationErrors)) {
            $query = PropertyRequest::where('is_active', true)
                ->where('user_id', '!=', auth()->id()) // Excluir solicitudes propias
                ->where('country', $selectedCountry);
            
            // Si hay término de búsqueda, usar búsqueda vectorial
            if (!empty($searchTerm)) {
                try {
                    // Generar embedding para el término de búsqueda
                    $searchEmbedding = $this->embeddingService->generate([$searchTerm]);
                    
                    if ($searchEmbedding !== null) {
                        // Convertir array a string de PostgreSQL vector format
                        $embeddingString = '[' . implode(',', $searchEmbedding->toArray()) . ']';
                        
                        // Búsqueda por similitud usando pgvector
                        $requests = $query
                            ->selectRaw("*, (embedding <=> ?::vector) * -1 + 1 as similarity_raw", [$embeddingString])
                            ->whereNotNull('embedding')
                            ->orderByRaw("embedding <=> ?::vector", [$embeddingString])
                            ->limit(50)
                            ->get()
                            ->map(function ($request) {
                                // Convertir similitud a porcentaje (0-100)
                                $request->similarity = max(0, min(100, $request->similarity_raw * 100));
                                return $request;
                            })
                            ->filter(function ($request) {
                                // Filtrar resultados con similitud muy baja
                                return $request->similarity >= 20;
                            });
                        
                        $totalResults = $requests->count();
                    } else {
                        // Fallback: búsqueda simple por país
                        $requests = $query->latest()->paginate(20);
                        $totalResults = $requests->total();
                    }
                } catch (\Exception $e) {
                    Log::error('Error in request search: ' . $e->getMessage());
                    // Fallback: búsqueda simple por país
                    $requests = $query->latest()->paginate(20);
                    $totalResults = $requests->total();
                }
            } else {
                // Solo filtrar por país
                $requests = $query->latest()->paginate(20);
                $totalResults = $requests->total();
            }
            
            $searchTime = round((microtime(true) - $startTime) * 1000);
        }
        
        // SEO data
        $locale = app()->getLocale();
        $seo = (object) [
            'title' => __('seo.request_search.title'),
            'description' => __('seo.request_search.description'),
            'image' => url('/og_image.png'),
            'type' => 'website',
            'canonical' => route_localized('requests.search', [], $locale),
            'hreflang_tags' => [
                ['rel' => 'alternate', 'hreflang' => 'es', 'href' => route_localized('requests.search', [], 'es')],
                ['rel' => 'alternate', 'hreflang' => 'en', 'href' => route_localized('requests.search', [], 'en')],
                ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => route_localized('requests.search', [], 'es')],
            ],
            'og_locale' => $locale === 'es' ? 'es_ES' : 'en_US',
            'og_alternate_locales' => $locale === 'es' ? ['en_US'] : ['es_ES'],
        ];
        
        return view('request-search', [
            'requests' => $requests,
            'countries' => $countries,
            'searchTerm' => $searchTerm,
            'selectedCountry' => $selectedCountry,
            'totalResults' => $totalResults,
            'searchTime' => $searchTime,
            'validationErrors' => $validationErrors,
            'isSearchRequest' => $isSearchRequest,
            'seo' => $seo,
        ]);
    }
    
}
