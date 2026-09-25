<?php

use App\Helpers\SeoLocaleHelper;
use App\Http\Controllers\PropertyListingController;
use App\Models\PropertyType;
use App\Models\TransactionType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\State;

uses(DatabaseTransactions::class);

it('uses localized type and transaction labels in contextual listing metadata', function () {
    DB::table('transaction_types')->insert([
        'country_code' => 'CL',
        'value' => 'venta',
        'label' => 'Venta',
        'value_en' => 'sale',
        'order' => 1,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $cachedTypes = new ReflectionProperty(TransactionType::class, 'allTypesCache');
    $cachedTypes->setAccessible(true);
    $cachedTypes->setValue(null, null);
    Cache::forget('transaction_types_all');

    $propertyType = new PropertyType;
    $propertyType->value = 'departamento';
    $propertyType->value_en = 'apartment';
    $propertyType->label = 'Departamento';
    $propertyType->label_plural = 'Departamentos';

    $transactionType = new TransactionType;
    $transactionType->value = 'venta';
    $transactionType->value_en = 'sale';
    $transactionType->label = 'Venta';

    $state = new State;
    $state->name = 'Metropolitana de Santiago';

    $city = new City;
    $city->name = 'Las Condes';

    $controller = new PropertyListingController;
    $getListingHubContent = new ReflectionMethod(PropertyListingController::class, 'getListingHubContent');
    $generateSeoMetadata = new ReflectionMethod(PropertyListingController::class, 'generateSeoMetadata');

    $englishContext = $getListingHubContent->invoke(
        $controller,
        'Chile',
        'en',
        $transactionType,
        $propertyType,
        $state,
        $city
    );
    $spanishContext = $getListingHubContent->invoke(
        $controller,
        'Chile',
        'es',
        $transactionType,
        $propertyType,
        $state,
        $city
    );

    $englishSeo = $generateSeoMetadata->invoke(
        $controller,
        'Chile',
        'CL',
        $transactionType,
        $propertyType,
        $state,
        $city,
        8,
        'en',
        $englishContext['description']
    );

    $spanishSeo = $generateSeoMetadata->invoke(
        $controller,
        'Chile',
        'CL',
        $transactionType,
        $propertyType,
        $state,
        $city,
        8,
        'es',
        $spanishContext['description']
    );

    $landType = new PropertyType;
    $landType->value = 'terreno';
    $landType->value_en = 'land';
    $landType->label = 'Terreno';
    $landType->label_plural = 'Terrenos';

    $landSeo = $generateSeoMetadata->invoke(
        $controller,
        'Chile',
        'CL',
        null,
        $landType,
        $state,
        $city,
        2,
        'en',
        'Explore land in Las Condes, Metropolitana de Santiago, Chile.'
    );

    expect($englishSeo['title'])->toBe('Apartments for sale in Las Condes')
        ->and($englishSeo['html_lang'])->toBe('en-CL')
        ->and($englishSeo['og_locale'])->toBe('en_CL')
        ->and(collect($englishSeo['hreflang_tags'])->pluck('hreflang')->all())
        ->toBe(['es-CL', 'en-CL', 'x-default'])
        ->and($englishSeo['description'])
        ->toContain('Explore apartments for sale in Las Condes, Metropolitana de Santiago, Chile.')
        ->toContain('There are 8 listings available.')
        ->and($spanishSeo['title'])->toBe('Departamentos en venta en Las Condes')
        ->and($spanishSeo['html_lang'])->toBe('es-CL')
        ->and($spanishSeo['og_locale'])->toBe('es_CL')
        ->and($spanishSeo['og_alternate_locales'])->toBe(['en_CL'])
        ->and($spanishContext['title'])
        ->toBe('Anuncios de venta de departamentos en Las Condes, Metropolitana de Santiago, Chile')
        ->not->toContain('Explorar')
        ->and($spanishSeo['description'])
        ->toContain('Explora anuncios de departamentos en venta disponibles en Las Condes, Metropolitana de Santiago, Chile.')
        ->toContain('Hay 8 anuncios disponibles.')
        ->and($landSeo['title'])->toBe('Land in Las Condes');
});

it('keeps generic locale tags when a page has no country target', function () {
    expect(SeoLocaleHelper::getLanguageTag('es'))->toBe('es')
        ->and(SeoLocaleHelper::getLanguageTag('en', 'INTL'))->toBe('en')
        ->and(SeoLocaleHelper::getOpenGraphLocale('es'))->toBe('es_ES')
        ->and(SeoLocaleHelper::getOpenGraphLocale('en', 'CL'))->toBe('en_CL');
});

it('uses a country-level description and safely limits SEO descriptions', function () {
    $generateSeoMetadata = new ReflectionMethod(PropertyListingController::class, 'generateSeoMetadata');
    $controller = new PropertyListingController;

    $countrySeo = $generateSeoMetadata->invoke(
        $controller,
        'Chile',
        'CL',
        null,
        null,
        null,
        null,
        12,
        'en',
        ''
    );

    $emptyCountrySeo = $generateSeoMetadata->invoke(
        $controller,
        'Chile',
        'CL',
        null,
        null,
        null,
        null,
        0,
        'es',
        ''
    );

    $propertyType = new PropertyType;
    $propertyType->value = 'terreno';
    $propertyType->value_en = 'land';
    $propertyType->label = 'Terreno';
    $propertyType->label_plural = 'Terrenos';

    $state = new State;
    $state->name = 'Ñuñoa';

    $longSeo = $generateSeoMetadata->invoke(
        $controller,
        'Chile',
        'CL',
        null,
        $propertyType,
        $state,
        null,
        12,
        'es',
        str_repeat('Propiedades disponibles en Ñuñoa. ', 10)
    );

    expect($countrySeo['description'])
        ->toContain('Explore properties in Chile.')
        ->toContain('There are 12 listings available.')
        ->and($emptyCountrySeo['description'])
        ->toContain('No hay anuncios disponibles actualmente.')
        ->and(mb_strlen($longSeo['description']))
        ->toBeLessThanOrEqual(160)
        ->and($longSeo['description'])
        ->toContain('Hay 12 anuncios disponibles.')
        ->and(mb_check_encoding($longSeo['description'], 'UTF-8'))
        ->toBeTrue();
});
