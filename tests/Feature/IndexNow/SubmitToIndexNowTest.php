<?php

declare(strict_types=1);

use App\Events\PropertyListingCreated;
use App\Listeners\SubmitToIndexNow;
use App\Models\PropertyListing;
use App\Services\IndexNowService;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

// ---------------------------------------------------------------------------
// IndexNowService
// ---------------------------------------------------------------------------

describe('IndexNowService', function () {
    it('no envía nada cuando INDEXNOW_ENABLED es false', function () {
        config(['indexnow.enabled' => false]);

        Http::fake();

        $service = new IndexNowService();
        $result = $service->submitUrls(['https://example.com/propiedad/1']);

        expect($result)->toBeFalse();
        Http::assertNothingSent();
    });

    it('no envía nada cuando la lista de URLs está vacía', function () {
        config([
            'indexnow.enabled' => true,
            'indexnow.api_key' => 'abc123def456abc123def456abc123de',
            'indexnow.host'    => 'bienesonline.com',
        ]);

        Http::fake();

        $service = new IndexNowService();
        $result = $service->submitUrls([]);

        expect($result)->toBeFalse();
        Http::assertNothingSent();
    });

    it('registra un warning cuando faltan credenciales', function () {
        config([
            'indexnow.enabled' => true,
            'indexnow.api_key' => '',
            'indexnow.host'    => '',
        ]);

        Http::fake();
        Log::shouldReceive('warning')->once()->withArgs(fn($msg) => str_contains($msg, 'faltan credenciales'));

        $service = new IndexNowService();
        $result = $service->submitUrls(['https://example.com/propiedad/1']);

        expect($result)->toBeFalse();
        Http::assertNothingSent();
    });

    it('envía un POST correcto a la API de IndexNow', function () {
        config([
            'indexnow.enabled'  => true,
            'indexnow.api_key'  => 'abc123def456abc123def456abc123de',
            'indexnow.host'     => 'bienesonline.com',
            'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
            'indexnow.logging'  => false,
        ]);

        Http::fake([
            'https://api.indexnow.org/indexnow' => Http::response('', 200),
        ]);

        $urls = [
            'https://bienesonline.com/es/argentina/cordoba/propiedad/1-casa-moderna',
            'https://bienesonline.com/en/argentina/cordoba/propiedad/1-casa-moderna',
        ];

        $service = new IndexNowService();
        $result = $service->submitUrls($urls);

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) use ($urls) {
            $body = $request->data();
            return $request->url() === 'https://api.indexnow.org/indexnow'
                && $body['host'] === 'bienesonline.com'
                && $body['key'] === 'abc123def456abc123def456abc123de'
                && $body['keyLocation'] === 'https://bienesonline.com/abc123def456abc123def456abc123de.txt'
                && $body['urlList'] === $urls;
        });
    });

    it('acepta respuesta 202 como éxito', function () {
        config([
            'indexnow.enabled'  => true,
            'indexnow.api_key'  => 'abc123def456abc123def456abc123de',
            'indexnow.host'     => 'bienesonline.com',
            'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
            'indexnow.logging'  => false,
        ]);

        Http::fake([
            'https://api.indexnow.org/indexnow' => Http::response('', 202),
        ]);

        $service = new IndexNowService();
        $result = $service->submitUrls(['https://bienesonline.com/es/argentina/cordoba/propiedad/1-casa']);

        expect($result)->toBeTrue();
    });

    it('retorna false y loggea warning ante respuesta de error HTTP', function () {
        config([
            'indexnow.enabled'  => true,
            'indexnow.api_key'  => 'abc123def456abc123def456abc123de',
            'indexnow.host'     => 'bienesonline.com',
            'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
            'indexnow.logging'  => false,
        ]);

        Http::fake([
            'https://api.indexnow.org/indexnow' => Http::response('Unauthorized', 401),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(fn($msg) => str_contains($msg, 'respuesta inesperada'));

        $service = new IndexNowService();
        $result = $service->submitUrls(['https://bienesonline.com/es/argentina/cordoba/propiedad/1-casa']);

        expect($result)->toBeFalse();
    });

    it('retorna false y loggea warning ante excepción de red', function () {
        config([
            'indexnow.enabled'  => true,
            'indexnow.api_key'  => 'abc123def456abc123def456abc123de',
            'indexnow.host'     => 'bienesonline.com',
            'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
            'indexnow.logging'  => false,
        ]);

        Http::fake([
            'https://api.indexnow.org/indexnow' => fn() => throw new \Exception('Connection refused'),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(fn($msg) => str_contains($msg, 'error al enviar'));

        $service = new IndexNowService();
        $result = $service->submitUrls(['https://bienesonline.com/es/argentina/cordoba/propiedad/1-casa']);

        expect($result)->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// SubmitToIndexNow listener
// ---------------------------------------------------------------------------

describe('SubmitToIndexNow listener', function () {
    it('envía las URLs de todos los locales al crearse un anuncio', function () {
        config([
            'indexnow.enabled'  => true,
            'indexnow.api_key'  => 'abc123def456abc123def456abc123de',
            'indexnow.host'     => 'bienesonline.com',
            'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
            'indexnow.logging'  => false,
            'locales.available' => ['es', 'en'],
        ]);

        Http::fake([
            'https://api.indexnow.org/indexnow' => Http::response('', 200),
        ]);

        $uniqueId = str_replace('.', '', uniqid('', true));
        $userId = DB::table('users')->insertGetId([
            'name'             => 'IndexNow Test User',
            'email'            => "indexnow-{$uniqueId}@example.com",
            'username'         => "indexnowtest{$uniqueId}",
            'avatar'           => 'demo/default.png',
            'password'         => bcrypt('password'),
            'locale'           => 'es',
            'terms_accepted'   => true,
            'terms_accepted_at'=> now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $listing = PropertyListing::factory()->create(['user_id' => $userId]);

        $seoService    = new SeoService();
        $expectedUrlEs = $seoService->generatePropertyUrl($listing, 'es');
        $expectedUrlEn = $seoService->generatePropertyUrl($listing, 'en');

        $listener = new SubmitToIndexNow(new IndexNowService(), $seoService);
        $listener->handle(new PropertyListingCreated($listing));

        Http::assertSent(function ($request) use ($expectedUrlEs, $expectedUrlEn) {
            $urls = $request->data()['urlList'] ?? [];
            return in_array($expectedUrlEs, $urls) && in_array($expectedUrlEn, $urls);
        });
    });

    it('el listener se encola al dispararse el evento PropertyListingCreated', function () {
        config([
            'indexnow.enabled'  => true,
            'indexnow.api_key'  => 'abc123def456abc123def456abc123de',
            'indexnow.host'     => 'bienesonline.com',
            'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
            'indexnow.logging'  => false,
        ]);

        Queue::fake();

        $uniqueId = str_replace('.', '', uniqid('', true));
        $userId = DB::table('users')->insertGetId([
            'name'             => 'IndexNow Queue Test User',
            'email'            => "indexnow-queue-{$uniqueId}@example.com",
            'username'         => "indexnowqueue{$uniqueId}",
            'avatar'           => 'demo/default.png',
            'password'         => bcrypt('password'),
            'locale'           => 'es',
            'terms_accepted'   => true,
            'terms_accepted_at'=> now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $listing = PropertyListing::factory()->create(['user_id' => $userId]);

        event(new PropertyListingCreated($listing));

        // Los listeners ShouldQueue se envuelven en CallQueuedListener
        Queue::assertPushed(\Illuminate\Events\CallQueuedListener::class, function ($job) {
            return $job->class === SubmitToIndexNow::class;
        });
    });
});

// ---------------------------------------------------------------------------
// Ruta de verificación de clave
// ---------------------------------------------------------------------------

describe('Ruta de verificación IndexNow /{key}.txt', function () {
    it('devuelve la clave correcta cuando el key coincide con la config', function () {
        config(['indexnow.api_key' => 'abc123def456abc123def456abc123de']);

        $response = $this->get('/abc123def456abc123def456abc123de.txt');

        $response->assertSuccessful();
        $response->assertSee('abc123def456abc123def456abc123de');
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    });

    it('devuelve 404 cuando la clave del path no coincide con la config', function () {
        config(['indexnow.api_key' => 'abc123def456abc123def456abc123de']);

        $response = $this->get('/ffffffffffffffffffffffffffffffff.txt');

        $response->assertNotFound();
    });

    it('devuelve 404 cuando INDEXNOW_API_KEY no está configurado', function () {
        config(['indexnow.api_key' => '']);

        $response = $this->get('/abc123def456abc123def456abc123de.txt');

        $response->assertNotFound();
    });
});
