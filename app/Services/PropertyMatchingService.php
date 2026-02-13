<?php

namespace App\Services;

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\PropertyType;
use App\Models\TransactionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Nnjeim\World\Models\Country;
use Pgvector\Laravel\Distance;

class PropertyMatchingService
{
    /**
     * Find matching listings for a property request.
     *
     * @param PropertyRequest $request
     * @param int $limit
     * @return Collection
     */
    public function findMatchesForRequest(PropertyRequest $request, int $limit = 20): Collection
    {
        $matches = collect();

        // 1. Exact matches (filtros tradicionales)
        $exactMatches = $this->getExactMatches($request);
        
        // 2. Semantic matches (embeddings) si tiene embedding
        if ($request->embedding) {
            $semanticMatches = $this->getSemanticMatches($request, $limit);
            
            // Combinar y rankear
            $matches = $this->mergeAndRank($exactMatches, $semanticMatches);
        } else {
            $matches = $exactMatches;
        }

        // 3. Agregar nivel de match y score
        return $matches->take($limit)->map(function ($listing, $index) use ($request) {
            $matchData = $this->calculateMatchLevel($listing, $request);
            $listing->match_level = $matchData['level'];
            $listing->match_score = $matchData['score'];
            $listing->match_details = $matchData['details'];
            return $listing;
        });
    }

    /**
     * Find matching requests for a property listing.
     *
     * @param PropertyListing $listing
     * @param int $limit
     * @return Collection
     */
    public function findMatchesForListing(PropertyListing $listing, int $limit = 20): Collection
    {
        $matches = collect();

        // 1. Exact matches
        $exactMatches = $this->getExactMatchesForListing($listing);
        
        // 2. Semantic matches si tiene embedding
        if ($listing->embedding) {
            $semanticMatches = $this->getSemanticMatchesForListing($listing, $limit);
            
            // Combinar y rankear
            $matches = $this->mergeAndRankRequests($exactMatches, $semanticMatches);
        } else {
            $matches = $exactMatches;
        }

        return $matches->take($limit)->map(function ($requestItem, $index) use ($listing) {
            $matchData = $this->calculateMatchLevelForListing($requestItem, $listing);
            $requestItem->match_level = $matchData['level'];
            $requestItem->match_score = $matchData['score'];
            $requestItem->match_details = $matchData['details'];
            return $requestItem;
        });
    }

    /**
     * Get exact matches using traditional filters.
     *
     * @param PropertyRequest $request
     * @return Collection
     */
    protected function getExactMatches(PropertyRequest $request): Collection
    {
        // Obtener valores equivalentes del tipo de propiedad y transacción
        $countryCode = $this->getCountryCode($request->country);
        $propertyEquivalents = PropertyType::getEquivalentValues($request->property_type, $countryCode);
        $transactionEquivalents = TransactionType::getEquivalentValues($request->transaction_type, $countryCode);

        $query = PropertyListing::active()
            ->whereIn('property_type', $propertyEquivalents)
            ->whereIn('transaction_type', $transactionEquivalents)
            ->where('country', $request->country);

        // Precio dentro del presupuesto
        if ($request->min_budget) {
            $query->where('price', '>=', $request->min_budget);
        }
        $query->where('price', '<=', $request->max_budget);

        // Ciudad si está especificada
        if ($request->city) {
            $query->where(function($q) use ($request) {
                $q->where('city', $request->city)
                  ->orWhere('state', $request->state);
            });
        }

        // Habitaciones mínimas
        if ($request->min_bedrooms) {
            $query->where('bedrooms', '>=', $request->min_bedrooms);
        }

        // Baños mínimos
        if ($request->min_bathrooms) {
            $query->where('bathrooms', '>=', $request->min_bathrooms);
        }

        // Cocheras mínimas
        if ($request->min_parking_spaces) {
            $query->where('parking_spaces', '>=', $request->min_parking_spaces);
        }

        // Área mínima
        if ($request->min_area) {
            $query->where('area', '>=', $request->min_area);
        }

        return $query->with(['user', 'primaryImage'])->get();
    }

    /**
     * Get semantic matches using embeddings.
     *
     * @param PropertyRequest $request
     * @param int $limit
     * @return Collection
     */
    protected function getSemanticMatches(PropertyRequest $request, int $limit): Collection
    {
        return PropertyListing::active()
            ->where('country', $request->country)
            ->nearestNeighbors('embedding', $request->embedding, Distance::Cosine)
            ->limit($limit * 2)
            ->with(['user', 'primaryImage'])
            ->get()
            ->filter(function($listing) {
                // Filtrar solo los que tienen un buen score de similitud
                return $listing->neighbor_distance !== null;
            });
    }

    /**
     * Get exact matches for a listing.
     *
     * @param PropertyListing $listing
     * @return Collection
     */
    protected function getExactMatchesForListing(PropertyListing $listing): Collection
    {
        // Obtener valores equivalentes del tipo de propiedad y transacción
        $countryCode = $this->getCountryCode($listing->country);
        $propertyEquivalents = PropertyType::getEquivalentValues($listing->property_type, $countryCode);
        $transactionEquivalents = TransactionType::getEquivalentValues($listing->transaction_type, $countryCode);

        $query = PropertyRequest::active()
            ->whereIn('property_type', $propertyEquivalents)
            ->whereIn('transaction_type', $transactionEquivalents)
            ->where('country', $listing->country);

        // Precio dentro del presupuesto
        $query->where('max_budget', '>=', $listing->price);
        $query->where(function($q) use ($listing) {
            $q->whereNull('min_budget')
              ->orWhere('min_budget', '<=', $listing->price);
        });

        // Ciudad o estado
        $query->where(function($q) use ($listing) {
            $q->whereNull('city')
              ->orWhere('city', $listing->city)
              ->orWhere('state', $listing->state);
        });

        return $query->with('user')->get();
    }

    /**
     * Get semantic matches for a listing.
     *
     * @param PropertyListing $listing
     * @param int $limit
     * @return Collection
     */
    protected function getSemanticMatchesForListing(PropertyListing $listing, int $limit): Collection
    {
        return PropertyRequest::active()
            ->where('country', $listing->country)
            ->nearestNeighbors('embedding', $listing->embedding, Distance::Cosine)
            ->limit($limit * 2)
            ->with('user')
            ->get()
            ->filter(function($request) {
                return $request->neighbor_distance !== null;
            });
    }

    /**
     * Merge and rank matches.
     *
     * @param Collection $exactMatches
     * @param Collection $semanticMatches
     * @return Collection
     */
    protected function mergeAndRank(Collection $exactMatches, Collection $semanticMatches): Collection
    {
        // Los exact matches tienen prioridad
        $exactIds = $exactMatches->pluck('id');
        
        // Agregar semantic matches que no estén en exact
        $additionalMatches = $semanticMatches->filter(function($listing) use ($exactIds) {
            return !$exactIds->contains($listing->id);
        });

        return $exactMatches->concat($additionalMatches);
    }

    /**
     * Merge and rank request matches.
     *
     * @param Collection $exactMatches
     * @param Collection $semanticMatches
     * @return Collection
     */
    protected function mergeAndRankRequests(Collection $exactMatches, Collection $semanticMatches): Collection
    {
        $exactIds = $exactMatches->pluck('id');
        
        $additionalMatches = $semanticMatches->filter(function($request) use ($exactIds) {
            return !$exactIds->contains($request->id);
        });

        return $exactMatches->concat($additionalMatches);
    }

    /**
     * Calculate match level and score.
     *
     * @param PropertyListing $listing
     * @param PropertyRequest $request
     * @return array
     */
    protected function calculateMatchLevel(PropertyListing $listing, PropertyRequest $request): array
    {
        $score = 0;
        $details = [];
        $level = 'flexible';

        // Tipo de propiedad (25 puntos)
        if ($listing->property_type === $request->property_type) {
            $score += 25;
            $details[] = 'Tipo de propiedad coincide';
        }

        // Tipo de transacción (25 puntos)
        if ($listing->transaction_type === $request->transaction_type) {
            $score += 25;
            $details[] = 'Tipo de operación coincide';
        }

        // Precio dentro del presupuesto (20 puntos)
        if ($listing->price >= ($request->min_budget ?? 0) && $listing->price <= $request->max_budget) {
            $score += 20;
            $details[] = 'Precio dentro del presupuesto';
        }

        // Ubicación (15 puntos)
        if ($listing->city === $request->city) {
            $score += 15;
            $details[] = 'Ciudad coincide';
        } elseif ($listing->state === $request->state) {
            $score += 10;
            $details[] = 'Provincia coincide';
        } elseif ($listing->country === $request->country) {
            $score += 5;
            $details[] = 'País coincide';
        }

        // Características (5 puntos cada una)
        if ($request->min_bedrooms && $listing->bedrooms >= $request->min_bedrooms) {
            $score += 5;
            $details[] = 'Habitaciones suficientes';
        }

        if ($request->min_bathrooms && $listing->bathrooms >= $request->min_bathrooms) {
            $score += 5;
            $details[] = 'Baños suficientes';
        }

        if ($request->min_area && $listing->area >= $request->min_area) {
            $score += 5;
            $details[] = 'Área suficiente';
        }

        // Determinar nivel de match
        if ($score >= 85) {
            $level = 'exact';
        } elseif ($score >= 60) {
            $level = 'semantic';
        }

        return [
            'level' => $level,
            'score' => $score,
            'details' => $details
        ];
    }

    /**
     * Calculate match level for listing.
     *
     * @param PropertyRequest $request
     * @param PropertyListing $listing
     * @return array
     */
    protected function calculateMatchLevelForListing(PropertyRequest $request, PropertyListing $listing): array
    {
        return $this->calculateMatchLevel($listing, $request);
    }

    /**
     * Get ISO2 country code from country name.
     *
     * @param string $countryName
     * @return string
     */
    protected function getCountryCode(string $countryName): string
    {
        try {
            $country = Country::where('name', $countryName)->first();
            return $country ? $country->iso2 : 'INTL';
        } catch (\Exception $e) {
            Log::warning("Could not find country code for: {$countryName}");
            return 'INTL';
        }
    }
}
