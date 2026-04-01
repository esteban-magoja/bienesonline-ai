<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;
use App\Models\CountrySetting;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;
use Nnjeim\World\Models\City;

class PropertySlugHelper
{
    /**
     * Normaliza un texto a slug (minúsculas, sin espacios, sin acentos)
     */
    public static function normalize(string $text): string
    {
        return Str::slug($text, '-');
    }

    /**
     * Obtiene el código ISO2 de un país por su nombre.
     */
    public static function getCountryCode(string $countryName): ?string
    {
        return Cache::remember('country_code_' . self::normalize($countryName), 3600, function () use ($countryName) {
            return Country::where('name', $countryName)->value('iso2');
        });
    }

    /**
     * Busca un tipo de operación por slug para un país dado.
     * El slug coincide con Str::slug($transactionType->value).
     * Usa fallback a INTL si no hay tipos para el país.
     */
    public static function getOperationBySlug(string $slug, string $countryCode): ?TransactionType
    {
        $types = TransactionType::getByCountry($countryCode);
        return $types->first(fn($t) => self::normalize($t->value) === $slug) ?? null;
    }

    /**
     * Busca un tipo de propiedad por slug para un país dado.
     * El slug coincide con Str::slug($propertyType->value).
     * Usa fallback a INTL si no hay tipos para el país.
     */
    public static function getPropertyTypeBySlug(string $slug, string $countryCode): ?PropertyType
    {
        $types = PropertyType::getByCountry($countryCode);
        return $types->first(fn($t) => self::normalize($t->value) === $slug) ?? null;
    }

    /**
     * Busca un estado/provincia por slug para un país dado.
     * Acepta diferencias de acentos y mayúsculas (Córdoba = cordoba = CÓRDOBA).
     * Solo valida contra la lista oficial de estados (nnjeim/world).
     */
    public static function getStateBySlug(string $slug, string $countryCode): ?State
    {
        $states = Cache::remember("states_{$countryCode}", 3600, function () use ($countryCode) {
            return State::where('country_code', $countryCode)->get();
        });

        return $states->first(fn($s) => self::normalize($s->name) === $slug) ?? null;
    }

    /**
     * Busca una ciudad por slug dentro de un estado.
     * Acepta diferencias de acentos y mayúsculas.
     * Solo valida contra la lista oficial de ciudades del estado (nnjeim/world).
     */
    public static function getCityBySlug(string $slug, int $stateId): ?City
    {
        $cities = Cache::remember("cities_{$stateId}", 3600, function () use ($stateId) {
            return City::where('state_id', $stateId)->get();
        });

        return $cities->first(fn($c) => self::normalize($c->name) === $slug) ?? null;
    }

    /**
     * Valida si un slug corresponde a un país habilitado en CountrySetting.
     * Retorna el nombre oficial del país o null si no es válido.
     */
    public static function validateCountry(string $slug): ?string
    {
        $countries = CountrySetting::getEnabledCountries();

        foreach ($countries as $country) {
            if (self::normalize($country->name) === $slug) {
                return $country->name;
            }
        }

        return null;
    }

    /**
     * Obtiene todos los países disponibles (DISTINCT de anuncios activos)
     */
    public static function getAvailableCountries(): array
    {
        return PropertyListing::query()
            ->where('is_active', true)
            ->whereNotNull('country')
            ->whereRaw("TRIM(country) != ''")
            ->selectRaw('DISTINCT TRIM(country) as country')
            ->orderBy('country')
            ->pluck('country')
            ->toArray();
    }

    /**
     * Obtiene todos los estados disponibles para un país (desde anuncios activos)
     */
    public static function getAvailableStates(string $country): array
    {
        return PropertyListing::where('is_active', true)
            ->where('country', $country)
            ->whereNotNull('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->toArray();
    }

    /**
     * Obtiene todas las ciudades disponibles para un país/estado (desde anuncios activos)
     */
    public static function getAvailableCities(string $country, ?string $state = null): array
    {
        $query = PropertyListing::where('is_active', true)
            ->where('country', $country)
            ->whereNotNull('city');

        if ($state) {
            $query->whereRaw('lower(unaccent(state)) = lower(unaccent(?))', [$state]);
        }

        return $query->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->toArray();
    }

    /**
     * Genera breadcrumbs dinámicos usando los tipos configurados del país.
     *
     * @param string          $locale
     * @param string          $country          Nombre del país (ej: "Argentina")
     * @param string          $countryCode      ISO2 (ej: "AR")
     * @param TransactionType|null $transactionType  Objeto del tipo de operación
     * @param PropertyType|null    $propertyType     Objeto del tipo de propiedad
     * @param State|null           $state            Objeto del estado (nnjeim/world)
     * @param City|null            $city             Objeto de la ciudad (nnjeim/world)
     */
    public static function generateBreadcrumbs(
        string $locale,
        string $country,
        string $countryCode,
        ?TransactionType $transactionType = null,
        ?PropertyType $propertyType = null,
        ?State $state = null,
        ?City $city = null
    ): array
    {
        $breadcrumbs = [
            ['label' => __('messages.home'), 'url' => route('home', ['locale' => $locale])],
        ];

        $url = "/{$locale}/" . self::normalize($country);
        $breadcrumbs[] = ['label' => $country, 'url' => $url];

        if ($transactionType) {
            $url .= '/' . self::normalize($transactionType->value);
            $breadcrumbs[] = ['label' => $transactionType->label, 'url' => $url];
        }

        if ($propertyType) {
            $url .= '/' . self::normalize($propertyType->value);
            $label = $propertyType->label_plural ?: $propertyType->label;
            $breadcrumbs[] = ['label' => $label, 'url' => $url];
        }

        if ($state) {
            $url .= '/' . self::normalize($state->name);
            $breadcrumbs[] = ['label' => $state->name, 'url' => $url];
        }

        if ($city) {
            $url .= '/' . self::normalize($city->name);
            $breadcrumbs[] = ['label' => $city->name, 'url' => null];
        }

        return $breadcrumbs;
    }
}
