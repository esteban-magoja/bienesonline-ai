<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Services\PropertyMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PropertyMatchController extends Controller
{
    protected $matchingService;

    public function __construct(PropertyMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Show matches for user's listings.
     * Limitado a 10 anuncios y cacheado 15 minutos para evitar N búsquedas vectoriales.
     */
    public function index()
    {
        $userId = auth()->id();

        $allMatches = Cache::remember("matches_index_{$userId}", 900, function () use ($userId) {
            $listings = PropertyListing::where('user_id', $userId)
                ->active()
                ->latest()
                ->limit(10)
                ->get();

            $result = collect();

            foreach ($listings as $listing) {
                $matches = $this->matchingService->findMatchesForListing($listing, 5);

                if ($matches->isNotEmpty()) {
                    $result->push([
                        'listing' => $listing,
                        'matches' => $matches
                    ]);
                }
            }

            return $result;
        });

        return view('theme::pages.dashboard.matches.index', compact('allMatches'));
    }

    /**
     * Show matches for a specific listing.
     */
    public function show(PropertyListing $listing)
    {
        if ($listing->user_id !== auth()->id()) {
            abort(403);
        }

        $matches = Cache::remember("matches_listing_{$listing->id}", 900, function () use ($listing) {
            return $this->matchingService->findMatchesForListing($listing, 20);
        });

        return view('theme::pages.dashboard.matches.show', compact('listing', 'matches'));
    }
}
