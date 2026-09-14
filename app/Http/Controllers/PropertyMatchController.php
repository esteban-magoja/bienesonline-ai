<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Services\PropertyMatchingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PropertyMatchController extends Controller
{
    private const MATCHES_PER_PAGE = 10;

    public function __construct(protected PropertyMatchingService $matchingService) { }

    /**
     * Show all user listings that have matching requests.
     * Paginates at DB level to avoid loading all listings into memory.
     * Never selects the `embedding` column.
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $userId = auth()->id();

        $allMatches = $this->buildMatchesBaseQuery($userId)
            ->with(['primaryImage', 'firstImage'])
            ->paginate(10)
            ->withQueryString();

        $summary = Cache::remember("matches_summary_{$userId}", 3600, function () use ($userId) {
            return $this->computeSummary($userId);
        });

        return view('theme::pages.dashboard.matches.index', [
            'allMatches' => $allMatches,
            'summary'    => $summary,
        ]);
    }

    /**
     * Build the base query for matches index: specific columns only (no embedding),
     * JOIN with property_requests, grouped by listing id.
     */
    private function buildMatchesBaseQuery(int $userId): Builder
    {
        return PropertyListing::select(
                'property_listings.id',
                'property_listings.user_id',
                'property_listings.title',
                'property_listings.property_type',
                'property_listings.transaction_type',
                'property_listings.price',
                'property_listings.currency',
                'property_listings.city',
                'property_listings.state',
                'property_listings.country',
                'property_listings.is_active',
            )
            ->selectRaw('COUNT(DISTINCT COALESCE(property_requests.client_email, property_requests.id::text)) AS match_count')
            ->selectRaw("COUNT(DISTINCT COALESCE(property_requests.client_email, property_requests.id::text)) FILTER (WHERE property_requests.created_at > NOW() - INTERVAL '24 hours') AS new_today_count")
            ->selectRaw("COUNT(DISTINCT COALESCE(property_requests.client_email, property_requests.id::text)) FILTER (WHERE property_requests.created_at > NOW() - INTERVAL '7 days') AS new_match_count")
            ->selectRaw('MAX(property_requests.created_at) AS latest_match_at')
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
                     })
                     ->where(function ($q) {
                         $q->whereNull('property_requests.city')
                           ->orWhereColumn('property_requests.city', 'property_listings.city')
                           ->orWhereColumn('property_requests.state', 'property_listings.state');
                     });
            })
            ->where('property_listings.user_id', $userId)
            ->where('property_listings.is_active', true)
            ->groupBy('property_listings.id')
            ->havingRaw('COUNT(DISTINCT COALESCE(property_requests.client_email, property_requests.id::text)) > 0')
            ->orderByDesc('latest_match_at');
    }

    /**
     * Compute summary stats using a single aggregate SQL subquery.
     *
     * @return array{total_matches: int, new_this_week: int, listings_count: int}
     */
    private function computeSummary(int $userId): array
    {
        $result = DB::selectOne("
            SELECT
                COALESCE(SUM(match_count), 0)::int     AS total_matches,
                COALESCE(SUM(new_match_count), 0)::int AS new_this_week,
                COUNT(*)::int                          AS listings_count
            FROM (
                SELECT
                    COUNT(DISTINCT COALESCE(pr.client_email, pr.id::text)) AS match_count,
                    COUNT(DISTINCT COALESCE(pr.client_email, pr.id::text))
                        FILTER (WHERE pr.created_at > NOW() - INTERVAL '7 days') AS new_match_count
                FROM property_listings pl
                JOIN property_requests pr
                    ON LOWER(pr.property_type) = LOWER(pl.property_type)
                   AND LOWER(pr.transaction_type) = LOWER(pl.transaction_type)
                   AND pr.country = pl.country
                   AND pr.is_active = true
                   AND (pr.expires_at IS NULL OR pr.expires_at > NOW())
                   AND (pr.max_budget IS NULL OR pr.max_budget = 0 OR pr.max_budget >= pl.price)
                   AND (pr.min_budget IS NULL OR pr.min_budget <= pl.price)
                   AND (pr.city IS NULL OR pr.city = pl.city OR pr.state = pl.state)
                WHERE pl.user_id = ? AND pl.is_active = true
                GROUP BY pl.id
                HAVING COUNT(DISTINCT COALESCE(pr.client_email, pr.id::text)) > 0
            ) sub
        ", [$userId]);

        return [
            'total_matches'  => (int) ($result->total_matches ?? 0),
            'new_this_week'  => (int) ($result->new_this_week ?? 0),
            'listings_count' => (int) ($result->listings_count ?? 0),
        ];
    }

    /**
     * Show full pgvector-powered matches for a specific listing.
     */
    public function show(Request $request, PropertyListing $listing): \Illuminate\Contracts\View\View
    {
        if ($listing->user_id !== auth()->id()) {
            abort(403);
        }

        $allMatches = Cache::remember("matches_listing_all_{$listing->id}", 3600, function () use ($listing) {
            return $this->matchingService->getAllMatchesForListing($listing)
                ->each(fn ($r) => $r->makeHidden('embedding'));
        });

        $recentThreshold = now()->subDays(7);
        $sort = $this->resolveSort($request);
        $matches = $this->paginateMatches($this->sortMatches($allMatches, $sort), $request);

        return view('theme::pages.dashboard.matches.show', [
            'listing'          => $listing,
            'matches'          => $matches,
            'sort'             => $sort,
            'recentThreshold'  => $recentThreshold,
        ]);
    }

    private function resolveSort(Request $request): string
    {
        $sort = $request->query('sort', 'newest');

        return in_array($sort, ['newest', 'score'], true) ? $sort : 'newest';
    }

    private function sortMatches(Collection $matches, string $sort): Collection
    {
        return $matches
            ->sortByDesc(function (PropertyRequest $match) use ($sort): array {
                $createdAt = $match->created_at?->timestamp ?? 0;

                return $sort === 'score'
                    ? [(int) $match->match_score, $createdAt, (int) $match->id]
                    : [$createdAt, (int) $match->id];
            })
            ->values();
    }

    private function paginateMatches(Collection $matches, Request $request): LengthAwarePaginator
    {
        $page = max($request->integer('page', 1), 1);

        return new LengthAwarePaginatorInstance(
            $matches->forPage($page, self::MATCHES_PER_PAGE)->values(),
            $matches->count(),
            self::MATCHES_PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }
}
