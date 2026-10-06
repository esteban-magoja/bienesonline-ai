<?php

declare(strict_types=1);

use App\Filament\Resources\SearchQueries\Pages\EditSearchQuery;
use App\Filament\Resources\SearchQueries\Pages\ListSearchQueries;
use App\Models\SearchQuery;
use App\Models\User;
use App\Services\SemanticPropertySearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->mock(SemanticPropertySearchService::class)
        ->shouldReceive('search')
        ->andReturn(new LengthAwarePaginator(collect(), 7, 20));
});

it('guarda una búsqueda de 10 o más caracteres con país, slug, resultados e inactiva', function (): void {
    $this->get('/es/search-properties?country=Argentina&search=Casa  con Piscina en Córdoba')
        ->assertSuccessful();

    $search = SearchQuery::where('country', 'Argentina')->where('slug', 'casa-con-piscina-en-cordoba')->sole();

    expect($search->query)->toBe('casa con piscina en córdoba')
        ->and($search->slug)->toBe('casa-con-piscina-en-cordoba')
        ->and($search->results_count)->toBe(7)
        ->and($search->active)->toBeFalse();
});

it('no guarda búsquedas de menos de 10 caracteres', function (): void {
    $this->get('/es/search-properties?country=Argentina&search=casa+lago')->assertSuccessful();

    expect(SearchQuery::where('country', 'Argentina')->where('slug', 'casa-lago')->exists())->toBeFalse();
});

it('no guarda búsquedas repetidas en el mismo país', function (): void {
    $this->get('/es/search-properties?country=Argentina&search=departamento en palermo');
    $this->get('/es/search-properties?country=Argentina&search=Departamento  en Palermo');

    expect(SearchQuery::where('slug', 'departamento-en-palermo')->where('country', 'Argentina')->count())->toBe(1);
});

it('guarda la búsqueda al visitar la página indexable si no existe', function (): void {
    $this->get('/es/chile/busqueda/casas-en-venta-en-quilpue-test');
    $this->get('/es/chile/busqueda/casas-en-venta-en-quilpue-test');

    $search = SearchQuery::where('country', 'Chile')->where('slug', 'casas-en-venta-en-quilpue-test')->sole();

    expect($search->query)->toBe('casas en venta en quilpue test')
        ->and($search->slug)->toBe('casas-en-venta-en-quilpue-test')
        ->and($search->results_count)->toBe(7)
        ->and($search->active)->toBeFalse();
});

describe('otras búsquedas', function (): void {
    beforeEach(fn () => Cache::flush());

    it('muestra hasta 5 búsquedas activas del mismo país ordenadas por palabras en común', function (): void {
        $make = fn (string $slug, array $attributes = []) => SearchQuery::factory()->active()->create(array_merge([
            'country' => 'Testlandia',
            'query' => str_replace('-', ' ', $slug),
            'slug' => $slug,
            'results_count' => 1,
        ], $attributes));

        $make('casa-en-venta-en-valparaiso');
        $make('departamento-en-valparaiso');
        $make('parcela-en-venta-en-san-esteban');
        $make('oficina-en-arriendo-santiago', ['results_count' => 50]);
        $make('local-comercial-en-arriendo');
        $make('bodega-en-arriendo-rancagua');
        $make('casa-en-venta-valparaiso-inactiva', ['active' => false]);
        $make('casas-en-venta-valparaiso', ['country' => 'Otrolandia']);

        $related = SearchQuery::related('Testlandia', 'casas-en-venta-san-esteban-valparaiso');

        expect($related)->toHaveCount(5)
            ->and($related->pluck('slug')->take(3)->all())->toBe([
                'parcela-en-venta-en-san-esteban',
                'casa-en-venta-en-valparaiso',
                'departamento-en-valparaiso',
            ])
            ->and($related->pluck('slug')->get(3))->toBe('oficina-en-arriendo-santiago')
            ->and($related->pluck('slug'))->not->toContain('casa-en-venta-valparaiso-inactiva', 'casas-en-venta-valparaiso');
    });

    it('muestra el cuadro con links en la página indexable', function (): void {
        SearchQuery::factory()->active()->create([
            'country' => 'Chile',
            'query' => 'casas en venta en quilpue test',
            'slug' => 'casas-en-venta-en-quilpue-test',
        ]);

        $this->get('/es/chile/busqueda/casas-en-venta-en-vina-test')
            ->assertSuccessful()
            ->assertSee(__('properties.search_results.other_searches'))
            ->assertSee('Casas en venta en quilpue test')
            ->assertSee('/es/chile/busqueda/casas-en-venta-en-quilpue-test', false);
    });
});

describe('panel admin', function (): void {
    beforeEach(function (): void {
        $admin = User::factory()->create(['avatar' => 'demo/default.png']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $this->actingAs($admin);
    });

    it('lista las búsquedas', function (): void {
        $search = SearchQuery::factory()->create();

        $this->get('/admin/search-queries')->assertOk();

        Livewire::test(ListSearchQueries::class)->assertCanSeeTableRecords([$search]);
    });

    it('activa una búsqueda desde la tabla con el toggle', function (): void {
        $search = SearchQuery::factory()->create();

        Livewire::test(ListSearchQueries::class)
            ->call('updateTableColumnState', 'active', (string) $search->getKey(), true);

        expect($search->fresh()->active)->toBeTrue();
    });

    it('edita una búsqueda', function (): void {
        $search = SearchQuery::factory()->create();

        Livewire::test(EditSearchQuery::class, ['record' => $search->getKey()])
            ->fillForm(['query' => 'casa quinta con pileta', 'slug' => 'casa-quinta-con-pileta', 'active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($search->fresh())
            ->slug->toBe('casa-quinta-con-pileta')
            ->active->toBeTrue();
    });

    it('borra una búsqueda', function (): void {
        $search = SearchQuery::factory()->create();

        Livewire::test(ListSearchQueries::class)->callTableAction('delete', $search);

        expect(SearchQuery::find($search->getKey()))->toBeNull();
    });
});
