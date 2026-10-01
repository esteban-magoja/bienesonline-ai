<?php

declare(strict_types=1);

use App\Events\PropertyListingCreated;
use App\Models\ImportSource;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\Import\FeedFetcher;
use App\Services\Import\ImportSourceException;
use App\Services\Import\ImportSourceRunner;
use App\Services\Import\Sources\WasiTrovitDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Nnjeim\World\Models\Country;
use Spatie\Permission\Models\Role;
use App\Models\CountrySetting;

uses(DatabaseTransactions::class);

const WASI_FEED_URL = 'https://feed.example-wasi.test/xml-trovit.htm';

function wasiFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/wasi-trovit.xml'));
}

/** Feed con los mismos anuncios, aplicando reemplazos de texto. */
function wasiFeed(array $replace = [], array $removeIds = []): string
{
    $xml = wasiFixture();
    foreach ($removeIds as $id) {
        $xml = preg_replace('#<ad>\s*<id><!\[CDATA\[' . $id . '\]\]></id>.*?</ad>#s', '', $xml);
    }

    return strtr($xml, $replace);
}

function fakeWasi(string $body, int $status = 200): void
{
    // Reemplaza los fakes previos (Http::fake acumula y gana el primero que coincide)
    Http::swap(new \Illuminate\Http\Client\Factory());
    Http::fake([
        'feed.example-wasi.test/*' => Http::response($body, $status),
        'images.wasi.co/*'         => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

function premiumUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'web']));

    return $user;
}

function makeSource(): ImportSource
{
    return ImportSource::create([
        'user_id' => premiumUser()->id,
        'driver'  => 'wasi',
        'name'    => 'feed.example-wasi.test',
        'config'  => ['country' => 'Argentina', 'feed_url' => WASI_FEED_URL],
    ]);
}

beforeEach(function () {
    Cache::flush();
    Role::firstOrCreate(['name' => 'registered', 'guard_name' => 'web']);
    config([
        'import.block_private_hosts'  => false,
        'import.feed_retry_delay_ms'  => 0,
        'queue.default'               => 'sync',
    ]);
    Storage::fake('public');
    Event::fake([PropertyListingCreated::class]);
    $this->mock(EmbeddingService::class, fn ($m) => $m->shouldReceive('generate')->andReturn(null));
});

describe('WasiTrovitDriver', function () {
    it('normalizes the trovit feed', function () {
        fakeWasi(wasiFixture());

        $ads = app(WasiTrovitDriver::class)->fetch(['country' => 'Argentina', 'feed_url' => WASI_FEED_URL]);

        expect($ads)->toHaveCount(3);

        $rent = collect($ads)->firstWhere('id', '10322736');
        expect($rent)
            ->title->toBe('Alquiler Monoambiente amoblado con cochera - Edificio Gloria')
            ->currency->toBe('ARS')
            ->price->toBe(750000)
            ->city->toBe('Funes')
            ->state->toBe('Santa Fe')
            ->country->toBe('Argentina')
            ->bathrooms->toBe(1)
            ->parking_spaces->toBe(1)
            ->and($rent['images'])->toHaveCount(6)
            ->and($rent['transaction_type'])->toBeIn(['rent', 'alquiler']);
    });

    it('rounds decimal areas because the columns are integers', function () {
        fakeWasi(wasiFeed(["<floor_area unit='meters'><![CDATA[72]]></floor_area>" => "<floor_area unit='meters'><![CDATA[668.92]]></floor_area>"]));

        $ads = app(WasiTrovitDriver::class)->fetch(['country' => 'Argentina', 'feed_url' => WASI_FEED_URL]);

        expect(collect($ads)->pluck('area')->all())->toContain(669)->each->toBeInt();
    });

    it('keeps line breaks in descriptions when the feed has them', function () {
        fakeWasi(wasiFeed(['<![CDATA[Alquiler Monoambiente amoblado con cochera - Edificio Gloria en  - Funes - Santa Fe - Argentina ]]>' => "<![CDATA[Línea uno<br>Línea dos\n\n\n\n<p>Párrafo</p>  fin]]>"]));

        $ads = app(WasiTrovitDriver::class)->fetch(['country' => 'Argentina', 'feed_url' => WASI_FEED_URL]);

        expect(collect($ads)->firstWhere('id', '10322736')['description'])->toBe("Línea uno\nLínea dos\n\nPárrafo\nfin");
    });

    it('decodes html entities in descriptions', function () {
        fakeWasi(wasiFixture());

        $ads = app(WasiTrovitDriver::class)->fetch(['country' => 'Argentina', 'feed_url' => WASI_FEED_URL]);
        $sale = collect($ads)->firstWhere('id', '10306217');

        expect($sale['description'])->toContain('ubicación')->not->toContain('&oacute;');
    });

    it('sends a random cache-buster and retries invalid responses', function () {
        Http::fake([
            'feed.example-wasi.test/*' => Http::sequence()
                ->push('<html>error de cache</html>', 200)
                ->push(wasiFixture(), 200),
            'images.wasi.co/*' => Http::response('x'),
        ]);

        $ads = app(WasiTrovitDriver::class)->fetch(['country' => 'Argentina', 'feed_url' => WASI_FEED_URL]);

        expect($ads)->toHaveCount(3);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'xml-trovit.htm?v='));
    });

    it('fails with a friendly error when the feed is never valid', function () {
        fakeWasi('<html>nope</html>');

        app(WasiTrovitDriver::class)->fetch(['country' => 'Argentina', 'feed_url' => WASI_FEED_URL]);
    })->throws(ImportSourceException::class);
});

describe('FeedFetcher', function () {
    it('blocks private and non-http urls', function (string $url) {
        config(['import.block_private_hosts' => true]);

        app(FeedFetcher::class)->assertSafeUrl($url);
    })->with([
        'http://127.0.0.1/feed.xml',
        'http://192.168.1.10/feed.xml',
        'http://10.0.0.5/feed.xml',
        'http://169.254.169.254/latest/meta-data',
        'file:///etc/passwd',
        'ftp://example.com/feed.xml',
    ])->throws(ImportSourceException::class);
});

describe('ImportLocationResolver and currency', function () {
    it('normalizes Chilean regions and provinces to the platform state names', function () {
        Cache::flush();
        $cl = Country::forceCreate(['name' => 'Chile', 'iso2' => 'CL', 'iso3' => 'CHL', 'phone_code' => '56', 'region' => 'Americas', 'subregion' => 'South America', 'status' => 1]);
        foreach (['Región Metropolitana de Santiago', 'Valparaíso', 'Biobío', 'Maule'] as $name) {
            \Nnjeim\World\Models\State::forceCreate(['country_id' => $cl->id, 'name' => $name, 'country_code' => 'CL']);
        }

        $resolver = app(\App\Services\Import\ImportLocationResolver::class);

        expect($resolver->state('Metropolitana de Santiago', 'Chile'))->toBe('Región Metropolitana de Santiago')
            ->and($resolver->state('Región de Valparaiso', 'Chile'))->toBe('Valparaíso')
            ->and($resolver->state('Concepcion', 'Chile'))->toBe('Biobío')      // provincia → región
            ->and($resolver->state('Cordillera', 'Chile'))->toBe('Región Metropolitana de Santiago')
            ->and($resolver->state('Maule', 'Chile'))->toBe('Maule')
            ->and($resolver->state('Algo Desconocido', 'Chile'))->toBe('Algo Desconocido')
            ->and($resolver->state('Santa Fe', 'Argentina'))->toBe('Santa Fe');
    });

    it('normalizes currencies', function () {
        $c = app(\App\Services\Import\ImportCurrencyNormalizer::class);

        expect($c->normalize('UF', 'CLP'))->toBe('CLF')
            ->and($c->normalize('clp', 'CLP'))->toBe('CLP')
            ->and($c->normalize('U$D', 'ARS'))->toBe('USD')
            ->and($c->normalize('', 'ARS'))->toBe('ARS')
            ->and($c->normalize('$', 'ARS'))->toBe('ARS');
    });
});

describe('ImportTypeResolver', function () {
    it('maps regional types through value_en', function () {
        Cache::flush();
        PropertyType::create(['country_code' => 'AR', 'value' => 'terreno', 'label' => 'Terreno', 'value_en' => 'land', 'order' => 1, 'is_active' => true]);
        PropertyType::create(['country_code' => 'AR', 'value' => 'departamento', 'label' => 'Departamento', 'value_en' => 'apartment', 'order' => 2, 'is_active' => true]);
        TransactionType::create(['country_code' => 'AR', 'value' => 'alquiler', 'label' => 'Alquiler', 'value_en' => 'rent', 'order' => 1, 'is_active' => true]);

        $resolver = app(\App\Services\Import\ImportTypeResolver::class);

        expect($resolver->propertyType('Lote', 'AR'))->toBe('terreno')
            ->and($resolver->propertyType('Apartamento', 'AR'))->toBe('departamento')
            ->and($resolver->propertyType('Oficina', 'AR'))->toBe('oficina')
            ->and($resolver->transactionType('For Rent', 'AR'))->toBe('alquiler')
            ->and($resolver->transactionType('algo raro', 'AR'))->toBeNull();
    });
});

describe('import and sync pipeline', function () {
    it('imports listings with images on the first run', function () {
        fakeWasi(wasiFixture());
        $source = makeSource();

        $job = app(ImportSourceRunner::class)->start($source, 'initial');

        $job->refresh();
        expect($job->status)->toBe('completed')
            ->and($job->total_listings)->toBe(3)
            ->and($job->imported_listings)->toBe(3);

        $listings = PropertyListing::where('import_source_id', $source->id)->get();
        expect($listings)->toHaveCount(3)
            ->and($listings->pluck('source')->unique()->all())->toBe(['wasi'])
            ->and($listings->pluck('user_id')->unique()->all())->toBe([$source->user_id]);

        $listing = $listings->firstWhere('external_id', '10322736');
        expect($listing->images()->count())->toBe(6)
            ->and($listing->images()->where('is_primary', true)->count())->toBe(1)
            ->and($listing->images()->whereNotNull('source_url')->count())->toBe(6);

        expect($source->refresh())
            ->last_sync_status->toBe('completed')
            ->consecutive_failures->toBe(0);
    });

    it('does not duplicate listings when syncing an unchanged feed', function () {
        fakeWasi(wasiFixture());
        $source = makeSource();
        $runner = app(ImportSourceRunner::class);

        $runner->start($source, 'initial');
        $job = $runner->start($source->refresh(), 'sync')->refresh();

        expect(PropertyListing::where('import_source_id', $source->id)->count())->toBe(3)
            ->and($job->imported_listings)->toBe(0)
            ->and($job->updated_listings)->toBe(0)
            ->and($job->skipped_listings)->toBe(3);
        Event::assertDispatchedTimes(PropertyListingCreated::class, 3);
    });

    it('updates changed fields but respects manual edits by the user', function () {
        fakeWasi(wasiFixture());
        $source = makeSource();
        $runner = app(ImportSourceRunner::class);
        $runner->start($source, 'initial');

        // El usuario edita el título de un anuncio a mano
        $listing = PropertyListing::where('external_id', '10322736')->first();
        $listing->update(['title' => 'Mi título personalizado']);

        // La fuente cambia el título y el precio del mismo anuncio
        fakeWasi(wasiFeed([
            'Alquiler Monoambiente amoblado con cochera - Edificio Gloria' => 'Título nuevo en Wasi',
            '<![CDATA[750000]]>' => '<![CDATA[820000]]>',
        ]));
        $job = $runner->start($source->refresh(), 'sync')->refresh();

        $listing->refresh();
        expect($listing->title)->toBe('Mi título personalizado')
            ->and((float) $listing->price)->toBe(820000.0)
            ->and($job->updated_listings)->toBe(1);
    });

    it('deactivates missing listings after the tolerance and reactivates them when they return', function () {
        $source = makeSource();
        $runner = app(ImportSourceRunner::class);

        fakeWasi(wasiFixture());
        $runner->start($source, 'initial');

        // Un anuncio desaparece del feed
        fakeWasi(wasiFeed(removeIds: ['10306217']));
        $runner->start($source->refresh(), 'sync');
        expect(PropertyListing::where('external_id', '10306217')->first())
            ->is_active->toBeTrue()
            ->import_missing_count->toBe(1);

        $job = $runner->start($source->refresh(), 'sync')->refresh();
        $missing = PropertyListing::where('external_id', '10306217')->first();
        expect($missing->is_active)->toBeFalse()
            ->and($job->deactivated_listings)->toBe(1)
            ->and(PropertyListing::where('external_id', '10322736')->first()->is_active)->toBeTrue();

        // Vuelve a aparecer
        fakeWasi(wasiFixture());
        $runner->start($source->refresh(), 'sync');
        expect($missing->refresh())->is_active->toBeTrue()->import_missing_count->toBe(0);
    });

    it('does not reactivate listings the user deactivated manually', function () {
        $source = makeSource();
        $runner = app(ImportSourceRunner::class);

        fakeWasi(wasiFixture());
        $runner->start($source, 'initial');

        PropertyListing::where('external_id', '10306217')->first()->update(['is_active' => false]);

        $runner->start($source->refresh(), 'sync');

        expect(PropertyListing::where('external_id', '10306217')->first()->is_active)->toBeFalse();
    });

    it('removes images that disappeared from the feed', function () {
        $source = makeSource();
        $runner = app(ImportSourceRunner::class);

        fakeWasi(wasiFixture());
        $runner->start($source, 'initial');

        fakeWasi(wasiFeed(['gr109468620260826073139.jpeg' => 'gr_nueva.jpeg']));
        $runner->start($source->refresh(), 'sync');

        $listing = PropertyListing::where('external_id', '10322736')->first();
        $urls = $listing->images()->pluck('source_url')->all();

        expect($urls)->toHaveCount(6)
            ->and(implode(',', $urls))->toContain('gr_nueva.jpeg')->not->toContain('gr109468620260826073139');
    });

    it('never deactivates anything when the feed is broken', function () {
        $source = makeSource();
        $runner = app(ImportSourceRunner::class);

        fakeWasi(wasiFixture());
        $runner->start($source, 'initial');

        fakeWasi('<html>500</html>', 500);
        $job = $runner->start($source->refresh(), 'sync')->refresh();

        expect($job->status)->toBe('failed')
            ->and(PropertyListing::where('import_source_id', $source->id)->where('is_active', true)->count())->toBe(3)
            ->and($source->refresh()->consecutive_failures)->toBe(1)
            ->and($source->last_sync_status)->toBe('failed');
    });

    it('marks the source as errored after repeated failures', function () {
        config(['import.max_consecutive_failures' => 2]);
        $source = makeSource();
        $runner = app(ImportSourceRunner::class);

        fakeWasi('<html>500</html>', 500);
        $runner->start($source, 'initial');
        $runner->start($source->refresh(), 'sync');

        expect($source->refresh()->status)->toBe(ImportSource::STATUS_ERROR);
        expect(ImportSource::dueForSync()->whereKey($source->id)->exists())->toBeFalse();
    });

    it('does not start a second job while one is running', function () {
        $source = makeSource();
        $source->importJobs()->create(['user_id' => $source->user_id, 'status' => 'processing', 'type' => 'sync']);

        expect(app(ImportSourceRunner::class)->start($source, 'sync'))->toBeNull();
    });
});

describe('imports:sync command', function () {
    it('only syncs sources whose interval has elapsed', function () {
        fakeWasi(wasiFixture());
        $due = makeSource();
        $due->update(['last_synced_at' => now()->subHours(25)]);

        $fresh = makeSource();
        $fresh->update(['last_synced_at' => now()->subHours(2)]);

        $paused = makeSource();
        $paused->update(['sync_enabled' => false]);

        $this->artisan('imports:sync')->assertSuccessful();

        expect($due->importJobs()->count())->toBe(1)
            ->and($fresh->importJobs()->count())->toBe(0)
            ->and($paused->importJobs()->count())->toBe(0);
    });
});

describe('dashboard section', function () {
    beforeEach(function () {
        $this->user = premiumUser();
        $this->actingAs($this->user);
    });

    it('rejects an unreachable feed without saving the source', function () {
        Country::forceCreate(['name' => 'Argentina', 'iso2' => 'AR', 'iso3' => 'ARG', 'phone_code' => '54', 'region' => 'Americas', 'subregion' => 'South America', 'status' => 1]);
        CountrySetting::enable('AR', 1);
        fakeWasi('<html>nope</html>', 404);

        $this->post(route('dashboard.imports.store'), [
            'driver' => 'wasi', 'country' => 'Argentina', 'feed_url' => WASI_FEED_URL,
        ])->assertSessionHasErrors('feed_url');

        expect(ImportSource::count())->toBe(0);
    });

    it('validates the driver', function () {
        $this->post(route('dashboard.imports.store'), ['driver' => 'inexistente'])
            ->assertSessionHasErrors('driver');
    });

    it('does not allow managing sources of other users', function () {
        $other = makeSource();

        $this->post(route('dashboard.imports.sync', $other))->assertForbidden();
        $this->patch(route('dashboard.imports.toggle', $other))->assertForbidden();
        $this->delete(route('dashboard.imports.destroy', $other))->assertForbidden();
    });

    it('keeps imported listings when the source is disconnected', function () {
        fakeWasi(wasiFixture());
        $source = ImportSource::create([
            'user_id' => $this->user->id, 'driver' => 'wasi', 'name' => 'x',
            'config'  => ['country' => 'Argentina', 'feed_url' => WASI_FEED_URL],
        ]);
        app(ImportSourceRunner::class)->start($source, 'initial');

        $this->delete(route('dashboard.imports.destroy', $source))->assertRedirect();

        expect(ImportSource::count())->toBe(0)
            ->and(PropertyListing::where('user_id', $this->user->id)->where('source', 'wasi')->count())->toBe(3)
            ->and(PropertyListing::where('user_id', $this->user->id)->whereNotNull('import_source_id')->count())->toBe(0);
    });

    it('shows the subscription notice to non-premium users and blocks their actions', function () {
        $this->actingAs(User::factory()->create());

        $this->post(route('dashboard.imports.store'), ['driver' => 'wasi'])->assertRedirect(route('dashboard.imports.index'));
        $this->post(route('dashboard.imports.sync', makeSource()))->assertRedirect(route('dashboard.imports.index'));
        expect(ImportSource::count())->toBe(1);
    });

    it('stops syncing sources of users who lost premium', function () {
        $source = makeSource();
        $source->user->syncRoles([]);
        $source->user->clearUserCache();

        expect(app(ImportSourceRunner::class)->start($source->refresh(), 'sync'))->toBeNull();
    });
});
