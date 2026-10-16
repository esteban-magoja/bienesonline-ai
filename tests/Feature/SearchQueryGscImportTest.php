<?php

declare(strict_types=1);

use App\Models\SearchQuery;
use App\Services\SemanticPropertySearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;

uses(DatabaseTransactions::class);

/**
 * Escribe un CSV con el formato de exportación "Páginas" de Search Console.
 *
 * @param  list<array{0: string, 1: int, 2: int, 3: string}>  $rows  [path, clics, impresiones, posición]
 */
function gscCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'gsc') . '.csv';
    $lines = ['Páginas principales,Clics,Impresiones,CTR,Posición'];

    foreach ($rows as [$urlPath, $clicks, $impressions, $position]) {
        $lines[] = "https://bienesonline.ai{$urlPath},{$clicks},{$impressions},0%,{$position}";
    }

    file_put_contents($path, implode("\n", $lines) . "\n");

    return $path;
}

it('activa las búsquedas con clics y crea las que no estaban guardadas', function (): void {
    $inactive = SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-casa-quilpue', 'query' => 'gsc test casa quilpue']);
    $withoutClicks = SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-sin-clics']);

    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-casa-quilpue', 3, 50, '6.5'],
        ['/es/chile/busqueda/gsc-test-terreno-corto', 1, 4, '9'],
        ['/es/chile/busqueda/gsc-test-sin-clics', 0, 12, '40'],
        ['/es/chile/busqueda/gsc-test-ignorada', 0, 5, '50'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file]])->assertSuccessful();

    expect($inactive->fresh())
        ->active->toBeTrue()
        ->gsc_clicks->toBe(3)
        ->gsc_impressions->toBe(50)
        ->gsc_position->toBe(6.5)
        ->gsc_imported_at->not->toBeNull();

    $created = SearchQuery::where('country', 'Chile')->where('slug', 'gsc-test-terreno-corto')->sole();
    expect($created->active)->toBeTrue()
        ->and($created->query)->toBe('gsc test terreno corto')
        ->and($created->gsc_clicks)->toBe(1);

    expect($withoutClicks->fresh())->active->toBeFalse()->gsc_impressions->toBe(12)
        ->and(SearchQuery::where('slug', 'gsc-test-ignorada')->exists())->toBeFalse();
});

it('suma las versiones es/en y las páginas paginadas de la misma búsqueda', function (): void {
    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-parcela-talca', 2, 100, '10'],
        ['/en/chile/search/gsc-test-parcela-talca', 1, 50, '4'],
        ['/en/chile/busqueda/gsc-test-parcela-talca', 1, 25, '4'],
        ['/es/chile/busqueda/gsc-test-parcela-talca?page=3', 1, 25, '20'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file]])->assertSuccessful();

    expect(SearchQuery::where('country', 'Chile')->where('slug', 'gsc-test-parcela-talca')->sole())
        ->gsc_clicks->toBe(5)
        ->gsc_impressions->toBe(200)
        ->gsc_position->toBe(9.0);
});

it('suma varios archivos sin contar dos veces las URLs repetidas', function (): void {
    $spanish = gscCsv([
        ['/es/chile/busqueda/gsc-test-depto-vina', 3, 30, '5'],
    ]);
    $english = gscCsv([
        ['/en/chile/search/gsc-test-depto-vina', 2, 20, '5'],
        ['/es/chile/busqueda/gsc-test-depto-vina', 3, 30, '5'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$spanish, $english]])->assertSuccessful();

    expect(SearchQuery::where('country', 'Chile')->where('slug', 'gsc-test-depto-vina')->value('gsc_clicks'))->toBe(5);
});

it('activa sin clics si tiene impresiones suficientes en posición cercana a la primera página', function (): void {
    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-potencial', 0, 40, '12'],
        ['/es/chile/busqueda/gsc-test-arriba-sin-clics', 0, 40, '3'],
        ['/es/chile/busqueda/gsc-test-muy-abajo', 0, 40, '45'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file]])->assertSuccessful();

    expect(SearchQuery::where('slug', 'gsc-test-potencial')->value('active'))->toBeTrue()
        ->and(SearchQuery::where('slug', 'gsc-test-arriba-sin-clics')->exists())->toBeFalse()
        ->and(SearchQuery::where('slug', 'gsc-test-muy-abajo')->exists())->toBeFalse();

    $this->artisan('search-queries:import-gsc', ['files' => [$file], '--min-impressions' => 0])->assertSuccessful();
});

it('pasa las métricas de una variante redirigida a la búsqueda principal', function (): void {
    $main = SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-casas-san-felipe']);
    $variant = SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-venta-casas-san-felipe', 'redirect_to_id' => $main->id]);

    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-venta-casas-san-felipe', 4, 30, '7'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file]])->assertSuccessful();

    expect($main->fresh())->active->toBeTrue()->gsc_clicks->toBe(4)
        ->and($variant->fresh())->active->toBeFalse()->gsc_clicks->toBeNull();
});

it('no desactiva búsquedas activas ni modifica nada en modo prueba', function (): void {
    $active = SearchQuery::factory()->active()->create(['country' => 'Chile', 'slug' => 'gsc-test-activa-manual']);
    $inactive = SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-dry-run']);

    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-activa-manual', 0, 2, '60'],
        ['/es/chile/busqueda/gsc-test-dry-run', 5, 20, '5'],
        ['/es/chile/busqueda/gsc-test-dry-run-nueva', 5, 20, '5'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file], '--dry-run' => true])
        ->expectsOutputToContain('Modo prueba')
        ->assertSuccessful();

    expect($inactive->fresh()->active)->toBeFalse()
        ->and(SearchQuery::where('slug', 'gsc-test-dry-run-nueva')->exists())->toBeFalse();

    $this->artisan('search-queries:import-gsc', ['files' => [$file]])->assertSuccessful();

    expect($active->fresh())->active->toBeTrue()->gsc_clicks->toBe(0);
});

it('redirige las búsquedas casi iguales a la de más clics con --redirect-duplicates', function (): void {
    $manual = SearchQuery::factory()->active()->create(['country' => 'Chile', 'slug' => 'gsc-test-casas-en-venta-en-quilpue']);
    $inactive = SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-casa-venta-quilpue']);

    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-venta-de-casas-quilpue', 9, 90, '5'],
        ['/es/chile/busqueda/gsc-test-casa-venta-quilpue', 2, 20, '6'],
        ['/es/chile/busqueda/gsc-test-casas-venta-en-quilpue', 1, 5, '7'],
        ['/es/chile/busqueda/gsc-test-terreno-quilpue', 1, 5, '7'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file], '--redirect-duplicates' => true, '--dry-run' => true])
        ->expectsOutputToContain('redirigirán (301)')
        ->assertSuccessful();

    expect(SearchQuery::where('slug', 'gsc-test-venta-de-casas-quilpue')->exists())->toBeFalse()
        ->and($manual->fresh()->redirect_to_id)->toBeNull();

    $this->artisan('search-queries:import-gsc', ['files' => [$file], '--redirect-duplicates' => true])->assertSuccessful();

    $principal = SearchQuery::where('country', 'Chile')->where('slug', 'gsc-test-venta-de-casas-quilpue')->sole();
    $newVariant = SearchQuery::where('country', 'Chile')->where('slug', 'gsc-test-casas-venta-en-quilpue')->sole();

    expect($principal)->active->toBeTrue()->redirect_to_id->toBeNull()
        ->and($manual->fresh()->redirect_to_id)->toBe($principal->id)
        ->and($inactive->fresh())->active->toBeFalse()->redirect_to_id->toBe($principal->id)->gsc_clicks->toBe(2)
        ->and($newVariant)->active->toBeFalse()->redirect_to_id->toBe($principal->id)
        ->and(SearchQuery::where('slug', 'gsc-test-terreno-quilpue')->value('redirect_to_id'))->toBeNull();

    $this->mock(SemanticPropertySearchService::class)->shouldNotReceive('search');
    $this->get('/es/chile/busqueda/gsc-test-casa-venta-quilpue')
        ->assertRedirect('/es/chile/busqueda/gsc-test-venta-de-casas-quilpue')
        ->assertStatus(301);
});

it('sin --redirect-duplicates activa todas las casi iguales y no redirige', function (): void {
    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-depto-en-renaca', 4, 40, '5'],
        ['/es/chile/busqueda/gsc-test-deptos-renaca', 1, 10, '8'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file]])
        ->expectsOutputToContain('Unificar con --redirect-duplicates')
        ->assertSuccessful();

    expect(SearchQuery::whereIn('slug', ['gsc-test-depto-en-renaca', 'gsc-test-deptos-renaca'])->where('active', true)->whereNull('redirect_to_id')->count())->toBe(2);
});

it('ignora URLs que no son búsquedas y países no habilitados', function (): void {
    $file = gscCsv([
        ['/es/chile/busqueda/gsc-test-valida', 2, 10, '5'],
        ['/es/chile/venta/casas', 50, 500, '3'],
        ['/es/pais-inexistente/busqueda/gsc-test-otro-pais', 9, 90, '4'],
    ]);

    $this->artisan('search-queries:import-gsc', ['files' => [$file]])
        ->expectsOutputToContain('URLs ignoradas (no son búsquedas indexables): 1.')
        ->expectsOutputToContain('pais-inexistente')
        ->assertSuccessful();

    expect(SearchQuery::where('slug', 'gsc-test-otro-pais')->exists())->toBeFalse();
});

it('falla si el archivo no existe', function (): void {
    $this->artisan('search-queries:import-gsc', ['files' => ['docs/no-existe.csv']])->assertFailed();
});

describe('noindex de búsquedas inactivas', function (): void {
    beforeEach(function (): void {
        $this->mock(SemanticPropertySearchService::class)
            ->shouldReceive('search')
            ->andReturn(new LengthAwarePaginator(collect(), 7, 20));
    });

    it('marca noindex las inactivas y las no guardadas si está habilitado', function (): void {
        config(['search.noindex_inactive' => true]);
        SearchQuery::factory()->active()->create(['country' => 'Chile', 'slug' => 'gsc-test-casas-en-vina-del-mar']);
        SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-casas-en-quillota']);

        $this->get('/es/chile/busqueda/gsc-test-casas-en-vina-del-mar')->assertSee('content="index,follow"', false);
        $this->get('/es/chile/busqueda/gsc-test-casas-en-quillota')->assertSee('content="noindex,follow"', false);
        $this->get('/es/chile/busqueda/casa-gsc')->assertSee('content="noindex,follow"', false);
    });

    it('indexa las inactivas si está deshabilitado', function (): void {
        config(['search.noindex_inactive' => false]);
        SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-casas-en-quillota']);

        $this->get('/es/chile/busqueda/gsc-test-casas-en-quillota')->assertSee('content="index,follow"', false);
    });

    it('actualiza la cantidad de anuncios de una búsqueda guardada al visitarla', function (): void {
        $search = SearchQuery::factory()->create(['country' => 'Chile', 'slug' => 'gsc-test-casas-en-quillota', 'results_count' => 0]);

        $this->get('/es/chile/busqueda/gsc-test-casas-en-quillota')->assertSuccessful();

        expect($search->fresh()->results_count)->toBe(7);
    });
});
