<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->user = User::find(1);
    $this->actingAs($this->user);
});

test('dashboard loads successfully', function (): void {
    $this->get('/dashboard')->assertOk();
});

test('dashboard stats are cached after first load', function (): void {
    Cache::flush();

    $this->get('/dashboard')->assertOk();

    $userId = $this->user->id;
    expect(Cache::has("dashboard_listings_{$userId}"))->toBeTrue()
        ->and(Cache::has("dashboard_requests_{$userId}"))->toBeTrue()
        ->and(Cache::has("dashboard_contacts_total_{$userId}"))->toBeTrue()
        ->and(Cache::has("dashboard_contacts_unseen_{$userId}"))->toBeTrue();
});

test('creating a listing clears the listings cache', function (): void {
    Cache::put("dashboard_listings_{$this->user->id}", 99, 300);

    PropertyListing::factory()->create(['user_id' => $this->user->id]);

    expect(Cache::has("dashboard_listings_{$this->user->id}"))->toBeFalse();
});

test('deleting a listing clears the listings cache', function (): void {
    $listing = PropertyListing::factory()->create(['user_id' => $this->user->id]);
    Cache::put("dashboard_listings_{$this->user->id}", 99, 300);

    $listing->delete();

    expect(Cache::has("dashboard_listings_{$this->user->id}"))->toBeFalse();
});

test('creating a request clears the requests cache', function (): void {
    Cache::put("dashboard_requests_{$this->user->id}", 99, 300);

    PropertyRequest::create([
        'user_id'          => $this->user->id,
        'title'            => 'Test request',
        'description'      => 'Looking for a property',
        'property_type'    => 'house',
        'transaction_type' => 'sale',
        'country'          => 'Argentina',
        'is_active'        => true,
    ]);

    expect(Cache::has("dashboard_requests_{$this->user->id}"))->toBeFalse();
});

test('deleting a request clears the requests cache', function (): void {
    $request = PropertyRequest::create([
        'user_id'          => $this->user->id,
        'title'            => 'Test request',
        'description'      => 'Looking for a property',
        'property_type'    => 'house',
        'transaction_type' => 'sale',
        'country'          => 'Argentina',
        'is_active'        => true,
    ]);
    Cache::put("dashboard_requests_{$this->user->id}", 99, 300);

    $request->delete();

    expect(Cache::has("dashboard_requests_{$this->user->id}"))->toBeFalse();
});
