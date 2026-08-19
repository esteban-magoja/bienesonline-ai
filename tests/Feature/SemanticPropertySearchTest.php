<?php

use App\Models\PropertyListing;
use App\Models\User;
use App\Services\SemanticPropertySearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pgvector\Laravel\Vector;

uses(DatabaseTransactions::class);

it('uses a minimum semantic similarity of 50 percent', function () {
    expect(config('openai.search_distance_threshold'))->toBe(0.5);
});

it('uses the shared semantic result card on the public search page', function () {
    Cache::flush();

    $user = User::query()->firstOrFail();
    PropertyListing::factory()->create([
        'user_id' => $user->id,
        'title' => 'Terreno en venta en Villa Carlos Paz',
        'country' => 'Argentina',
        'city' => 'Villa Carlos Paz',
        'is_active' => true,
        'embedding' => new Vector(array_fill(0, 1536, 0.001)),
        'title_i18n' => ['es' => 'Terreno en venta en Villa Carlos Paz'],
    ]);

    $response = $this->get('/es/search-properties?country=Argentina&search=Terreno+Venta+Villa+Carlos+Paz');

    $response->assertSuccessful()
        ->assertSee('Terreno en venta en Villa Carlos Paz')
        ->assertSee('rounded-2xl border border-zinc-200', false)
        ->assertSee('text-indigo-600', false);
});

it('renders an indexable semantic search landing page filtered by country', function () {
    $user = User::query()->firstOrFail();
    $matchingListing = PropertyListing::factory()->create([
        'user_id' => $user->id,
        'title' => 'Apartamento cerca del Sagrado Corazón',
        'country' => 'Chile',
        'city' => 'Santiago',
        'state' => 'Santiago Metropolitan',
        'is_active' => true,
        'embedding' => new Vector(array_fill(0, 1536, 0.001)),
        'title_i18n' => ['es' => 'Apartamento cerca del Sagrado Corazón'],
    ]);
    PropertyListing::factory()->create([
        'user_id' => $user->id,
        'title' => 'Apartamento en Colombia',
        'country' => 'Colombia',
        'is_active' => true,
        'embedding' => new Vector(array_fill(0, 1536, 0.001)),
    ]);

    $response = $this->get('/es/chile/busqueda/apartamento-sagrado-corazon');

    $response->assertSuccessful()
        ->assertSee('Apartamento cerca del Sagrado Corazón')
        ->assertDontSee('Apartamento en Colombia')
        ->assertSee('<meta name="robots" content="index,follow">', false)
        ->assertSee('<link rel="canonical"', false);

    expect($matchingListing->fresh()->country)->toBe('Chile');
});

it('returns noindex for a semantic search with no results', function () {
    $response = $this->get('/es/espana/busqueda/apartamento-inexistente');

    $response->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex,follow">', false)
        ->assertSee(__('properties.results.no_results_title'));
});

it('canonicalizes semantic search slugs with a permanent redirect', function () {
    $response = $this->get('/es/chile/search/Apartamento-Sagrado-Corazon');

    $response->assertMovedPermanently()
        ->assertRedirect('/es/chile/busqueda/apartamento-sagrado-corazon');
});

it('falls back to text search when embedding generation is unavailable', function () {
    Cache::flush();
    Http::fake([
        'https://api.openai.com/v1/embeddings' => Http::response([], 500),
    ]);

    $user = User::query()->firstOrFail();
    PropertyListing::factory()->create([
        'user_id' => $user->id,
        'title' => 'Casa familiar en Santiago',
        'description' => 'Casa con jardín y estacionamiento.',
        'country' => 'Chile',
        'is_active' => true,
        'embedding' => null,
    ]);

    $results = app(SemanticPropertySearchService::class)->search('casa familiar', 'Chile');

    expect($results->getCollection()->pluck('title'))->toContain('Casa familiar en Santiago');
});
