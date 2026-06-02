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
        return $this->getAllScoredMatchesForRequest($request)
            ->take($limit);
    }

    /**
     * Count all matches for a request without applying a display limit.
     */
    public function countMatchesForRequest(PropertyRequest $request): int
    {
        return $this->countExactMatchesForRequest($request);
    }

    /**
     * Fast SQL-only count of matching listings for a request (no vector search, no score calculation).
     * Suitable for dashboard indicators where approximate counts are acceptable.
     */
    public function countExactMatchesForRequest(PropertyRequest $request): int
    {
        $countryCode            = $this->getCountryCode($request->country);
        $propertyEquivalents    = PropertyType::getEquivalentValues($request->property_type, $countryCode);
        $transactionEquivalents = TransactionType::getEquivalentValues($request->transaction_type, $countryCode);

        $propPlaceholders = implode(',', array_fill(0, count($propertyEquivalents), '?'));
        $tranPlaceholders = implode(',', array_fill(0, count($transactionEquivalents), '?'));

        $query = PropertyListing::active()
            ->whereRaw("LOWER(property_type) IN ({$propPlaceholders})", $propertyEquivalents)
            ->whereRaw("LOWER(transaction_type) IN ({$tranPlaceholders})", $transactionEquivalents)
            ->where('country', $request->country);

        if ($request->min_budget) {
            $query->where('price', '>=', $request->min_budget);
        }
        if ($request->max_budget) {
            $query->where('price', '<=', $request->max_budget);
        }
        if ($request->city) {
            $query->where(function ($q) use ($request) {
                $q->where('city', $request->city)->orWhere('state', $request->state);
            });
        }
        if ($request->min_bedrooms) {
            $query->where('bedrooms', '>=', $request->min_bedrooms);
        }
        if ($request->min_bathrooms) {
            $query->where('bathrooms', '>=', $request->min_bathrooms);
        }
        if ($request->min_parking_spaces) {
            $query->where('parking_spaces', '>=', $request->min_parking_spaces);
        }
        if ($request->min_area) {
            $query->where('area', '>=', $request->min_area);
        }

        return $query->count();
    }

    /**
     * Get all scored and sorted matches for a request (no limit applied).
     */
    protected function getAllScoredMatchesForRequest(PropertyRequest $request): Collection
    {
        // 1. Exact matches (filtros tradicionales)
        $exactMatches = $this->getExactMatches($request);

        // 2. Semantic matches (embeddings) si tiene embedding
        if ($request->embedding) {
            $semanticMatches = $this->getSemanticMatches($request, 100);
            $matches = $this->mergeAndRank($exactMatches, $semanticMatches);
        } else {
            $matches = $exactMatches;
        }

        // 3. Calcular score para todos, filtrar < 50% y ordenar
        return $matches
            ->map(function ($listing) use ($request) {
                $matchData = $this->calculateMatchLevel($listing, $request);
                $listing->match_level = $matchData['level'];
                $listing->match_score = $matchData['score'];
                $listing->match_details = $matchData['details'];
                return $listing;
            })
            ->filter(fn ($listing) => $listing->match_score >= 50)
            ->sortByDesc('match_score')
            ->values();
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
        return $this->getAllScoredMatchesForListing($listing)->take($limit);
    }

    /**
     * Count all matches for a listing without applying a display limit.
     */
    public function countMatchesForListing(PropertyListing $listing): int
    {
        return $this->getAllScoredMatchesForListing($listing)->count();
    }

    /**
     * Fast SQL-only count of matching requests for a listing (no vector search, no score calculation).
     * Suitable for dashboard indicators where approximate counts are acceptable.
     */
    public function countExactMatchesForListing(PropertyListing $listing): int
    {
        $countryCode            = $this->getCountryCode($listing->country);
        $propertyEquivalents    = PropertyType::getEquivalentValues($listing->property_type, $countryCode);
        $transactionEquivalents = TransactionType::getEquivalentValues($listing->transaction_type, $countryCode);

        $propPlaceholders = implode(',', array_fill(0, count($propertyEquivalents), '?'));
        $tranPlaceholders = implode(',', array_fill(0, count($transactionEquivalents), '?'));

        return PropertyRequest::active()
            ->whereRaw("LOWER(property_type) IN ({$propPlaceholders})", $propertyEquivalents)
            ->whereRaw("LOWER(transaction_type) IN ({$tranPlaceholders})", $transactionEquivalents)
            ->where('country', $listing->country)
            ->where(function ($q) use ($listing) {
                $q->whereNull('max_budget')
                  ->orWhere('max_budget', '=', 0)
                  ->orWhere('max_budget', '>=', $listing->price);
            })
            ->where(function ($q) use ($listing) {
                $q->whereNull('min_budget')
                  ->orWhere('min_budget', '<=', $listing->price);
            })
            ->where(function ($q) use ($listing) {
                $q->whereNull('city')
                  ->orWhere('city', $listing->city)
                  ->orWhere('state', $listing->state);
            })
            ->count();
    }

    /**
     * Get all scored and sorted matches for a listing (no limit applied).
     */
    protected function getAllScoredMatchesForListing(PropertyListing $listing): Collection
    {
        // 1. Exact matches
        $exactMatches = $this->getExactMatchesForListing($listing);

        // 2. Semantic matches si tiene embedding
        if ($listing->embedding) {
            $semanticMatches = $this->getSemanticMatchesForListing($listing, 100);
            $matches = $this->mergeAndRankRequests($exactMatches, $semanticMatches);
        } else {
            $matches = $exactMatches;
        }

        // 3. Calcular score para todos, filtrar < 50% y ordenar
        return $matches
            ->map(function ($requestItem) use ($listing) {
                $matchData = $this->calculateMatchLevelForListing($requestItem, $listing);
                $requestItem->match_level = $matchData['level'];
                $requestItem->match_score = $matchData['score'];
                $requestItem->match_details = $matchData['details'];
                return $requestItem;
            })
            ->filter(fn ($requestItem) => $requestItem->match_score >= 50)
            ->sortByDesc('match_score')
            ->unique(fn ($r) => $r->client_email ?? $r->id)
            ->values();
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

        $propPlaceholders = implode(',', array_fill(0, count($propertyEquivalents), '?'));
        $tranPlaceholders = implode(',', array_fill(0, count($transactionEquivalents), '?'));

        $query = PropertyListing::active()
            ->whereRaw("LOWER(property_type) IN ({$propPlaceholders})", $propertyEquivalents)
            ->whereRaw("LOWER(transaction_type) IN ({$tranPlaceholders})", $transactionEquivalents)
            ->where('country', $request->country);

        // Precio dentro del presupuesto
        if ($request->min_budget) {
            $query->where('price', '>=', $request->min_budget);
        }
        if ($request->max_budget) {
            $query->where('price', '<=', $request->max_budget);
        }

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

        return $query
            ->select([
                'id', 'user_id', 'title', 'property_type', 'transaction_type',
                'price', 'currency', 'bedrooms', 'bathrooms', 'parking_spaces',
                'area', 'city', 'state', 'country', 'is_active',
            ])
            ->with(['user', 'primaryImage', 'firstImage'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();
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
        $countryCode          = $this->getCountryCode($request->country);
        $propertyEquivalents  = PropertyType::getEquivalentValues($request->property_type, $countryCode);
        $transactionEquivalents = TransactionType::getEquivalentValues($request->transaction_type, $countryCode);

        $propPlaceholders  = implode(',', array_fill(0, count($propertyEquivalents), '?'));
        $tranPlaceholders  = implode(',', array_fill(0, count($transactionEquivalents), '?'));

        return PropertyListing::active()
            ->where('country', $request->country)
            ->whereRaw("LOWER(property_type) IN ({$propPlaceholders})", $propertyEquivalents)
            ->whereRaw("LOWER(transaction_type) IN ({$tranPlaceholders})", $transactionEquivalents)
            ->nearestNeighbors('embedding', $request->embedding, Distance::Cosine)
            ->limit($limit * 2)
            ->with(['user', 'primaryImage', 'firstImage'])
            ->get()
            ->filter(function($listing) {
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

        $propPlaceholders = implode(',', array_fill(0, count($propertyEquivalents), '?'));
        $tranPlaceholders = implode(',', array_fill(0, count($transactionEquivalents), '?'));

        $query = PropertyRequest::active()
            ->whereRaw("LOWER(property_type) IN ({$propPlaceholders})", $propertyEquivalents)
            ->whereRaw("LOWER(transaction_type) IN ({$tranPlaceholders})", $transactionEquivalents)
            ->where('country', $listing->country);

        // Precio dentro del presupuesto — max_budget NULL o 0 significa sin límite superior (registros legacy)
        $query->where(function ($q) use ($listing) {
            $q->whereNull('max_budget')
              ->orWhere('max_budget', '=', 0)
              ->orWhere('max_budget', '>=', $listing->price);
        });
        $query->where(function ($q) use ($listing) {
            $q->whereNull('min_budget')
              ->orWhere('min_budget', '<=', $listing->price);
        });

        // Ciudad o estado
        $query->where(function($q) use ($listing) {
            $q->whereNull('city')
              ->orWhere('city', $listing->city)
              ->orWhere('state', $listing->state);
        });

        return $query->with('user')->orderByDesc('id')->get()
            ->unique(fn ($r) => $r->client_email ?? $r->id)
            ->values();
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
        $countryCode          = $this->getCountryCode($listing->country);
        $propertyEquivalents  = PropertyType::getEquivalentValues($listing->property_type, $countryCode);
        $transactionEquivalents = TransactionType::getEquivalentValues($listing->transaction_type, $countryCode);

        $propPlaceholders  = implode(',', array_fill(0, count($propertyEquivalents), '?'));
        $tranPlaceholders  = implode(',', array_fill(0, count($transactionEquivalents), '?'));

        return PropertyRequest::active()
            ->where('country', $listing->country)
            ->whereRaw("LOWER(property_type) IN ({$propPlaceholders})", $propertyEquivalents)
            ->whereRaw("LOWER(transaction_type) IN ({$tranPlaceholders})", $transactionEquivalents)
            ->nearestNeighbors('embedding', $listing->embedding, Distance::Cosine)
            ->limit($limit * 2)
            ->with('user')
            ->get()
            ->filter(function($request) {
                return $request->neighbor_distance !== null;
            })
            ->unique(fn ($r) => $r->client_email ?? $r->id)
            ->values();
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
     * Scoring breakdown (max 100 pts):
     *   25 — tipo de propiedad
     *   25 — tipo de transacción
     *   20 — precio dentro del presupuesto
     *   15 — ubicación (ciudad=15, provincia=10, país=5)
     *    5 — habitaciones mínimas
     *    5 — baños mínimos
     *    5 — área mínima
     *   15 — similitud semántica (cosine similarity, bonus)
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

        $countryCode = $this->getCountryCode($listing->country);

        // Tipo de propiedad (25 puntos) — normalizado vía value_en para soportar variaciones regionales
        $listingPropertyEn = PropertyType::getValueEn($listing->property_type, $countryCode) ?? strtolower($listing->property_type);
        $requestPropertyEn = PropertyType::getValueEn($request->property_type, $countryCode) ?? strtolower($request->property_type);
        if ($listingPropertyEn === $requestPropertyEn) {
            $score += 25;
            $details[] = 'Tipo de propiedad coincide';
        }

        // Tipo de transacción (25 puntos) — normalizado vía value_en
        $listingTransactionEn = TransactionType::getValueEn($listing->transaction_type, $countryCode) ?? strtolower($listing->transaction_type);
        $requestTransactionEn = TransactionType::getValueEn($request->transaction_type, $countryCode) ?? strtolower($request->transaction_type);
        if ($listingTransactionEn === $requestTransactionEn) {
            $score += 25;
            $details[] = 'Tipo de operación coincide';
        }

        // Precio dentro del presupuesto (20 puntos)
        // Solo comparar si las monedas coinciden para evitar falsos positivos
        if ($listing->currency === $request->currency) {
            $aboveMin = $listing->price >= ($request->min_budget ?? 0);
            $belowMax = $request->max_budget === null || $request->max_budget == 0 || $listing->price <= $request->max_budget;
            if ($aboveMin && $belowMax) {
                $score += 20;
                $details[] = 'Precio dentro del presupuesto';
            }
        }

        // Ubicación (25 puntos máx — acumulativos, no excluyentes)
        if ($listing->city && $request->city && strcasecmp((string) $listing->city, (string) $request->city) === 0) {
            $score += 15;
            $details[] = 'Ciudad coincide';
        }
        if ($listing->state && $request->state && strcasecmp((string) $listing->state, (string) $request->state) === 0) {
            $score += 10;
            $details[] = 'Provincia coincide';
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

        // Similitud semántica (hasta 15 puntos bonus)
        if ($listing->embedding && $request->embedding) {
            $similarity = $this->cosineSimilarity(
                $listing->embedding->toArray(),
                $request->embedding->toArray()
            );
            $semanticBonus = (int) round($similarity * 15);
            if ($semanticBonus > 0) {
                $score += $semanticBonus;
                $details[] = sprintf('Similitud semántica: %.0f%%', $similarity * 100);
            }
        }

        // Capear a 100 (el bonus semántico puede elevar por encima)
        $score = min(100, $score);

        // Determinar nivel de match
        if ($score >= 85) {
            $level = 'exact';
        } elseif ($score >= 70) {
            $level = 'semantic';
        }

        return [
            'level' => $level,
            'score' => $score,
            'details' => $details
        ];
    }

    /**
     * Calculate cosine similarity between two vectors.
     * OpenAI embeddings are unit-normalized, so this equals the dot product.
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float Similarity between 0.0 and 1.0
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return (float) ($dot / (sqrt($normA) * sqrt($normB)));
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
