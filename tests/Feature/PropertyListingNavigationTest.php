<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

it('completes the listing navigation from property type to locality', function () {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Usuario navegación',
        'email' => 'listing-navigation-' . uniqid() . '@example.com',
        'username' => 'listing-navigation-' . uniqid(),
        'avatar' => 'demo/default.png',
        'password' => bcrypt('password'),
        'locale' => 'es',
        'terms_accepted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $createListing = function (array $attributes) use ($userId): void {
        DB::table('property_listings')->insert(array_merge([
            'user_id' => $userId,
            'title' => 'Listado de navegación',
            'description' => 'Descripción de prueba',
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
        ], $attributes));
    };

    $createListing([]);
    $createListing(['city' => 'Tanti']);
    $createListing([
        'property_type' => 'terreno',
        'city' => 'Villa Carlos Paz',
    ]);
    $createListing([
        'transaction_type' => 'alquiler',
        'property_type' => 'casa',
        'city' => 'Villa Carlos Paz',
    ]);
    $createListing([
        'transaction_type' => 'alquiler',
        'state' => 'Buenos Aires',
        'city' => 'Buenos Aires',
    ]);

    $this->get('/es/argentina/casa')
        ->assertSuccessful()
        ->assertSee('/es/argentina/venta/casa', false)
        ->assertSee('/es/argentina/alquiler/casa', false);

    $this->get('/es/argentina/venta/casa')
        ->assertSuccessful()
        ->assertSee('/es/argentina/venta/casa/cordoba', false)
        ->assertDontSee('/es/argentina/venta/casa/buenos-aires', false);

    $this->get('/es/argentina/venta/casa/cordoba')
        ->assertSuccessful()
        ->assertSee('/es/argentina/venta/casa/cordoba/villa-carlos-paz', false)
        ->assertSee('/es/argentina/venta/casa/cordoba/tanti', false);

    $this->get('/es/argentina/cordoba')
        ->assertSuccessful()
        ->assertSee('/es/argentina/casa/cordoba', false)
        ->assertSee('/es/argentina/venta/cordoba', false)
        ->assertSee('/es/argentina/cordoba/villa-carlos-paz', false)
        ->assertDontSee('/es/argentina/casa/buenos-aires', false);

    $this->get('/es/argentina/cordoba/villa-carlos-paz')
        ->assertSuccessful()
        ->assertSee('/es/argentina/casa/cordoba/villa-carlos-paz', false)
        ->assertSee('/es/argentina/terreno/cordoba/villa-carlos-paz', false)
        ->assertSee('/es/argentina/venta/cordoba/villa-carlos-paz', false)
        ->assertSee('/es/argentina/alquiler/cordoba/villa-carlos-paz', false);
});
