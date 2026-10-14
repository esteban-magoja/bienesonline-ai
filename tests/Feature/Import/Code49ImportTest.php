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
use App\Services\Import\Sources\Code49Driver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

const CODE49_FEED_URL = 'https://feed.example-code49.test/exportacao/bienesonline.xml';

function fakeCode49(): void
{
    Http::swap(new \Illuminate\Http\Client\Factory());
    Http::fake([
        'feed.example-code49.test/*'   => Http::response(file_get_contents(base_path('tests/Fixtures/code49-trovit.xml'))),
        'images.example-code49.test/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

function code49Ads(): \Illuminate\Support\Collection
{
    return collect(app(Code49Driver::class)->fetch(['country' => 'Chile', 'feed_url' => CODE49_FEED_URL]))->keyBy('id');
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

    PropertyType::updateOrCreate(['country_code' => 'CL', 'value' => 'casa'], ['label' => 'Casa', 'value_en' => 'house', 'order' => 1, 'is_active' => true]);
    PropertyType::updateOrCreate(['country_code' => 'CL', 'value' => 'terreno'], ['label' => 'Terreno', 'value_en' => 'land', 'order' => 2, 'is_active' => true]);
    TransactionType::updateOrCreate(['country_code' => 'CL', 'value' => 'venta'], ['label' => 'Venta', 'value_en' => 'sale', 'order' => 1, 'is_active' => true]);
    Cache::flush();
});

it('is registered as an import source', function () {
    expect(app(ImportSourceRegistry::class)->get('code49'))->toBeInstanceOf(Code49Driver::class);
});

it('normalizes the code49 feed', function () {
    fakeCode49();

    $ads = code49Ads();

    expect($ads)->toHaveCount(3);

    expect($ads['7005'])
        ->title->toBe('Luminosa casa de dos pisos semi renovada La Florida')
        ->property_type->toBe('casa')
        ->transaction_type->toBe('venta')
        ->price->toEqual(3795)
        ->currency->toBe('CLF')
        ->bedrooms->toBe(3)
        ->area->toBe(75)
        ->lotsize->toBe(162)
        ->address->toBe('Manutara')
        ->city->toBe('Santiago')
        ->country->toBe('Chile')
        ->latitude->toBeNull()
        ->and($ads['7005']['description'])->toContain("semi renovada, ubicada en cómodo barrio residencial.\nUF 3.980")
        ->and($ads['7005']['images'])->toBe([
            'https://images.example-code49.test/admin/imovel/7005-1.jpg',
            'https://images.example-code49.test/admin/imovel/7005-2.jpg',
            'https://images.example-code49.test/admin/imovel/7005-3.jpg',
        ]);
});

it('builds the address without repeating the city or duplicated parts', function () {
    fakeCode49();

    expect(code49Ads()['17']['address'])->toBe('Lo Fontecilla');
});

it('maps "Land For Sale" ads without property type to land', function () {
    fakeCode49();

    expect(code49Ads()['15'])
        ->property_type->toBe('terreno')
        ->transaction_type->toBe('venta')
        ->currency->toBe('CLP')
        ->address->toBeNull();
});

it('imports the feed with images through the pipeline', function () {
    fakeCode49();
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'web']));
    $source = ImportSource::create([
        'user_id' => $user->id,
        'driver'  => 'code49',
        'name'    => 'feed.example-code49.test',
        'config'  => ['country' => 'Chile', 'feed_url' => CODE49_FEED_URL],
    ]);

    $job = app(ImportSourceRunner::class)->start($source, 'initial')->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->imported_listings)->toBe(3);

    $listings = PropertyListing::where('import_source_id', $source->id)->get();
    expect($listings->pluck('source')->unique()->all())->toBe(['code49'])
        ->and($listings->firstWhere('external_id', '7005')->images()->count())->toBe(3);
});
