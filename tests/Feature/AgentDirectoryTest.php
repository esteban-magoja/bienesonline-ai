<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function createAgent(array $overrides = []): int
{
    $uniqueId = str_replace('.', '', uniqid('', true));

    return DB::table('users')->insertGetId(array_merge([
        'name' => 'Agente ' . $uniqueId,
        'email' => "agent-{$uniqueId}@example.com",
        'username' => "agent{$uniqueId}",
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'agency' => 'Inmobiliaria ' . $uniqueId,
        'city' => 'Villa Carlos Paz',
        'state' => 'Córdoba',
        'country' => 'Argentina',
        'locale' => 'es',
        'terms_accepted' => true,
        'terms_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function createListing(int $userId, array $overrides = []): void
{
    $uniqueId = str_replace('.', '', uniqid('', true));

    DB::table('property_listings')->insert(array_merge([
        'user_id' => $userId,
        'title' => 'Listado ' . $uniqueId,
        'description' => 'Descripcion de prueba para listado',
        'property_type' => 'casa',
        'transaction_type' => 'venta',
        'price' => 100000,
        'area' => 120,
        'city' => 'Villa Carlos Paz',
        'state' => 'Córdoba',
        'country' => 'Argentina',
        'currency' => 'USD',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('lists agencies ordered by active listing count in the selected location', function () {
    $topAgencyId = createAgent([
        'agency' => 'Inmobiliaria Top',
        'username' => 'inmobiliaria-top',
    ]);
    $secondAgencyId = createAgent([
        'agency' => 'Inmobiliaria Segunda',
        'username' => 'inmobiliaria-segunda',
    ]);
    $noAgencyId = createAgent([
        'agency' => null,
        'name' => 'Sin Agencia',
        'username' => 'sin-agencia',
        'email' => 'sin-agencia@example.com',
    ]);
    $inactiveAgencyId = createAgent([
        'agency' => 'Inmobiliaria Inactiva',
        'username' => 'inmobiliaria-inactiva',
    ]);

    createListing($topAgencyId);
    createListing($topAgencyId, ['title' => 'Listado Top 2']);
    createListing($topAgencyId, ['title' => 'Listado Top 3']);

    createListing($secondAgencyId);

    createListing($noAgencyId, ['title' => 'No debe verse']);
    createListing($inactiveAgencyId, ['title' => 'Inactivo', 'is_active' => false]);

    $this->get('/es/argentina/inmobiliarias/cordoba/villa-carlos-paz')
        ->assertSuccessful()
        ->assertSeeInOrder(['Inmobiliaria Top', 'Inmobiliaria Segunda'])
        ->assertDontSee('Sin Agencia')
        ->assertDontSee('Inmobiliaria Inactiva');
});

it('supports pagination in the english directory', function () {
    for ($i = 1; $i <= 20; $i++) {
        $agentId = createAgent([
            'agency' => "Agency {$i}",
            'username' => "agency-{$i}",
            'email' => "agency-{$i}@example.com",
        ]);

        createListing($agentId, [
            'title' => "Listing {$i}",
            'transaction_type' => 'sale',
            'state' => 'Cordoba',
        ]);
    }

    $this->get('/en/agents')
        ->assertSuccessful()
        ->assertSee('?page=2');
});

it('returns not found for an invalid country slug', function () {
    createAgent([
        'agency' => 'Inmobiliaria Valida',
        'username' => 'inmobiliaria-valida',
        'email' => 'inmobiliaria-valida@example.com',
    ]);

    $this->get('/es/pais-inexistente/inmobiliarias')->assertNotFound();
});

it('matches country and location filters despite casing and whitespace differences', function () {
    $agentId = createAgent([
        'agency' => 'Inmobiliaria Normalizada',
        'username' => 'inmobiliaria-normalizada',
        'email' => 'inmobiliaria-normalizada@example.com',
    ]);

    createListing($agentId, [
        'country' => ' ARGENTINA ',
        'state' => 'CORDOBA',
        'city' => 'VILLA CARLOS PAZ',
    ]);

    $this->get('/es/argentina/inmobiliarias/cordoba/villa-carlos-paz')
        ->assertSuccessful()
        ->assertSee('Inmobiliaria Normalizada')
        ->assertSee('1 anuncio publicado');
});

it('shows the filtered location instead of the agents profile location', function () {
    $agentId = createAgent([
        'agency' => 'Agencia Multipaís',
        'username' => 'agencia-multipais',
        'email' => 'agencia-multipais@example.com',
        'city' => 'Cuenca',
        'state' => 'Azuay',
        'country' => 'Ecuador',
    ]);

    createListing($agentId, [
        'country' => 'Argentina',
        'state' => 'Córdoba',
        'city' => 'Villa Carlos Paz',
    ]);
    createListing($agentId, [
        'country' => 'Argentina',
        'state' => 'Córdoba',
        'city' => 'Córdoba',
        'title' => 'Listado Córdoba',
    ]);

    $this->get('/es/argentina/inmobiliarias')
        ->assertSuccessful()
        ->assertSee('Agencia Multipaís')
        ->assertSee('Argentina')
        ->assertDontSee('Cuenca, Azuay, Ecuador');
});

it('provides navigation links for available countries, states, and cities', function () {
    $agentId = createAgent([
        'agency' => 'Agencia Navegable',
        'username' => 'agencia-navegable',
        'email' => 'agencia-navegable@example.com',
    ]);

    createListing($agentId, [
        'country' => 'Argentina',
        'state' => 'Córdoba',
        'city' => 'Villa Carlos Paz',
    ]);

    $this->get('/es/inmobiliarias')
        ->assertSuccessful()
        ->assertSee('/es/argentina/inmobiliarias', false);

    $this->get('/es/argentina/inmobiliarias')
        ->assertSuccessful()
        ->assertSee('Explorar por zona')
        ->assertSee('<details', false)
        ->assertSee('/es/argentina/inmobiliarias/cordoba', false);

    $this->get('/es/argentina/inmobiliarias/cordoba')
        ->assertSuccessful()
        ->assertSee('/es/argentina/inmobiliarias/cordoba/villa-carlos-paz', false);

    $this->get('/es/argentina/inmobiliarias/cordoba/villa-carlos-paz')
        ->assertSuccessful()
        ->assertDontSee('Explorar por ciudad')
        ->assertDontSee('/es/argentina/inmobiliarias/cordoba/cordoba', false);
});

it('does not capture property listing routes', function () {
    $agentId = createAgent();
    createListing($agentId);

    $this->get('/es/argentina/venta')
        ->assertSuccessful();

    expect($this->app['router']->currentRouteName())->toBe('property.listings');
});
