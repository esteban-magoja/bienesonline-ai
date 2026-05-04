<?php

declare(strict_types=1);

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

describe('PropertyRequestObserver', function () {
    it('clears listing cache when a matching request is created', function () {
        $uniqueId = str_replace('.', '', uniqid('', true));
        $userId = DB::table('users')->insertGetId([
            'name'             => 'Test User',
            'email'            => "observer-test-{$uniqueId}@example.com",
            'username'         => "observertest{$uniqueId}",
            'avatar'           => 'demo/default.png',
            'password'         => bcrypt('password'),
            'locale'           => 'es',
            'terms_accepted'   => true,
            'terms_accepted_at'=> now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $listing = PropertyListing::factory()->create([
            'user_id'          => $userId,
            'country'          => 'Chile',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'price'            => 100000,
            'is_active'        => true,
        ]);

        Cache::put("matches_listing_count_{$listing->id}", 5, 3600);
        Cache::put("matches_listing_{$listing->id}", collect([1, 2, 3]), 3600);
        Cache::put("matches_index_{$userId}", collect([]), 900);

        PropertyRequest::create([
            'user_id'          => User::first()->id,
            'title'            => 'Test request',
            'description'      => 'Test description for request',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'country'          => 'Chile',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        expect(Cache::has("matches_listing_count_{$listing->id}"))->toBeFalse()
            ->and(Cache::has("matches_listing_{$listing->id}"))->toBeFalse()
            ->and(Cache::has("matches_index_{$userId}"))->toBeFalse();
    });

    it('does not clear cache for listings with different country', function () {
        $listing = PropertyListing::factory()->create([
            'country'          => 'Argentina',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'price'            => 100000,
            'is_active'        => true,
        ]);

        Cache::put("matches_listing_count_{$listing->id}", 5, 3600);

        PropertyRequest::create([
            'user_id'          => User::first()->id,
            'title'            => 'Test request Chile',
            'description'      => 'Test description',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'country'          => 'Chile',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        expect(Cache::has("matches_listing_count_{$listing->id}"))->toBeTrue();
    });

    it('clears listing cache when a request is updated', function () {
        $listing = PropertyListing::factory()->create([
            'country'          => 'Chile',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'price'            => 100000,
            'is_active'        => true,
        ]);

        $request = PropertyRequest::create([
            'user_id'          => User::first()->id,
            'title'            => 'Test request',
            'description'      => 'Test description',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'country'          => 'Chile',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        Cache::put("matches_listing_count_{$listing->id}", 5, 3600);

        $request->update(['title' => 'Updated title']);

        expect(Cache::has("matches_listing_count_{$listing->id}"))->toBeFalse();
    });

    it('clears listing cache when a request is deleted', function () {
        $listing = PropertyListing::factory()->create([
            'country'          => 'Chile',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'price'            => 100000,
            'is_active'        => true,
        ]);

        $request = PropertyRequest::create([
            'user_id'          => User::first()->id,
            'title'            => 'Test request',
            'description'      => 'Test description',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'country'          => 'Chile',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        Cache::put("matches_listing_count_{$listing->id}", 5, 3600);

        $request->delete();

        expect(Cache::has("matches_listing_count_{$listing->id}"))->toBeFalse();
    });
});
