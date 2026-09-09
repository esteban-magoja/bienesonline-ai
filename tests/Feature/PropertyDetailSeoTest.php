<?php

use App\Models\PropertyListing;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Propietario SEO',
        'email' => 'property-seo-' . uniqid() . '@example.com',
        'username' => 'property-seo-' . uniqid(),
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->property = PropertyListing::factory()
        ->create([
            'user_id' => $userId,
            'title' => 'Casa moderna en Villa Carlos Paz',
            'description' => 'Una casa amplia y luminosa.',
            'property_type' => 'house',
            'transaction_type' => 'sale',
            'city' => 'Villa Carlos Paz',
            'state' => 'Córdoba',
            'country' => 'Argentina',
            'is_active' => true,
        ]);
});

it('serves the exact canonical URL for each locale', function (string $locale): void {
    $seoService = app(SeoService::class);
    $canonicalUrl = $seoService->generatePropertyUrl($this->property, $locale);
    $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);

    $this->get($canonicalPath)->assertSuccessful();
})->with(['es', 'en']);

it('generates locale-specific canonical SEO data', function (): void {
    $seoService = app(SeoService::class);
    $spanishSeo = $seoService->generatePropertySeo($this->property, 'es');
    $englishSeo = $seoService->generatePropertySeo($this->property, 'en');

    expect($spanishSeo->canonical)->toContain('/es/')
        ->and($englishSeo->canonical)->toContain('/en/')
        ->and($spanishSeo->canonical)->not->toBe($englishSeo->canonical);
});

it('normalizes regional property and transaction values in JSON-LD', function (): void {
    DB::table('property_types')->updateOrInsert(
        ['country_code' => 'ES', 'value' => 'piso'],
        [
            'label' => 'Piso',
            'label_plural' => 'Pisos',
            'value_en' => 'apartment',
            'order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    DB::table('transaction_types')->updateOrInsert(
        ['country_code' => 'CL', 'value' => 'arriendo'],
        [
            'label' => 'Arriendo',
            'value_en' => 'rent',
            'order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $this->property->update([
        'property_type' => 'piso',
        'transaction_type' => 'arriendo',
    ]);

    $seo = app(SeoService::class)->generatePropertySeo($this->property, 'es');
    $graph = collect($seo->structured_data['@graph']);
    $propertyNode = $graph->first(fn (array $node): bool => ($node['@id'] ?? null) === $seo->canonical . '#property');
    $offerNode = $graph->first(fn (array $node): bool => ($node['@id'] ?? null) === $seo->canonical . '#offer');

    expect($seo->structured_data['@context'])->toBe('https://schema.org')
        ->and($propertyNode['@type'])->toBe('Apartment')
        ->and($offerNode['businessFunction'])->toBe('https://schema.org/LeaseOut')
        ->and($offerNode['priceCurrency'])->toBe(strtoupper($this->property->currency))
        ->and($propertyNode['address']['addressCountry'])->toBeIn(['AR', 'Argentina'])
        ->and($graph->first(fn (array $node): bool => ($node['@type'] ?? null) === 'BreadcrumbList'))->not->toBeNull()
        ->and(json_decode(json_encode($seo->structured_data), true))->toBeArray();
});

it('redirects a property URL without its slug to the locale canonical URL', function (): void {
    $seoService = app(SeoService::class);
    $canonicalUrl = $seoService->generatePropertyUrl($this->property, 'es');
    $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);
    $urlWithoutSlug = preg_replace('#/propiedad/(\d+)-[^/]+$#', '/propiedad/$1', $canonicalPath);

    $this->get($urlWithoutSlug)
        ->assertMovedPermanently()
        ->assertRedirect($canonicalUrl);
});

it('redirects an incorrect slug to the canonical URL in the same locale', function (): void {
    $seoService = app(SeoService::class);
    $canonicalUrl = $seoService->generatePropertyUrl($this->property, 'en');
    $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);
    $incorrectPath = preg_replace('#/propiedad/(\d+)-[^/]+$#', '/propiedad/$1-slug-incorrecto', $canonicalPath);

    $this->get($incorrectPath)
        ->assertMovedPermanently()
        ->assertRedirect($canonicalUrl);
});

it('redirects incorrect location segments to the canonical URL', function (): void {
    $seoService = app(SeoService::class);
    $canonicalUrl = $seoService->generatePropertyUrl($this->property, 'es');
    $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);
    $incorrectPath = str_replace('/argentina/villa-carlos-paz/', '/mexico/cordoba/', $canonicalPath);

    $this->get($incorrectPath)
        ->assertMovedPermanently()
        ->assertRedirect($canonicalUrl);
});

it('does not redirect an inactive property because it is not publicly available', function (): void {
    $this->property->update(['is_active' => false]);

    $canonicalUrl = app(SeoService::class)->generatePropertyUrl($this->property, 'es');
    $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH);

    $this->get($canonicalPath)->assertNotFound();
});
