<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Services\PropertyMatchingService;
use Illuminate\Http\Request;

class PropertyMatchController extends Controller
{
    protected $matchingService;

    public function __construct(PropertyMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Show matches for user's listings.
     */
    public function index()
    {
        $listings = PropertyListing::where('user_id', auth()->id())
            ->active()
            ->get();

        $allMatches = collect();

        foreach ($listings as $listing) {
            $matches = $this->matchingService->findMatchesForListing($listing, 5);
            
            if ($matches->isNotEmpty()) {
                $allMatches->push([
                    'listing' => $listing,
                    'matches' => $matches
                ]);
            }
        }

        return view('theme::pages.dashboard.matches.index', compact('allMatches'));
    }

    /**
     * Show matches for a specific listing.
     */
    public function show(PropertyListing $listing)
    {
        // Verificar que el usuario sea el dueño
        if ($listing->user_id !== auth()->id()) {
            abort(403);
        }

        $matches = $this->matchingService->findMatchesForListing($listing, 20);

        return view('theme::pages.dashboard.matches.show', compact('listing', 'matches'));
    }
}
