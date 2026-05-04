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
        $user = auth()->user();
        $canView = $user->hasRole('admin') || $user->hasRole('premium');

        if (!$canView) {
            return view('theme::pages.dashboard.matches.index', [
                'canView' => false,
                'allMatches' => collect(),
            ]);
        }

        $userId = $user->id;

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

        return view('theme::pages.dashboard.matches.index', compact('allMatches') + ['canView' => true]);
    }

    /**
     * Show matches for a specific listing.
     */
    public function show(PropertyListing $listing)
    {
        $user = auth()->user();
        $canView = $user->hasRole('admin') || $user->hasRole('premium');

        if (!$canView) {
            return view('theme::pages.dashboard.matches.show', [
                'canView' => false,
                'listing' => $listing,
                'matches' => collect(),
            ]);
        }

        if ($listing->user_id !== $user->id) {
            abort(403);
        }

        $matches = Cache::remember("matches_listing_{$listing->id}", 900, function () use ($listing) {
            return $this->matchingService->findMatchesForListing($listing, 20);
        });

        $totalMatches = Cache::remember("matches_listing_count_{$listing->id}", 900, function () use ($listing) {
            return $this->matchingService->countMatchesForListing($listing);
        });

        return view('theme::pages.dashboard.matches.show', compact('listing', 'matches', 'totalMatches') + ['canView' => true]);
    }
}
