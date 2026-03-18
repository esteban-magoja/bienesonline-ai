<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\TransactionType;

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
     * Valida si un slug existe como país en la BD
     */
    public static function validateCountry(string $slug): ?string
    {
        // Obtener todos los países y buscar por slug normalizado
        $countries = PropertyListing::where('is_active', true)
            ->distinct()
            ->pluck('country');
        
        foreach ($countries as $country) {
            if (self::normalize($country) === $slug) {
                return $country;
            }
        }
        
        return null;
    }

    /**
     * Valida si un slug existe como estado en la BD (dentro de un país)
     */
    public static function validateState(string $slug, string $country): ?string
    {
        // Obtener todos los estados y buscar por slug normalizado
        $states = PropertyListing::where('is_active', true)
            ->where('country', $country)
            ->whereNotNull('state')
            ->distinct()
            ->pluck('state');
        
        foreach ($states as $state) {
            if (self::normalize($state) === $slug) {
                return $state;
            }
        }
        
        return null;
    }

    /**
     * Valida si un slug existe como ciudad en la BD (dentro de estado/país)
     */
    public static function validateCity(string $slug, string $country, ?string $state = null): ?string
    {
        $query = PropertyListing::where('is_active', true)
            ->where('country', $country)
            ->whereNotNull('city');

        if ($state) {
            $query->where('state', $state);
        }

        $cities = $query->distinct()->pluck('city');
        
        foreach ($cities as $city) {
            if (self::normalize($city) === $slug) {
                return $city;
            }
        }
        
        return null;
    }

    /**
     * Valida si un slug existe como tipo de transacción en la BD
     * Mapea slugs traducidos (venta, alquiler) a value_en (sale, rent)
     * Verifica usando todos los valores regionales equivalentes, case-insensitive
     */
    public static function validateTransactionType(string $slug, string $country): ?string
    {
        $transactionMap = [
            'venta'             => 'sale',
            'sale'              => 'sale',
            'alquiler'          => 'rent',
            'rent'              => 'rent',
            'alquiler-temporal' => 'temporary_rent',
            'temporary-rent'    => 'temporary_rent',
        ];

        $valueEn = $transactionMap[$slug] ?? null;
        if (!$valueEn) {
            return null;
        }

        // Obtener todos los valores regionales con ese value_en (venta, arriendo, renta…)
        $equivalents = TransactionType::where('value_en', $valueEn)
            ->where('is_active', true)
            ->pluck('value')
            ->map(fn($v) => strtolower($v))
            ->unique()
            ->toArray();

        if (empty($equivalents)) {
            $equivalents = [strtolower($valueEn)];
        }

        $exists = PropertyListing::where('is_active', true)
            ->where('country', $country)
            ->where(function ($q) use ($equivalents) {
                foreach ($equivalents as $val) {
                    $q->orWhereRaw('LOWER(transaction_type) = ?', [$val]);
                }
            })
            ->exists();

        return $exists ? $valueEn : null;
    }

    /**
     * Valida si un slug existe como tipo de propiedad en la BD
     * Mapea slugs traducidos (casas, departamentos) a value_en (house, apartment)
     * Verifica usando todos los valores regionales equivalentes, case-insensitive
     */
    public static function validatePropertyType(string $slug, string $country): ?string
    {
        $propertyMap = [
            'casa' => 'house', 'casas' => 'house', 'house' => 'house', 'houses' => 'house',
            'departamento' => 'apartment', 'departamentos' => 'apartment',
            'apartment' => 'apartment', 'apartments' => 'apartment',
            'piso' => 'apartment', 'pisos' => 'apartment',
            'apartamento' => 'apartment', 'apartamentos' => 'apartment',
            'oficina' => 'office', 'oficinas' => 'office', 'office' => 'office', 'offices' => 'office',
            'local' => 'commercial', 'locales' => 'commercial', 'commercial' => 'commercial',
            'terreno' => 'land', 'terrenos' => 'land', 'land' => 'land', 'lands' => 'land',
            'lote' => 'land', 'lotes' => 'land',
            'campo' => 'field', 'campos' => 'field', 'field' => 'field', 'fields' => 'field',
            'finca' => 'farm', 'fincas' => 'farm', 'farm' => 'farm', 'farms' => 'farm',
            'rancho' => 'farm', 'parcela' => 'farm',
            'galpon' => 'warehouse', 'galpones' => 'warehouse',
            'bodega' => 'warehouse', 'bodegas' => 'warehouse', 'nave' => 'warehouse',
            'warehouse' => 'warehouse', 'warehouses' => 'warehouse',
            'cochera' => 'parking', 'cocheras' => 'parking',
            'garaje' => 'parking', 'garajes' => 'parking',
            'estacionamiento' => 'parking', 'parqueadero' => 'parking', 'parking' => 'parking',
            'ph' => 'townhouse', 'townhouse' => 'townhouse', 'townhouses' => 'townhouse',
            'condominio' => 'condo', 'condominios' => 'condo', 'condo' => 'condo',
            'chalet' => 'villa', 'chalets' => 'villa', 'villa' => 'villa',
            'atico' => 'penthouse', 'aticos' => 'penthouse', 'penthouse' => 'penthouse',
        ];

        $valueEn = $propertyMap[$slug] ?? null;
        if (!$valueEn) {
            return null;
        }

        // Obtener todos los valores regionales con ese value_en (casa, departamento, piso…)
        $equivalents = PropertyType::where('value_en', $valueEn)
            ->where('is_active', true)
            ->pluck('value')
            ->map(fn($v) => strtolower($v))
            ->unique()
            ->toArray();

        if (empty($equivalents)) {
            $equivalents = [strtolower($valueEn)];
        }

        $exists = PropertyListing::where('is_active', true)
            ->where('country', $country)
            ->where(function ($q) use ($equivalents) {
                foreach ($equivalents as $val) {
                    $q->orWhereRaw('LOWER(property_type) = ?', [$val]);
                }
            })
            ->exists();

        return $exists ? $valueEn : null;
    }

    /**
     * Obtiene todos los países disponibles (DISTINCT de la BD)
     */
    public static function getAvailableCountries(): array
    {
        return PropertyListing::where('is_active', true)
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->toArray();
    }

    /**
     * Obtiene todos los estados disponibles para un país
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
     * Obtiene todas las ciudades disponibles para un país/estado
     */
    public static function getAvailableCities(string $country, ?string $state = null): array
    {
        $query = PropertyListing::where('is_active', true)
            ->where('country', $country)
            ->whereNotNull('city');

        if ($state) {
            $query->where('state', $state);
        }

        return $query->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->toArray();
    }

    /**
     * Detecta qué tipo de parámetro es un slug en cascada
     * Retorna: ['type' => 'transaction|property|state|city|unknown', 'value' => 'valor_real_bd']
     */
    public static function detectSlugType(
        string $slug, 
        string $country, 
        ?string $previousContext = null
    ): array
    {
        // Intentar como transaction_type
        if ($value = self::validateTransactionType($slug, $country)) {
            return ['type' => 'transaction', 'value' => $value];
        }

        // Intentar como property_type
        if ($value = self::validatePropertyType($slug, $country)) {
            return ['type' => 'property', 'value' => $value];
        }

        // Intentar como state
        if ($value = self::validateState($slug, $country)) {
            return ['type' => 'state', 'value' => $value];
        }

        // Si hay contexto previo (state), intentar como ciudad
        if ($previousContext) {
            if ($value = self::validateCity($slug, $country, $previousContext)) {
                return ['type' => 'city', 'value' => $value];
            }
        }

        return ['type' => 'unknown', 'value' => null];
    }

    /**
     * Genera breadcrumbs dinámicos basados en los parámetros
     */
    public static function generateBreadcrumbs(
        string $locale,
        string $country,
        ?string $transaction = null,
        ?string $propertyType = null,
        ?string $state = null,
        ?string $city = null
    ): array
    {
        $breadcrumbs = [
            ['label' => __('messages.home'), 'url' => route('home', ['locale' => $locale])],
        ];

        $url = "/{$locale}/" . self::normalize($country);
        $breadcrumbs[] = ['label' => $country, 'url' => $url];

        if ($transaction) {
            // Mapear transaction_type a slug traducido
            $transactionSlugs = [
                'sale' => ['es' => 'venta', 'en' => 'sale'],
                'rent' => ['es' => 'alquiler', 'en' => 'rent'],
                'temporary_rent' => ['es' => 'alquiler-temporal', 'en' => 'temporary-rent'],
            ];
            $transactionSlug = $transactionSlugs[$transaction][$locale] ?? self::normalize($transaction);
            $url .= '/' . $transactionSlug;
            $breadcrumbs[] = ['label' => __("properties.transaction_types.{$transaction}"), 'url' => $url];
        }

        if ($propertyType) {
            // Mapear property_type a slug traducido
            $propertySlugs = [
                'house' => ['es' => 'casas', 'en' => 'houses'],
                'apartment' => ['es' => 'departamentos', 'en' => 'apartments'],
                'office' => ['es' => 'oficinas', 'en' => 'offices'],
                'commercial' => ['es' => 'locales', 'en' => 'commercials'],
                'land' => ['es' => 'terrenos', 'en' => 'lands'],
                'field' => ['es' => 'campos', 'en' => 'fields'],
                'farm' => ['es' => 'fincas', 'en' => 'farms'],
                'warehouse' => ['es' => 'galpones', 'en' => 'warehouses'],
            ];
            $propertySlug = $propertySlugs[$propertyType][$locale] ?? self::normalize($propertyType);
            $url .= '/' . $propertySlug;
            $breadcrumbs[] = ['label' => __("properties.types.{$propertyType}"), 'url' => $url];
        }

        if ($state) {
            $url .= '/' . self::normalize($state);
            $breadcrumbs[] = ['label' => $state, 'url' => $url];
        }

        if ($city) {
            $url .= '/' . self::normalize($city);
            $breadcrumbs[] = ['label' => $city, 'url' => null];
        }

        return $breadcrumbs;
    }
}
