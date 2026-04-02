<?php

namespace App\Http\Controllers;

use App\Helpers\PropertySlugHelper;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Services\SeoService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SitemapController extends Controller
{
    /**
     * Main sitemap index — lists all child sitemaps.
     */
    public function index(): Response
    {
        $lastPropUpdate = PropertyListing::active()->latest('updated_at')->value('updated_at');
        $lastPropMod    = $lastPropUpdate ? $lastPropUpdate->toW3cString() : now()->toW3cString();

        $sitemaps = [
            ['loc' => url('/sitemap-pages.xml'),          'lastmod' => now()->toW3cString()],
            ['loc' => url('/sitemap-properties-es.xml'),  'lastmod' => $lastPropMod],
            ['loc' => url('/sitemap-properties-en.xml'),  'lastmod' => $lastPropMod],
            ['loc' => url('/sitemap-listings-es.xml'),    'lastmod' => $lastPropMod],
            ['loc' => url('/sitemap-listings-en.xml'),    'lastmod' => $lastPropMod],
            ['loc' => url('/sitemap-profiles.xml'),       'lastmod' => $lastPropMod],
        ];

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
     * Individual property pages sitemap, with correct SEO URLs and images.
     */
    public function properties(string $locale): Response
    {
        if (!in_array($locale, ['es', 'en'])) {
            abort(404);
        }

        $seoService = app(SeoService::class);

        $properties = Cache::remember("sitemap_properties_{$locale}", 3600, function () use ($locale, $seoService) {
            $items = [];

            PropertyListing::active()
                ->with(['primaryImage', 'images'])
                ->orderBy('updated_at', 'desc')
                ->chunk(200, function ($chunk) use ($locale, $seoService, &$items) {
                    foreach ($chunk as $property) {
                        $items[] = [
                            'loc'         => $seoService->generatePropertyUrl($property, $locale),
                            'lastmod'     => $property->updated_at->toW3cString(),
                            'changefreq'  => 'weekly',
                            'priority'    => $property->is_featured ? '0.9' : '0.7',
                            'image'       => $property->primaryImage?->image_url ?? $property->images->first()?->image_url,
                            'image_title' => e($property->getTranslation('title', $locale)),
                            'alternates'  => [
                                'es' => $seoService->generatePropertyUrl($property, 'es'),
                                'en' => $seoService->generatePropertyUrl($property, 'en'),
                            ],
                        ];
                    }
                });

            return $items;
        });

        return response()
            ->view('sitemap.properties', compact('properties', 'locale'))
            ->header('Content-Type', 'application/xml');
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
}
