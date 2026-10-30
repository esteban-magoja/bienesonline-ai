<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function () {
    deactivateExistingListings();

    $this->username = 'listing-map-' . uniqid();
    $this->userId = DB::table('users')->insertGetId([
        'name' => 'Usuario mapa',
        'email' => $this->username . '@example.com',
        'username' => $this->username,
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->createListing = function (array $attributes = []): int {
        return DB::table('property_listings')->insertGetId(array_merge([
            'user_id' => $this->userId,
            'title' => 'Casa para el mapa',
            'description' => 'Descripción de prueba',
            'property_type' => 'casa',
            'transaction_type' => 'venta',
            'price' => 100000,
            'area' => 120,
            'city' => 'Villa Carlos Paz',
            'state' => 'Córdoba',
            'country' => 'Argentina',
            'currency' => 'USD',
            'latitude' => -31.4241,
            'longitude' => -64.4978,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    };
});

it('links the listing to its map view when listings have a location', function () {
    ($this->createListing)();

    $this->get('/es/argentina/venta?min_price=5000&page=2')
        ->assertSuccessful()
        ->assertSee('/es/argentina/venta?min_price=5000&amp;view=map', false);
});

it('hides the map link when no listing has a location', function () {
    ($this->createListing)(['latitude' => null, 'longitude' => null]);

    $this->get('/es/argentina')
        ->assertSuccessful()
        ->assertDontSee('view=map', false);
});

it('renders the map view as noindex with the list as canonical', function () {
    ($this->createListing)();
    ($this->createListing)(['latitude' => null, 'longitude' => null]);

    $this->get('/es/argentina/venta?view=map')
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex,follow">', false)
        ->assertSee('<link rel="canonical" href="' . url('/es/argentina/venta') . '">', false)
        ->assertSee('1 anuncio de este listado no tiene ubicación')
        ->assertSee('listingMap(', false);
});

it('shows the listing navigation in the map view pointing to other map views', function () {
    ($this->createListing)();

    $this->get('/es/argentina?view=map')
        ->assertSuccessful()
        ->assertSee('Por tipo de inmueble')
        ->assertSee('/es/argentina/casa?view=map', false)
        ->assertSee('/es/argentina/cordoba?view=map', false);
});

it('returns only active listings with a location that match the listing filters', function () {
    $mappable = ($this->createListing)(['title' => 'Casa ubicable']);
    ($this->createListing)(['latitude' => null, 'longitude' => null]);
    ($this->createListing)(['latitude' => 0, 'longitude' => 0]);
    ($this->createListing)(['is_active' => false]);
    ($this->createListing)(['transaction_type' => 'alquiler']);
    ($this->createListing)(['price' => 900000]);

    $response = $this->getJson('/es/map-data?path=argentina/venta&max_price=500000')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('listings.0.id', $mappable)
        ->assertJsonPath('listings.0.title', 'Casa ubicable')
        ->assertJsonPath('listings.0.lat', -31.4241)
        ->assertJsonPath('listings.0.price', 100000);

    expect($response->json('listings.0.url'))->toContain("/es/argentina/villa-carlos-paz/propiedad/{$mappable}-");
});

it('limits the map data to the visible area', function () {
    $inside = ($this->createListing)();
    ($this->createListing)(['city' => 'Rosario', 'state' => 'Santa Fe', 'latitude' => -32.9442, 'longitude' => -60.6505]);

    $this->getJson('/es/map-data?path=argentina&bbox=-31.6,-64.7,-31.2,-64.3')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('listings.0.id', $inside);
});

it('rejects an invalid area or listing path', function () {
    $this->getJson('/es/map-data?path=argentina&bbox=1,2,3')->assertStatus(422);
    $this->getJson('/es/map-data?path=argentina&bbox=10,0,-10,5')->assertStatus(422);
    $this->getJson('/es/map-data?path=pais-inexistente')->assertNotFound();
    $this->getJson('/es/map-data')->assertStatus(422);
});

it('links the property detail to nearby listings on the map', function () {
    $id = ($this->createListing)();

    $this->get("/es/argentina/villa-carlos-paz/propiedad/{$id}-casa-para-el-mapa")
        ->assertSuccessful()
        ->assertSee("/es/argentina/venta/casa/cordoba?view=map&amp;lat=-31.4241&amp;lng=-64.4978&amp;zoom=14&amp;highlight={$id}", false);
});

it('links the realtor profile to its map view when listings have a location', function () {
    ($this->createListing)();

    $this->get("/es/inmobiliaria/{$this->username}?transaction_type=venta&page=2")
        ->assertSuccessful()
        ->assertSee("/es/inmobiliaria/{$this->username}?transaction_type=venta&amp;view=map", false);
});

it('hides the profile map link when no listing has a location', function () {
    ($this->createListing)(['latitude' => null, 'longitude' => null]);

    $this->get("/es/inmobiliaria/{$this->username}")
        ->assertSuccessful()
        ->assertDontSee('view=map', false);
});

it('renders the profile map view with its filters as selects', function () {
    ($this->createListing)();
    ($this->createListing)(['transaction_type' => 'alquiler']);

    $this->get("/es/inmobiliaria/{$this->username}?view=map&transaction_type=venta")
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex,follow">', false)
        ->assertSee('<link rel="canonical" href="' . url("/es/inmobiliaria/{$this->username}") . '">', false)
        ->assertSee("/es/inmobiliaria/{$this->username}?transaction_type=venta&amp;view=map\" selected>Venta (1)", false)
        ->assertSee("/es/inmobiliaria/{$this->username}?transaction_type=alquiler&amp;view=map", false)
        ->assertSee('listingMap(', false);
});

it('returns only the realtor active listings with a location in the profile map data', function () {
    $mappable = ($this->createListing)();
    ($this->createListing)(['transaction_type' => 'alquiler']);
    ($this->createListing)(['latitude' => null, 'longitude' => null]);
    ($this->createListing)(['is_active' => false]);

    $otherUserId = DB::table('users')->insertGetId([
        'name' => 'Otro usuario',
        'email' => 'listing-map-other-' . uniqid() . '@example.com',
        'username' => 'listing-map-other-' . uniqid(),
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    ($this->createListing)(['user_id' => $otherUserId]);

    $this->getJson("/es/map-data/profile/{$this->username}?transaction_type=venta")
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('listings.0.id', $mappable);

    $this->getJson('/es/map-data/profile/usuario-inexistente')->assertNotFound();
});

it('passes only a numeric highlighted listing to the map', function () {
    ($this->createListing)();

    $this->get('/es/argentina?view=map&highlight=53')
        ->assertSuccessful()
        ->assertSee('highlight\\u0022:53', false);

    $this->get('/es/argentina?view=map&highlight=abc')
        ->assertSuccessful()
        ->assertSee('highlight\\u0022:null', false);
});
