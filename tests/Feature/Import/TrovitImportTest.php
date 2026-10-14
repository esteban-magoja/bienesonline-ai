<?php

declare(strict_types=1);

use App\Events\PropertyListingCreated;
use App\Models\ImportSource;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\Import\ImportSourceRegistry;
use App\Services\Import\ImportSourceRunner;
use App\Services\Import\ImportTypeResolver;
use App\Services\Import\Sources\TrovitDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

const TROVIT_FEED_URL = 'https://feed.example-trovit.test/trovit.xml';

/** No hay un feed Trovit real de prueba: se usan los fixtures de Wasi y Code49, que son variantes del formato. */
function fakeTrovit(string $fixture): void
{
    Http::swap(new \Illuminate\Http\Client\Factory());
    Http::fake([
        'feed.example-trovit.test/*' => Http::response(file_get_contents(base_path("tests/Fixtures/{$fixture}"))),
        '*'                          => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

function trovitAds(string $country): \Illuminate\Support\Collection
{
    return collect(app(TrovitDriver::class)->fetch(['country' => $country, 'feed_url' => TROVIT_FEED_URL]))->keyBy('id');
}

beforeEach(function () {
    Cache::flush();
    Role::firstOrCreate(['name' => 'registered', 'guard_name' => 'web']);
    config([
        'import.block_private_hosts' => false,
        'import.feed_retry_delay_ms' => 0,
        'queue.default'              => 'sync',
    ]);
    Storage::fake('public');
    Event::fake([PropertyListingCreated::class]);
    $this->mock(EmbeddingService::class, fn ($m) => $m->shouldReceive('generate')->andReturn(null));

    PropertyType::updateOrCreate(['country_code' => 'CL', 'value' => 'terreno'], ['label' => 'Terreno', 'value_en' => 'land', 'order' => 2, 'is_active' => true]);
    TransactionType::updateOrCreate(['country_code' => 'CL', 'value' => 'venta'], ['label' => 'Venta', 'value_en' => 'sale', 'order' => 1, 'is_active' => true]);
    TransactionType::updateOrCreate(['country_code' => 'CL', 'value' => 'arriendo'], ['label' => 'Arriendo', 'value_en' => 'rent', 'order' => 2, 'is_active' => true]);
    Cache::flush();
});

it('is registered as an import source', function () {
    expect(app(ImportSourceRegistry::class)->get('trovit'))->toBeInstanceOf(TrovitDriver::class)
        ->and(array_keys(app(ImportSourceRegistry::class)->all()))->toContain('wasi', 'code49', 'trovit');
});

it('reads feeds with nested pictures', function () {
    fakeTrovit('wasi-trovit.xml');

    $ad = trovitAds('Argentina')['10322736'];

    expect($ad['images'])->toHaveCount(6)
        ->and($ad['city'])->toBe('Funes')
        ->and($ad['transaction_type'])->toBeIn(['rent', 'alquiler']);
});

it('reads feeds with flat pictures and street address', function () {
    fakeTrovit('code49-trovit.xml');

    $ads = trovitAds('Chile');

    expect($ads)->toHaveCount(3)
        ->and($ads['7005']['images'])->toHaveCount(3)
        ->and($ads['7005']['address'])->toBe('Manutara')
        ->and($ads['17']['address'])->toBe('Lo Fontecilla')
        ->and($ads['15']['property_type'])->toBe('terreno');
});

it('understands trovit operation types prefixed with the property type', function () {
    $resolver = app(ImportTypeResolver::class);

    expect($resolver->transactionType('Office For Rent', 'CL'))->toBe('arriendo')
        ->and($resolver->transactionType('Parking For Sale', 'CL'))->toBe('venta')
        ->and($resolver->transactionType('Something else', 'CL'))->toBeNull();
});

it('imports the feed through the pipeline', function () {
    fakeTrovit('code49-trovit.xml');
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'web']));
    $source = ImportSource::create([
        'user_id' => $user->id,
        'driver'  => 'trovit',
        'name'    => 'feed.example-trovit.test',
        'config'  => ['country' => 'Chile', 'feed_url' => TROVIT_FEED_URL],
    ]);

    $job = app(ImportSourceRunner::class)->start($source, 'initial')->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->imported_listings)->toBe(3)
        ->and(PropertyListing::where('import_source_id', $source->id)->pluck('source')->unique()->all())->toBe(['trovit']);
});
