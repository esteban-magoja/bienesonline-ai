<?php

namespace App\Http\Controllers;

use App\Models\PropertyListing;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Main sitemap index
     */
    public function index(): Response
    {
        $sitemaps = [
            [
                'loc' => url('/sitemap-pages.xml'),
                'lastmod' => now()->toW3cString(),
            ],
            [
                'loc' => url('/sitemap-properties-es.xml'),
                'lastmod' => PropertyListing::active()->latest('updated_at')->first()?->updated_at?->toW3cString() ?? now()->toW3cString(),
            ],
            [
                'loc' => url('/sitemap-properties-en.xml'),
                'lastmod' => PropertyListing::active()->latest('updated_at')->first()?->updated_at?->toW3cString() ?? now()->toW3cString(),
            ],
        ];

        return response()
            ->view('sitemap.index', compact('sitemaps'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Static pages sitemap
     */
    public function pages(): Response
    {
        $locales = ['es', 'en'];
        $pages = [];

        // Home
        foreach ($locales as $locale) {
            $pages[] = [
                'loc' => url("/{$locale}"),
                'lastmod' => now()->toW3cString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
                'alternates' => $this->getAlternates("/{locale}"),
            ];
        }

        // Property search
        foreach ($locales as $locale) {
            $pages[] = [
                'loc' => url("/{$locale}/search-properties"),
                'lastmod' => now()->toW3cString(),
                'changefreq' => 'hourly',
                'priority' => '0.9',
                'alternates' => $this->getAlternates("/{locale}/search-properties"),
            ];
        }

        // Request search
        foreach ($locales as $locale) {
            $pages[] = [
                'loc' => url("/{$locale}/search-requests"),
                'lastmod' => now()->toW3cString(),
                'changefreq' => 'hourly',
                'priority' => '0.8',
                'alternates' => $this->getAlternates("/{locale}/search-requests"),
            ];
        }

        // Pricing
        foreach ($locales as $locale) {
            $pages[] = [
                'loc' => url("/{$locale}/pricing"),
                'lastmod' => now()->subDays(7)->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'alternates' => $this->getAlternates("/{locale}/pricing"),
            ];
        }

        // Login
        foreach ($locales as $locale) {
            $pages[] = [
                'loc' => url("/{$locale}/login"),
                'lastmod' => now()->subDays(30)->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
                'alternates' => $this->getAlternates("/{locale}/login"),
            ];
        }

        // Register
        foreach ($locales as $locale) {
            $pages[] = [
                'loc' => url("/{$locale}/signup"),
                'lastmod' => now()->subDays(30)->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
                'alternates' => $this->getAlternates("/{locale}/signup"),
            ];
        }

        return response()
            ->view('sitemap.pages', compact('pages'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Properties sitemap by locale
     */
    public function properties(string $locale): Response
    {
        if (!in_array($locale, ['es', 'en'])) {
            abort(404);
        }

        $properties = PropertyListing::active()
            ->with(['primaryImage', 'images'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($property) use ($locale) {
                $slug = \Illuminate\Support\Str::slug($property->getTranslation('title', $locale));
                
                return [
                    'loc' => url("/{$locale}/property/{$property->id}/{$slug}"),
                    'lastmod' => $property->updated_at->toW3cString(),
                    'changefreq' => 'weekly',
                    'priority' => $property->is_featured ? '0.9' : '0.7',
                    'image' => $property->primaryImage?->image_url ?? $property->images->first()?->image_url,
                    'image_title' => $property->getTranslation('title', $locale),
                    'alternates' => [
                        'es' => url("/es/property/{$property->id}/" . \Illuminate\Support\Str::slug($property->getTranslation('title', 'es'))),
                        'en' => url("/en/property/{$property->id}/" . \Illuminate\Support\Str::slug($property->getTranslation('title', 'en'))),
                    ],
                ];
            });

        return response()
            ->view('sitemap.properties', compact('properties', 'locale'))
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Get alternate URLs for hreflang
     */
    private function getAlternates(string $pathPattern): array
    {
        return [
            'es' => url(str_replace('{locale}', 'es', $pathPattern)),
            'en' => url(str_replace('{locale}', 'en', $pathPattern)),
        ];
    }
}
