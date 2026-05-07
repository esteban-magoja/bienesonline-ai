<?php

declare(strict_types=1);

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function createTestUser(): int
{
    $uniqueId = str_replace('.', '', uniqid('', true));
    return DB::table('users')->insertGetId([
        'name'              => 'Test User',
        'email'             => "matches-cache-{$uniqueId}@example.com",
        'username'          => "matchescache{$uniqueId}",
        'avatar'            => 'demo/default.png',
        'password'          => bcrypt('password'),
        'locale'            => 'es',
        'terms_accepted'    => true,
        'terms_accepted_at' => now(),
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
}

beforeEach(function (): void {
    Cache::flush();
});

test('dashboard_matches_inbound cache is cleared when listing is created', function (): void {
    $userId = createTestUser();
    Cache::put("dashboard_matches_inbound_{$userId}", 99, 21600);

    PropertyListing::factory()->create(['user_id' => $userId]);

    expect(Cache::has("dashboard_matches_inbound_{$userId}"))->toBeFalse();
});

test('dashboard_matches_inbound cache is cleared when listing is deleted', function (): void {
    $userId = createTestUser();
    $listing = PropertyListing::factory()->create(['user_id' => $userId]);
    Cache::put("dashboard_matches_inbound_{$userId}", 99, 21600);

    $listing->delete();

    expect(Cache::has("dashboard_matches_inbound_{$userId}"))->toBeFalse();
});

test('dashboard_matches_inbound cache is cleared when listing is_active changes', function (): void {
    $userId = createTestUser();
    $listing = PropertyListing::factory()->create(['user_id' => $userId, 'is_active' => true]);
    Cache::put("dashboard_matches_inbound_{$userId}", 99, 21600);

    $listing->update(['is_active' => false]);

    expect(Cache::has("dashboard_matches_inbound_{$userId}"))->toBeFalse();
});

test('dashboard_matches_outbound cache is cleared when request is created', function (): void {
    $userId = createTestUser();
    Cache::put("dashboard_matches_outbound_{$userId}", 99, 21600);

    PropertyRequest::create([
        'user_id'          => $userId,
        'title'            => 'Test request',
        'description'      => 'Test description for request',
        'property_type'    => 'house',
        'transaction_type' => 'sale',
        'country'          => 'Argentina',
        'currency'         => 'USD',
        'is_active'        => true,
    ]);

    expect(Cache::has("dashboard_matches_outbound_{$userId}"))->toBeFalse();
});

test('dashboard_matches_outbound cache is cleared when request is updated', function (): void {
    $userId = createTestUser();
    $request = PropertyRequest::create([
        'user_id'          => $userId,
        'title'            => 'Test request',
        'description'      => 'Test description for request',
        'property_type'    => 'house',
        'transaction_type' => 'sale',
        'country'          => 'Argentina',
        'currency'         => 'USD',
        'is_active'        => true,
    ]);
    Cache::put("dashboard_matches_outbound_{$userId}", 99, 21600);

    $request->update(['is_active' => false]);

    expect(Cache::has("dashboard_matches_outbound_{$userId}"))->toBeFalse();
});

test('dashboard_matches_outbound cache is cleared when request is deleted', function (): void {
    $userId = createTestUser();
    $request = PropertyRequest::create([
        'user_id'          => $userId,
        'title'            => 'Test request',
        'description'      => 'Test description for request',
        'property_type'    => 'house',
        'transaction_type' => 'sale',
        'country'          => 'Argentina',
        'currency'         => 'USD',
        'is_active'        => true,
    ]);
    Cache::put("dashboard_matches_outbound_{$userId}", 99, 21600);

    $request->delete();

    expect(Cache::has("dashboard_matches_outbound_{$userId}"))->toBeFalse();
});


