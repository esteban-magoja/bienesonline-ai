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

function seedPropertyListingsForSitemapTest(int $count): int
{
    $timestamp = now();
    $userId = DB::table('users')->insertGetId([
        'name' => 'Sitemap Test User',
        'email' => 'sitemap-test-' . str_replace('.', '', (string) microtime(true)) . '@example.com',
        'username' => 'sitemaptest' . str_replace('.', '', (string) microtime(true)),
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'terms_accepted_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
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
