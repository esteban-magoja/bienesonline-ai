<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\PropertyListing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function createProfileTestUser(array $overrides = []): User
{
    $uniqueId = str_replace('.', '', uniqid('', true));
    $userId = DB::table('users')->insertGetId(array_merge([
        'name' => 'Perfil ' . $uniqueId,
        'email' => "profile-{$uniqueId}@example.com",
        'username' => "profile{$uniqueId}",
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'agency' => 'Agencia de Perfil',
        'address' => 'Av. Principal 123',
        'country' => 'Ecuador',
        'locale' => 'es',
        'terms_accepted' => true,
        'terms_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return User::findOrFail($userId);
}

function createProfileListing(int $userId, string $title, array $overrides = []): void
{
    PropertyListing::query()->create(array_merge([
        'user_id' => $userId,
        'title' => $title,
        'description' => 'Descripcion de prueba',
        'property_type' => 'casa',
        'transaction_type' => 'venta',
        'price' => 100000,
        'area' => 100,
        'city' => 'Cuenca',
        'state' => 'Azuay',
        'country' => 'Ecuador',
        'currency' => 'USD',
        'is_active' => true,
    ], $overrides));
}

it('links the realtor breadcrumb to the profile country directory and shows company details', function () {
    $user = createProfileTestUser();
    $user->setProfileKeyValue('about', 'Somos una empresa inmobiliaria familiar.');

    $this->get("/es/inmobiliaria/{$user->username}")
        ->assertSuccessful()
        ->assertSee('/es/ecuador/inmobiliarias', false)
        ->assertSee('Descripción de la empresa')
        ->assertSee('Somos una empresa inmobiliaria familiar.')
        ->assertSee('Dirección')
        ->assertSee('Av. Principal 123');
});

it('links the realtor breadcrumb to the general directory without a profile country', function () {
    $user = createProfileTestUser([
        'country' => null,
        'email' => 'profile-no-country@example.com',
        'username' => 'profile-no-country',
    ]);

    $this->get("/es/inmobiliaria/{$user->username}")
        ->assertSuccessful()
        ->assertSee('/es/inmobiliarias"', false);
});

it('filters profile listings by country, state, and city', function () {
    $user = createProfileTestUser([
        'email' => 'profile-locations@example.com',
        'username' => 'profile-locations',
    ]);

    createProfileListing($user->id, 'Anuncio Ecuador');
    createProfileListing($user->id, 'Anuncio Argentina', [
        'country' => 'Argentina',
        'state' => 'Córdoba',
        'city' => 'Villa Carlos Paz',
    ]);

    $this->get("/es/inmobiliaria/{$user->username}?country=Argentina&state=C%C3%B3rdoba&city=Villa%20Carlos%20Paz")
        ->assertSuccessful()
        ->assertSee('Anuncio Argentina')
        ->assertDontSee('Anuncio Ecuador')
        ->assertSee('Argentina');
});
