<?php

namespace App\Http\Controllers;

use App\Helpers\PropertySlugHelper;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Services\SeoService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    /**
     * Operational ceiling for property sitemap URLs.
     *
     * Google allows up to 50,000 URLs, but these sitemap entries also include
     * hreflang alternates and image tags, so we keep a much lower cap to stay
     * comfortably below the XML file size limit in production.
     */
    private const PROPERTY_SITEMAP_URL_LIMIT = 5000;

    /**
     * Main sitemap index — dynamically lists all child sitemaps including paginated property ones.
     */
    public function index(): Response
    {
        $lastPropUpdate = PropertyListing::active()->latest('updated_at')->value('updated_at');
        $lastPropMod    = $lastPropUpdate ? $lastPropUpdate->toW3cString() : now()->toW3cString();
        $lastAgentUpdate = DB::table('property_listings as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->where('pl.is_active', true)
            ->whereNotNull('u.agency')
            ->whereRaw("TRIM(u.agency) != ''")
            ->latest('pl.updated_at')
            ->value('pl.updated_at');
        $lastAgentMod = $lastAgentUpdate ? \Carbon\Carbon::parse($lastAgentUpdate)->toW3cString() : $lastPropMod;
        $totalActive    = PropertyListing::active()->count();
        $totalPages     = max(1, (int) ceil($totalActive / self::PROPERTY_SITEMAP_URL_LIMIT));

        $sitemaps = [
            ['loc' => url('/sitemap-pages.xml'), 'lastmod' => now()->toW3cString()],
        ];

        foreach (['es', 'en'] as $locale) {
            for ($page = 1; $page <= $totalPages; $page++) {
                $sitemaps[] = [
                    'loc'     => url("/sitemap-properties-{$locale}-{$page}.xml"),
                    'lastmod' => $lastPropMod,
                ];
            }
            $sitemaps[] = ['loc' => url("/sitemap-listings-{$locale}.xml"), 'lastmod' => $lastPropMod];
            $sitemaps[] = ['loc' => url("/sitemap-agents-{$locale}.xml"), 'lastmod' => $lastAgentMod];
        }

        $sitemaps[] = ['loc' => url('/sitemap-profiles.xml'), 'lastmod' => $lastPropMod];

        return response()
            ->view('sitemap.index', compact('sitemaps'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Static pages sitemap (home, search, pricing, login, signup).
     */
    public function pages(): Response
    {
        $locales = ['es', 'en'];
        $pages   = [];

        $staticPages = [
            ['path' => '',                   'changefreq' => 'daily',   'priority' => '1.0', 'age' => 0],
            ['path' => '/search-properties', 'changefreq' => 'hourly',  'priority' => '0.9', 'age' => 0],
            ['path' => '/search-requests',   'changefreq' => 'hourly',  'priority' => '0.8', 'age' => 0],
            ['path' => '/pricing',           'changefreq' => 'weekly',  'priority' => '0.8', 'age' => 7],
            ['path' => '/login',             'changefreq' => 'monthly', 'priority' => '0.5', 'age' => 30],
            ['path' => '/signup',            'changefreq' => 'monthly', 'priority' => '0.5', 'age' => 30],
        ];

        foreach ($staticPages as $page) {
            $alts = [];
            foreach ($locales as $l) {
                $alts[$l] = url("/{$l}{$page['path']}");
            }
            foreach ($locales as $locale) {
                $pages[] = [
                    'loc'        => url("/{$locale}{$page['path']}"),
                    'lastmod'    => now()->subDays($page['age'])->toW3cString(),
                    'changefreq' => $page['changefreq'],
                    'priority'   => $page['priority'],
                    'alternates' => $alts,
                ];
            }
        }

        return response()
            ->view('sitemap.pages', compact('pages'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Individual property pages sitemap — paginated, streamed to avoid memory exhaustion.
     * Each page contains up to PROPERTY_SITEMAP_URL_LIMIT URLs.
     */
    public function properties(string $locale, int $page = 1): StreamedResponse
    {
        if (!in_array($locale, ['es', 'en']) || $page < 1) {
            abort(404);
        }

        $perPage = self::PROPERTY_SITEMAP_URL_LIMIT;
        $offset  = ($page - 1) * $perPage;
        $total   = PropertyListing::active()->count();

        if ($total === 0 || ($page > 1 && $offset >= $total)) {
            abort(404);
        }

        // Lightweight query: only fetch the IDs for this page window.
        $ids = PropertyListing::active()
            ->select('id')
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->pluck('id')
            ->toArray();

        $seoService = app(SeoService::class);

        return response()->stream(function () use ($locale, $seoService, $ids) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
            echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml"' . "\n";
            echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

            // Process 200 at a time — eager-load primaryImage per batch.
            foreach (array_chunk($ids, 200) as $chunkIds) {
                $properties = PropertyListing::with(['primaryImage'])
                    ->select(['id', 'title', 'is_featured', 'updated_at', 'country', 'city'])
                    ->whereIn('id', $chunkIds)
                    ->orderBy('id')
                    ->get();

                foreach ($properties as $property) {
                    $locEs = $seoService->generatePropertyUrl($property, 'es');
                    $locEn = $seoService->generatePropertyUrl($property, 'en');
                    $loc   = $locale === 'es' ? $locEs : $locEn;

                    echo "    <url>\n";
                    echo '        <loc>' . e($loc) . "</loc>\n";
                    echo '        <lastmod>' . $property->updated_at->toW3cString() . "</lastmod>\n";
                    echo "        <changefreq>weekly</changefreq>\n";
                    echo '        <priority>' . ($property->is_featured ? '0.9' : '0.7') . "</priority>\n";
                    echo '        <xhtml:link rel="alternate" hreflang="es" href="' . e($locEs) . '" />' . "\n";
                    echo '        <xhtml:link rel="alternate" hreflang="en" href="' . e($locEn) . '" />' . "\n";
                    echo '        <xhtml:link rel="alternate" hreflang="x-default" href="' . e($locEs) . '" />' . "\n";

                    $imageUrl = $this->normalizeSitemapImageUrl($property->primaryImage?->image_url);
                    if ($imageUrl) {
                        echo "        <image:image>\n";
                        echo '            <image:loc>' . e($imageUrl) . "</image:loc>\n";
                        echo '            <image:title>' . e($property->title) . "</image:title>\n";
                        echo "        </image:image>\n";
                    }

                    echo "    </url>\n";
                }

                unset($properties);
            }

            echo "</urlset>\n";
        }, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Listing/filter pages sitemap: country, operation, type combinations.
     * These are the SEO-friendly landing pages like /es/argentina/venta/casas.
     */
    public function listings(string $locale): Response
    {
        if (!in_array($locale, ['es', 'en'])) {
            abort(404);
        }

        $urls = Cache::remember("sitemap_listings_{$locale}", 3600, function () use ($locale) {
            return $this->buildListingUrls($locale);
        });

        return response()
            ->view('sitemap.listings', compact('urls'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Agent directory sitemap: /{locale}/inmobiliarias and /{locale}/{country}/inmobiliarias/{state?}/{city?}.
     */
    public function agents(string $locale): Response
    {
        if (!in_array($locale, ['es', 'en'])) {
            abort(404);
        }

        $urls = Cache::remember("sitemap_agents_{$locale}", 3600, function () use ($locale) {
            return $this->buildAgentDirectoryUrls($locale);
        });

        return response()
            ->view('sitemap.listings', compact('urls'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * User/agency profile pages sitemap.
     */
    public function profiles(): Response
    {
        $pages = Cache::remember('sitemap_profiles', 3600, function () {
            return DB::table('users as u')
                ->join('property_listings as pl', function ($j) {
                    $j->on('pl.user_id', '=', 'u.id')->where('pl.is_active', true);
                })
                ->select('u.username', DB::raw('MAX(pl.updated_at) as last_updated'))
                ->whereNotNull('u.username')
                ->where('u.username', '!=', '')
                ->groupBy('u.id', 'u.username')
                ->get()
                ->map(function ($user) {
                    $lastmod = $user->last_updated
                        ? \Carbon\Carbon::parse($user->last_updated)->toW3cString()
                        : now()->toW3cString();
                    return [
                        'loc'        => url("/es/inmobiliaria/{$user->username}"),
                        'lastmod'    => $lastmod,
                        'changefreq' => 'weekly',
                        'priority'   => '0.6',
                        'alternates' => [
                            'es' => url("/es/inmobiliaria/{$user->username}"),
                            'en' => url("/en/realtor/{$user->username}"),
                        ],
                    ];
                })
                ->toArray();
        });

        return response()
            ->view('sitemap.pages', compact('pages'))
            ->header('Content-Type', 'application/xml');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build all listing-page URLs for a given locale from actual DB combinations.
     */
    private function buildListingUrls(string $locale): array
    {
        $altLocale = $locale === 'es' ? 'en' : 'es';

        // Load all type tables once to avoid N+1 lookups
        $allTransactions = TransactionType::all();
        $allPropertyTypes = PropertyType::all();

        // Distinct (country, transaction_type, property_type) with latest update date
        $combos = DB::table('property_listings')
            ->where('is_active', true)
            ->whereNotNull('country')
            ->whereNotNull('transaction_type')
            ->whereNotNull('property_type')
            ->select(
                'country',
                'transaction_type',
                'property_type',
                DB::raw('MAX(updated_at) as last_updated'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('country', 'transaction_type', 'property_type')
            ->get();

        $urls = [];
        $seen = [];

        foreach ($combos as $combo) {
            $countrySlug = Str::slug($combo->country);
            if (!$countrySlug) continue;

            $countryCode  = PropertySlugHelper::getCountryCode($combo->country) ?? 'INTL';
            $lastmod      = $combo->last_updated
                ? \Carbon\Carbon::parse($combo->last_updated)->toW3cString()
                : now()->toW3cString();

            // Slugs for both locales
            $transSlugEs  = $this->transactionSlug($combo->transaction_type, $countryCode, 'es', $allTransactions);
            $transSlugEn  = $this->transactionSlug($combo->transaction_type, $countryCode, 'en', $allTransactions);
            $typeSlugEs   = $this->propertyTypeSlug($combo->property_type,   $countryCode, 'es', $allPropertyTypes);
            $typeSlugEn   = $this->propertyTypeSlug($combo->property_type,   $countryCode, 'en', $allPropertyTypes);

            $transSlug    = $locale === 'es' ? $transSlugEs : $transSlugEn;
            $typeSlug     = $locale === 'es' ? $typeSlugEs  : $typeSlugEn;
            $altTransSlug = $locale === 'es' ? $transSlugEn : $transSlugEs;
            $altTypeSlug  = $locale === 'es' ? $typeSlugEn  : $typeSlugEs;

            // Level 1 — country index
            $this->addListingUrl($urls, $seen,
                "/{$locale}/{$countrySlug}",
                "/{$altLocale}/{$countrySlug}",
                $lastmod, 'daily', '0.8'
            );

            if (!$transSlug) continue;

            // Level 2 — country + operation
            $this->addListingUrl($urls, $seen,
                "/{$locale}/{$countrySlug}/{$transSlug}",
                "/{$altLocale}/{$countrySlug}/{$altTransSlug}",
                $lastmod, 'daily', '0.7'
            );

            if (!$typeSlug) continue;

            // Level 3 — country + operation + type
            $this->addListingUrl($urls, $seen,
                "/{$locale}/{$countrySlug}/{$transSlug}/{$typeSlug}",
                "/{$altLocale}/{$countrySlug}/{$altTransSlug}/{$altTypeSlug}",
                $lastmod, 'weekly', '0.6'
            );
        }

        // Also add city-level pages for cities with multiple listings
        $cityLevelCombos = DB::table('property_listings')
            ->where('is_active', true)
            ->whereNotNull('country')
            ->whereNotNull('transaction_type')
            ->whereNotNull('property_type')
            ->whereNotNull('city')
            ->select(
                'country', 'transaction_type', 'property_type', 'city',
                DB::raw('MAX(updated_at) as last_updated'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('country', 'transaction_type', 'property_type', 'city')
            ->havingRaw('COUNT(*) >= 1')
            ->get();

        foreach ($cityLevelCombos as $combo) {
            $countrySlug = Str::slug($combo->country);
            $citySlug    = Str::slug($combo->city);
            if (!$countrySlug || !$citySlug) continue;

            $countryCode  = PropertySlugHelper::getCountryCode($combo->country) ?? 'INTL';
            $lastmod      = $combo->last_updated
                ? \Carbon\Carbon::parse($combo->last_updated)->toW3cString()
                : now()->toW3cString();

            $transSlugEs  = $this->transactionSlug($combo->transaction_type, $countryCode, 'es', $allTransactions);
            $transSlugEn  = $this->transactionSlug($combo->transaction_type, $countryCode, 'en', $allTransactions);
            $typeSlugEs   = $this->propertyTypeSlug($combo->property_type,   $countryCode, 'es', $allPropertyTypes);
            $typeSlugEn   = $this->propertyTypeSlug($combo->property_type,   $countryCode, 'en', $allPropertyTypes);

            $transSlug    = $locale === 'es' ? $transSlugEs : $transSlugEn;
            $typeSlug     = $locale === 'es' ? $typeSlugEs  : $typeSlugEn;
            $altTransSlug = $locale === 'es' ? $transSlugEn : $transSlugEs;
            $altTypeSlug  = $locale === 'es' ? $typeSlugEn  : $typeSlugEs;

            if (!$transSlug || !$typeSlug) continue;

            // Level 4 — country + operation + type + city
            $this->addListingUrl($urls, $seen,
                "/{$locale}/{$countrySlug}/{$transSlug}/{$typeSlug}/{$citySlug}",
                "/{$altLocale}/{$countrySlug}/{$altTransSlug}/{$altTypeSlug}/{$citySlug}",
                $lastmod, 'weekly', '0.5'
            );
        }

        return $urls;
    }

    /**
     * Build all public agent directory URLs for a locale.
     */
    private function buildAgentDirectoryUrls(string $locale): array
    {
        $altLocale = $locale === 'es' ? 'en' : 'es';
        $segment = $locale === 'es' ? 'inmobiliarias' : 'agents';
        $altSegment = $locale === 'es' ? 'agents' : 'inmobiliarias';

        $latestUpdated = DB::table('property_listings as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->where('pl.is_active', true)
            ->whereNotNull('u.agency')
            ->whereRaw("TRIM(u.agency) != ''")
            ->latest('pl.updated_at')
            ->value('pl.updated_at');

        $defaultLastmod = $latestUpdated ? \Carbon\Carbon::parse($latestUpdated)->toW3cString() : now()->toW3cString();
        $urls = [];
        $seen = [];

        $this->addListingUrl(
            $urls,
            $seen,
            "/{$locale}/{$segment}",
            "/{$altLocale}/{$altSegment}",
            $defaultLastmod,
            'daily',
            '0.7'
        );

        $countries = DB::table('property_listings as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->where('pl.is_active', true)
            ->whereNotNull('u.agency')
            ->whereRaw("TRIM(u.agency) != ''")
            ->whereNotNull('pl.country')
            ->whereRaw("TRIM(pl.country) != ''")
            ->selectRaw('TRIM(pl.country) as country, MAX(pl.updated_at) as last_updated')
            ->groupByRaw('TRIM(pl.country)')
            ->get();

        foreach ($countries as $country) {
            $countrySlug = PropertySlugHelper::normalize($country->country);
            if (!$countrySlug) {
                continue;
            }

            $lastmod = $country->last_updated
                ? \Carbon\Carbon::parse($country->last_updated)->toW3cString()
                : $defaultLastmod;

            $this->addListingUrl(
                $urls,
                $seen,
                "/{$locale}/{$countrySlug}/{$segment}",
                "/{$altLocale}/{$countrySlug}/{$altSegment}",
                $lastmod,
                'weekly',
                '0.6'
            );
        }

        $states = DB::table('property_listings as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->where('pl.is_active', true)
            ->whereNotNull('u.agency')
            ->whereRaw("TRIM(u.agency) != ''")
            ->whereNotNull('pl.country')
            ->whereRaw("TRIM(pl.country) != ''")
            ->whereNotNull('pl.state')
            ->whereRaw("TRIM(pl.state) != ''")
            ->selectRaw('TRIM(pl.country) as country, TRIM(pl.state) as state, MAX(pl.updated_at) as last_updated')
            ->groupByRaw('TRIM(pl.country), TRIM(pl.state)')
            ->get();

        foreach ($states as $state) {
            $countrySlug = PropertySlugHelper::normalize($state->country);
            $stateSlug = PropertySlugHelper::normalize($state->state);
            if (!$countrySlug || !$stateSlug) {
                continue;
            }

            $lastmod = $state->last_updated
                ? \Carbon\Carbon::parse($state->last_updated)->toW3cString()
                : $defaultLastmod;

            $this->addListingUrl(
                $urls,
                $seen,
                "/{$locale}/{$countrySlug}/{$segment}/{$stateSlug}",
                "/{$altLocale}/{$countrySlug}/{$altSegment}/{$stateSlug}",
                $lastmod,
                'weekly',
                '0.5'
            );
        }

        $cities = DB::table('property_listings as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->where('pl.is_active', true)
            ->whereNotNull('u.agency')
            ->whereRaw("TRIM(u.agency) != ''")
            ->whereNotNull('pl.country')
            ->whereRaw("TRIM(pl.country) != ''")
            ->whereNotNull('pl.state')
            ->whereRaw("TRIM(pl.state) != ''")
            ->whereNotNull('pl.city')
            ->whereRaw("TRIM(pl.city) != ''")
            ->selectRaw('TRIM(pl.country) as country, TRIM(pl.state) as state, TRIM(pl.city) as city, MAX(pl.updated_at) as last_updated')
            ->groupByRaw('TRIM(pl.country), TRIM(pl.state), TRIM(pl.city)')
            ->get();

        foreach ($cities as $city) {
            $countrySlug = PropertySlugHelper::normalize($city->country);
            $stateSlug = PropertySlugHelper::normalize($city->state);
            $citySlug = PropertySlugHelper::normalize($city->city);
            if (!$countrySlug || !$stateSlug || !$citySlug) {
                continue;
            }

            $lastmod = $city->last_updated
                ? \Carbon\Carbon::parse($city->last_updated)->toW3cString()
                : $defaultLastmod;

            $this->addListingUrl(
                $urls,
                $seen,
                "/{$locale}/{$countrySlug}/{$segment}/{$stateSlug}/{$citySlug}",
                "/{$altLocale}/{$countrySlug}/{$altSegment}/{$stateSlug}/{$citySlug}",
                $lastmod,
                'weekly',
                '0.4'
            );
        }

        return $urls;
    }

    /**
     * Add a URL entry only if not already seen (deduplication).
     */
    private function addListingUrl(
        array &$urls,
        array &$seen,
        string $path,
        string $altPath,
        string $lastmod,
        string $changefreq,
        string $priority
    ): void {
        if (isset($seen[$path])) return;
        $seen[$path] = true;

        $altLocaleKey = str_starts_with($path, '/es/') ? 'en' : 'es';
        $curLocaleKey = $altLocaleKey === 'es' ? 'en' : 'es';

        $urls[] = [
            'loc'        => url($path),
            'lastmod'    => $lastmod,
            'changefreq' => $changefreq,
            'priority'   => $priority,
            'alternates' => [
                $curLocaleKey => url($path),
                $altLocaleKey => url($altPath),
            ],
        ];
    }

    /**
     * Return the locale-specific slug for a transaction type value.
     */
    private function transactionSlug(string $value, string $countryCode, string $locale, $allTypes): string
    {
        $normalized = strtolower(trim($value));

        // Find matching type (country-specific first, then INTL)
        $type = $allTypes
            ->filter(fn($t) => strtolower($t->value) === $normalized)
            ->sortBy(fn($t) => $t->country_code === $countryCode ? 0 : 1)
            ->first();

        if (!$type) {
            // Fallback: use value itself as slug
            return Str::slug($normalized);
        }

        return $locale === 'en' ? Str::slug($type->value_en) : Str::slug($type->value);
    }

    /**
     * Return the locale-specific slug for a property type value.
     */
    private function propertyTypeSlug(string $value, string $countryCode, string $locale, $allTypes): string
    {
        $normalized = strtolower(trim($value));

        $type = $allTypes
            ->filter(fn($t) => strtolower($t->value) === $normalized)
            ->sortBy(fn($t) => $t->country_code === $countryCode ? 0 : 1)
            ->first();

        if (!$type) {
            return Str::slug($normalized);
        }

        return $locale === 'en' ? Str::slug($type->value_en) : Str::slug($type->value);
    }

    private function normalizeSitemapImageUrl(?string $imageUrl): ?string
    {
        if (!$imageUrl) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $imageUrl)) {
            return $imageUrl;
        }

        return url('/' . ltrim($imageUrl, '/'));
    }
}
