<?php

use App\Models\PropertyListing;
use App\Services\RelatedPropertyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config(['matching.enabled' => false]);

    $userId = DB::table('users')->insertGetId([
        'name' => 'Propietario de relacionados',
        'email' => 'related-' . uniqid() . '@example.com',
        'username' => 'related-' . uniqid(),
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->property = PropertyListing::factory()->create([
        'user_id' => $userId,
        'title' => 'Departamento principal',
        'property_type' => 'departamento',
        'transaction_type' => 'alquiler',
        'price' => 200000,
        'currency' => 'ARS',
        'bedrooms' => 2,
        'area' => 80,
        'city' => 'Villa Carlos Paz',
        'state' => 'Córdoba',
        'country' => 'Argentina',
        'is_active' => true,
    ]);
});

it('prioritizes the same regional type and transaction before broader fallbacks', function (): void {
    $sameCity = PropertyListing::factory()->create([
        'user_id' => $this->property->user_id,
        'property_type' => 'departamento',
        'transaction_type' => 'alquiler',
        'price' => 210000,
        'currency' => 'ARS',
        'bedrooms' => 2,
        'area' => 82,
        'city' => 'Villa Carlos Paz',
        'state' => 'Córdoba',
        'country' => 'Argentina',
        'is_active' => true,
    ]);

    $sameTypeAndTransaction = PropertyListing::factory()->create([
        'user_id' => $this->property->user_id,
        'property_type' => 'departamento',
        'transaction_type' => 'alquiler',
        'price' => 205000,
        'currency' => 'ARS',
        'city' => 'Córdoba',
        'state' => 'Córdoba',
        'country' => 'Argentina',
        'is_active' => true,
    ]);

    $sameTransaction = PropertyListing::factory()->create([
        'user_id' => $this->property->user_id,
        'property_type' => 'casa',
        'transaction_type' => 'alquiler',
        'price' => 190000,
        'currency' => 'ARS',
        'city' => 'Villa Carlos Paz',
        'state' => 'Córdoba',
        'country' => 'Argentina',
        'is_active' => true,
    ]);

    $sameType = PropertyListing::factory()->create([
        'user_id' => $this->property->user_id,
        'property_type' => 'departamento',
        'transaction_type' => 'venta',
        'price' => 195000,
        'currency' => 'ARS',
        'city' => 'Villa Carlos Paz',
        'state' => 'Córdoba',
        'country' => 'Argentina',
        'is_active' => true,
    ]);

    $related = app(RelatedPropertyService::class)->findRelatedProperties($this->property);

    expect($related->pluck('id')->all())->toBe([
        $sameCity->id,
        $sameTypeAndTransaction->id,
        $sameTransaction->id,
        $sameType->id,
    ]);
});

it('excludes inactive listings, other countries, and the current property', function (): void {
    $fallback = PropertyListing::factory()->create([
        'user_id' => $this->property->user_id,
        'property_type' => 'casa',
        'transaction_type' => 'venta',
        'country' => 'Argentina',
        'city' => 'Córdoba',
        'is_active' => true,
    ]);

    PropertyListing::factory()->create([
        'user_id' => $this->property->user_id,
        'country' => 'Argentina',
        'is_active' => false,
    ]);

    PropertyListing::factory()->create([
        'user_id' => $this->property->user_id,
        'country' => 'España',
        'is_active' => true,
    ]);

    $related = app(RelatedPropertyService::class)->findRelatedProperties($this->property);

    expect($related->pluck('id')->all())->toContain($fallback->id)
        ->and($related->pluck('id')->all())->not->toContain($this->property->id)
        ->and($related->pluck('country')->unique()->all())->toBe(['Argentina']);
});
