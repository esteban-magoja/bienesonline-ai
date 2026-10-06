<?php

use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\SearchTermTypeResolver;
use App\Services\SemanticPropertySearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pgvector\Laravel\Vector;

uses(DatabaseTransactions::class);

it('uses a minimum semantic similarity of 50 percent and a 10 point relative margin', function () {
    expect(config('openai.search_distance_threshold'))->toBe(0.5)
        ->and(config('openai.search_relative_margin'))->toBe(0.10);
});

it('uses the shared semantic result card on the public search page', function () {
    Cache::flush();

    $user = User::query()->firstOrFail();
    PropertyListing::factory()->create([
        'user_id' => $user->id,
        'title' => 'Terreno en venta en Villa Carlos Paz',
        'property_type' => 'terreno',
        'transaction_type' => 'venta',
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
    $response = $this->get('/es/chile/busqueda/apartamento-inexistente');

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
        'property_type' => 'casa',
        'transaction_type' => 'venta',
        'country' => 'Chile',
        'is_active' => true,
        'embedding' => null,
    ]);

    $results = app(SemanticPropertySearchService::class)->search('casa familiar', 'Chile');

    expect($results->getCollection()->pluck('title'))->toContain('Casa familiar en Santiago');
});

describe('precisión de la búsqueda semántica', function () {
    beforeEach(function () {
        Cache::flush();

        foreach ([['casa', 'Casa', 'house'], ['departamento', 'Departamento', 'apartment']] as [$value, $label, $valueEn]) {
            PropertyType::create(['country_code' => 'ZZ', 'value' => $value, 'label' => $label, 'value_en' => $valueEn, 'order' => 1, 'is_active' => true]);
        }
        foreach ([['venta', 'Venta', 'sale'], ['arriendo', 'Arriendo', 'rent']] as [$value, $label, $valueEn]) {
            TransactionType::create(['country_code' => 'ZZ', 'value' => $value, 'label' => $label, 'value_en' => $valueEn, 'order' => 1, 'is_active' => true]);
        }

        // Vector unitario con similitud coseno $similarity respecto de [1, 0, 0, ...]
        $this->vector = function (float $similarity): Vector {
            $values = array_fill(0, 1536, 0.0);
            $values[0] = $similarity;
            $values[1] = sqrt(1 - $similarity ** 2);

            return new Vector($values);
        };

        $this->mock(EmbeddingService::class)
            ->shouldReceive('generate')
            ->andReturn(($this->vector)(1.0));

        $this->listing = function (string $title, float $similarity, string $type = 'casa', string $transaction = 'venta'): void {
            $listing = PropertyListing::factory()->create([
                'user_id' => User::factory()->create(['avatar' => 'demo/default.png'])->id,
                'title' => $title,
                'country' => 'Testlandia',
                'property_type' => $type,
                'transaction_type' => $transaction,
                'is_active' => true,
            ]);

            // Sin eventos: el observer regeneraría el embedding con el mock
            $listing->embedding = ($this->vector)($similarity);
            $listing->saveQuietly();
        };
    });

    it('detecta tipo de inmueble y operación en el texto, incluyendo plurales y equivalentes', function () {
        expect(app(SearchTermTypeResolver::class)->resolve('Casas en venta en Valparaíso'))->toBe([
            'property_types' => ['casa', 'house'],
            'transaction_types' => ['venta', 'sale'],
        ])->and(app(SearchTermTypeResolver::class)->resolve('propiedades en los andes'))->toBe([
            'property_types' => [],
            'transaction_types' => [],
        ]);
    });

    it('filtra por tipo y operación y solo muestra resultados cercanos al mejor', function () {
        ($this->listing)('Casa muy parecida', 0.95);
        ($this->listing)('Casa parecida', 0.88);
        ($this->listing)('Casa poco parecida', 0.80);
        ($this->listing)('Departamento idéntico', 1.0, 'departamento');
        ($this->listing)('Casa en arriendo idéntica', 1.0, 'casa', 'arriendo');

        $titles = app(SemanticPropertySearchService::class)
            ->search('casas en venta', 'Testlandia')
            ->pluck('title')
            ->all();

        expect($titles)->toBe(['Casa muy parecida', 'Casa parecida']);
    });

    it('no filtra por tipo cuando el texto no lo menciona', function () {
        ($this->listing)('Casa', 0.95);
        ($this->listing)('Departamento', 0.93, 'departamento', 'arriendo');

        expect(app(SemanticPropertySearchService::class)->search('propiedades en testlandia', 'Testlandia')->total())->toBe(2);
    });
});
