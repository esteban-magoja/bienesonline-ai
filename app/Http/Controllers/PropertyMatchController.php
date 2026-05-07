<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Services\PropertyMatchingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PropertyMatchController extends Controller
{
    public function __construct(protected PropertyMatchingService $matchingService) { }

    /**
     * Show all user listings that have matching requests.
     * Uses a single JOIN query (no pgvector) and paginates from cached collection.
     */
    public function index()
    {
        $userId = auth()->id();
        $perPage = 10;
        $page    = (int) request()->input('page', 1);

        $listings = Cache::remember("matches_index_{$userId}", 3600, function () use ($userId) {
            return PropertyListing::select('property_listings.*')
                ->selectRaw('COUNT(property_requests.id) AS match_count')
                ->join('property_requests', function ($join) {
                    $join->whereRaw('LOWER(property_requests.property_type) = LOWER(property_listings.property_type)')
                         ->whereRaw('LOWER(property_requests.transaction_type) = LOWER(property_listings.transaction_type)')
                         ->whereColumn('property_requests.country', 'property_listings.country')
                         ->where('property_requests.is_active', true)
                         ->where(function ($q) {
                             $q->whereNull('property_requests.expires_at')
                               ->orWhere('property_requests.expires_at', '>', now());
                         })
                         ->where(function ($q) {
                             $q->whereNull('property_requests.max_budget')
                               ->orWhere('property_requests.max_budget', 0)
                               ->orWhereColumn('property_requests.max_budget', '>=', 'property_listings.price');
                         })
                         ->where(function ($q) {
                             $q->whereNull('property_requests.min_budget')
                               ->orWhereColumn('property_requests.min_budget', '<=', 'property_listings.price');
                         });
                })
                ->where('property_listings.user_id', $userId)
                ->where('property_listings.is_active', true)
                ->groupBy('property_listings.id')
                ->havingRaw('COUNT(property_requests.id) > 0')
                ->orderByDesc('match_count')
                ->get();
        });

        $paginated = new LengthAwarePaginator(
            $listings->forPage($page, $perPage),
            $listings->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('theme::pages.dashboard.matches.index', ['allMatches' => $paginated]);
    }

    /**
     * Show full pgvector-powered matches for a specific listing.
     */
    public function show(PropertyListing $listing)
    {
        if ($listing->user_id !== auth()->id()) {
            abort(403);
        }

        $matches = Cache::remember("matches_listing_{$listing->id}", 3600, function () use ($listing) {
            return $this->matchingService->findMatchesForListing($listing, 20);
        });

        $totalMatches = Cache::remember("matches_listing_count_{$listing->id}", 3600, function () use ($listing) {
            return $this->matchingService->countMatchesForListing($listing);
        });

        return view('theme::pages.dashboard.matches.show', compact('listing', 'matches', 'totalMatches'));
    }
}
