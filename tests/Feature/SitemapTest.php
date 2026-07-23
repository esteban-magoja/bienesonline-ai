<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

it('splits property sitemap indexes conservatively to stay below Google size limits', function () {
    $baselineActive = DB::table('property_listings')->where('is_active', true)->count();
    $insertedActive = seedPropertyListingsForSitemapTest(5001);
    $expectedPages = (int) ceil(($baselineActive + $insertedActive) / 5000);

    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertSee("/sitemap-properties-es-{$expectedPages}.xml", false);
    $response->assertSee("/sitemap-properties-en-{$expectedPages}.xml", false);
    $response->assertDontSee('/sitemap-properties-es-' . ($expectedPages + 1) . '.xml', false);
    $response->assertDontSee('/sitemap-properties-en-' . ($expectedPages + 1) . '.xml', false);
});

it('returns the remaining properties on the last sitemap page', function () {
    $baselineActive = DB::table('property_listings')->where('is_active', true)->count();
    $insertedActive = seedPropertyListingsForSitemapTest(5001);
    $totalActive = $baselineActive + $insertedActive;
    $lastPage = (int) ceil($totalActive / 5000);
    $expectedUrls = $totalActive - (($lastPage - 1) * 5000);

    $response = $this->get("/sitemap-properties-es-{$lastPage}.xml");

    $response->assertSuccessful();
    $response->assertStreamed();

    expect(substr_count($response->streamedContent(), '<url>'))->toBe($expectedUrls);
});

it('returns not found when requesting a property sitemap page beyond the last page', function () {
    $baselineActive = DB::table('property_listings')->where('is_active', true)->count();
    $insertedActive = seedPropertyListingsForSitemapTest(5001);
    $lastPage = (int) ceil(($baselineActive + $insertedActive) / 5000);

    $response = $this->get('/sitemap-properties-es-' . ($lastPage + 1) . '.xml');

    $response->assertNotFound();
});

it('emits absolute image URLs in property sitemaps when stored image URLs are relative', function () {
    $listingId = createPropertyListingWithImageForSitemapTest('/storage/property_images/test-relative-image.jpg');

    $response = $this->get('/sitemap-properties-es-1.xml');

    $response->assertSuccessful();
    $response->assertStreamed();
    expect($response->streamedContent())->toContain(
        '<image:loc>' . url('/storage/property_images/test-relative-image.jpg') . '</image:loc>'
    );

    expect($listingId)->toBeInt();
});

it('includes user profile pages in profiles sitemap', function () {
    $timestamp = now();
    $uniqueId = str_replace('.', '', uniqid('', true));
    $username = "profiletest{$uniqueId}";

    $userId = DB::table('users')->insertGetId([
        'name' => 'Profile Sitemap User',
        'email' => "profile-sitemap-{$uniqueId}@example.com",
        'username' => $username,
        'agency' => 'Profile Agency',
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'terms_accepted_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('property_listings')->insert([
        'user_id' => $userId,
        'title' => 'Profile sitemap listing',
        'description' => 'Listing created to include user profile in sitemap.',
        'property_type' => 'house',
        'transaction_type' => 'sale',
        'price' => 100000,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'parking_spaces' => 1,
        'area' => 120,
        'address' => 'Street 10',
        'city' => 'Cuenca',
        'state' => 'Azuay',
        'country' => 'Ecuador',
        'postal_code' => '010101',
        'latitude' => null,
        'longitude' => null,
        'is_featured' => false,
        'is_active' => true,
        'currency' => 'USD',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $response = $this->get('/sitemap-profiles.xml');

    $response->assertSuccessful();
    $response->assertSee("/es/inmobiliaria/{$username}", false);
    $response->assertSee("/en/realtor/{$username}", false);
});

it('generates agents directory sitemap with country state and city pages', function () {
    $timestamp = now();
    $uniqueId = str_replace('.', '', uniqid('', true));

    $userId = DB::table('users')->insertGetId([
        'name' => 'Agent Sitemap User',
        'email' => "agent-sitemap-{$uniqueId}@example.com",
        'username' => "agentsitemap{$uniqueId}",
        'agency' => 'Agent Sitemap Agency',
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'terms_accepted_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('property_listings')->insert([
        'user_id' => $userId,
        'title' => 'Agent directory sitemap listing',
        'description' => 'Listing created to include location pages in agent sitemap.',
        'property_type' => 'house',
        'transaction_type' => 'sale',
        'price' => 100000,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'parking_spaces' => 1,
        'area' => 120,
        'address' => 'Street 20',
        'city' => 'Cuenca',
        'state' => 'Azuay',
        'country' => 'Ecuador',
        'postal_code' => '010101',
        'latitude' => null,
        'longitude' => null,
        'is_featured' => false,
        'is_active' => true,
        'currency' => 'USD',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $response = $this->get('/sitemap-agents-es.xml');

    $response->assertSuccessful();
    $response->assertSee('/es/inmobiliarias', false);
    $response->assertSee('/es/ecuador/inmobiliarias', false);
    $response->assertSee('/es/ecuador/inmobiliarias/azuay', false);
    $response->assertSee('/es/ecuador/inmobiliarias/azuay/cuenca', false);
});

it('includes agent directory sitemaps in sitemap index', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertSee('/sitemap-agents-es.xml', false);
    $response->assertSee('/sitemap-agents-en.xml', false);
});

function seedPropertyListingsForSitemapTest(int $count): int
{
    $timestamp = now();
    $userId = createSitemapTestUser($timestamp);
    $rows = [];

    for ($index = 1; $index <= $count; $index++) {
        $rows[] = [
            'user_id' => $userId,
            'title' => "Sitemap test listing {$index}",
            'description' => 'Listing created to test sitemap pagination.',
            'property_type' => 'house',
            'transaction_type' => 'sale',
            'price' => 100000,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'parking_spaces' => 1,
            'area' => 120,
            'address' => "Street {$index}",
            'city' => 'Cordoba',
            'state' => 'Cordoba',
            'country' => 'Argentina',
            'postal_code' => '5000',
            'latitude' => null,
            'longitude' => null,
            'is_featured' => false,
            'is_active' => true,
            'currency' => 'USD',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        if (count($rows) === 1000) {
            DB::table('property_listings')->insert($rows);
            $rows = [];
        }
    }

    if ($rows !== []) {
        DB::table('property_listings')->insert($rows);
    }

    return $count;
}

function createPropertyListingWithImageForSitemapTest(string $imageUrl): int
{
    $timestamp = now();
    $userId = createSitemapTestUser($timestamp);

    $listingId = DB::table('property_listings')->insertGetId([
        'user_id' => $userId,
        'title' => 'Sitemap image test listing',
        'description' => 'Listing created to test sitemap image URLs.',
        'property_type' => 'house',
        'transaction_type' => 'sale',
        'price' => 100000,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'parking_spaces' => 1,
        'area' => 120,
        'address' => 'Street 1',
        'city' => 'Cordoba',
        'state' => 'Cordoba',
        'country' => 'Argentina',
        'postal_code' => '5000',
        'latitude' => null,
        'longitude' => null,
        'is_featured' => false,
        'is_active' => true,
        'currency' => 'USD',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('property_images')->insert([
        'property_listing_id' => $listingId,
        'image_path' => 'property_images/test-relative-image.jpg',
        'image_url' => $imageUrl,
        'alt_text' => 'Sitemap image test listing',
        'is_primary' => true,
        'sort_order' => 0,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return $listingId;
}

function createSitemapTestUser($timestamp): int
{
    $uniqueId = str_replace('.', '', uniqid('', true));

    return DB::table('users')->insertGetId([
        'name' => 'Sitemap Test User',
        'email' => "sitemap-test-{$uniqueId}@example.com",
        'username' => "sitemaptest{$uniqueId}",
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'terms_accepted_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
}
