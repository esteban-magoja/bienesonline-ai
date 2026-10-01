<?php

namespace App\Services\Import;

use App\Models\PropertyType;
use App\Models\TransactionType;
use Nnjeim\World\Models\Country;

/**
 * Traduce tipos de inmueble y de operación de una fuente externa a los valores
 * regionales que usa la plataforma (property_types / transaction_types).
 */
class ImportTypeResolver
{
    /** Sinónimos frecuentes (normalizados) → value_en. */
    private const PROPERTY_SYNONYMS = [
        'casa' => 'house', 'house' => 'house', 'chalet' => 'house', 'quinta' => 'house', 'villa' => 'house',
        'apartamento' => 'apartment', 'departamento' => 'apartment', 'apartment' => 'apartment',
        'piso' => 'apartment', 'penthouse' => 'apartment', 'apartaestudio' => 'apartment', 'ph' => 'apartment',
        'lote' => 'land', 'terreno' => 'land', 'land' => 'land', 'campo' => 'land',
        'finca' => 'land', 'parcela' => 'land', 'chacra' => 'land',
    ];

    /** Tipo regional preferido cuando varios comparten value_en (ej. campo/terreno → land). */
    private const PREFERRED = ['land' => 'terreno', 'apartment' => 'departamento', 'house' => 'casa'];

    private const TRANSACTION_SYNONYMS = [
        'sale' => 'sale', 'for sale' => 'sale', 'venta' => 'sale', 'vender' => 'sale',
        'rent' => 'rent', 'for rent' => 'rent', 'alquiler' => 'rent', 'arriendo' => 'rent',
        'arrendar' => 'rent', 'renta' => 'rent',
        'temporary rent' => 'temporary_rent', 'alquiler temporal' => 'temporary_rent',
    ];

    public function countryCode(string $countryName): ?string
    {
        return Country::where('name', $countryName)->value('iso2');
    }

    public function propertyType(string $raw, ?string $countryCode): ?string
    {
        $normalized = mb_strtolower(trim($raw));
        if ($normalized === '') {
            return null;
        }

        $types = $countryCode ? PropertyType::getByCountry($countryCode) : collect();

        // 1. Coincidencia directa con un tipo del país (por valor o etiqueta)
        $direct = $types->first(fn ($t) => mb_strtolower($t->value) === $normalized
            || mb_strtolower($t->label) === $normalized);
        if ($direct) {
            return $direct->value;
        }

        // 2. Puente por value_en → tipo regional del país
        $valueEn = self::PROPERTY_SYNONYMS[$normalized] ?? null;
        if ($valueEn) {
            $candidates = $types->where('value_en', $valueEn);
            $regional = $candidates->first(fn ($t) => $t->value === (self::PREFERRED[$valueEn] ?? null))
                ?? $candidates->first();
            if ($regional) {
                return $regional->value;
            }
        }

        // 3. Sin equivalencia regional: se conserva el valor normalizado
        return $normalized;
    }

    public function transactionType(string $raw, ?string $countryCode): ?string
    {
        $normalized = mb_strtolower(trim($raw));
        $valueEn = self::TRANSACTION_SYNONYMS[$normalized] ?? null;

        if ($valueEn === null) {
            return null;
        }

        $types = $countryCode ? TransactionType::getByCountry($countryCode) : collect();
        $regional = $types->first(fn ($t) => $t->value_en === $valueEn);

        return $regional?->value ?? $valueEn;
    }
}
