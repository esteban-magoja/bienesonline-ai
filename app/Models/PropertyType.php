<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PropertyType extends Model
{
    protected $fillable = [
        'country_code',
        'value',
        'label',
        'value_en',
        'order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Obtener tipos de propiedad por código de país
     * Usa fallback a INTL si no hay datos para el país
     */
    public static function getByCountry(string $countryCode): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember("property_types_{$countryCode}", 3600, function () use ($countryCode) {
            $types = self::where('country_code', $countryCode)
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('label')
                ->get();
            
            // Si no hay tipos para este país, usar INTL
            if ($types->isEmpty()) {
                \Log::info("País sin property_types definidos: {$countryCode}. Usando INTL.");
                $types = self::where('country_code', 'INTL')
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->orderBy('label')
                    ->get();
            }
            
            return $types;
        });
    }

    /**
     * Obtener el valor en inglés de un tipo de propiedad
     */
    public static function getValueEn(string $value, string $countryCode): ?string
    {
        return self::where('country_code', $countryCode)
            ->where('value', $value)
            ->value('value_en');
    }

    /**
     * Obtener valores equivalentes en diferentes países
     * Ejemplo: "departamento" (AR), "apartamento" (CO), "piso" (ES) → todos son "apartment"
     */
    public static function getEquivalentValues(string $value, string $countryCode): array
    {
        // Primero obtenemos el value_en del tipo dado
        $valueEn = self::getValueEn($value, $countryCode);
        
        if (!$valueEn) {
            return [$value]; // Si no encuentra, retornar el valor original
        }
        
        // Buscar todos los valores que tengan el mismo value_en
        return self::where('value_en', $valueEn)
            ->where('is_active', true)
            ->pluck('value')
            ->unique()
            ->toArray();
    }

    /**
     * Limpiar cache de tipos
     */
    public static function clearCache(?string $countryCode = null): void
    {
        if ($countryCode) {
            Cache::forget("property_types_{$countryCode}");
        } else {
            // Limpiar todos
            $codes = self::distinct('country_code')->pluck('country_code');
            foreach ($codes as $code) {
                Cache::forget("property_types_{$code}");
            }
        }
    }
}
