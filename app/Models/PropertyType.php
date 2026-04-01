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
        'label_plural',
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
     * Con fallback global si no se encuentra en el país especificado
     */
    public static function getValueEn(string $value, string $countryCode): ?string
    {
        $normalized = strtolower(trim($value));

        // Buscar primero en el país especificado (case-insensitive)
        $valueEn = self::where('country_code', $countryCode)
            ->whereRaw('LOWER(value) = ?', [$normalized])
            ->value('value_en');
        
        // Si no se encuentra, buscar en cualquier país (fallback global)
        if (!$valueEn) {
            $valueEn = self::whereRaw('LOWER(value) = ?', [$normalized])
                ->value('value_en');
        }
        
        return $valueEn;
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

    /** Caché en memoria para evitar N+1 queries al mostrar listas de propiedades */
    private static ?array $allTypesCache = null;

    private static function getAllTypes(): array
    {
        if (self::$allTypesCache === null) {
            self::$allTypesCache = Cache::remember('property_types_all', 3600, function () {
                return self::where('is_active', true)->get()->toArray();
            });
        }
        return self::$allTypesCache;
    }

    /**
     * Obtener la etiqueta de display para un valor dado.
     * Acepta valores con cualquier capitalización (Casa, casa, house).
     * En español retorna el label del DB; en inglés usa value_en como clave de traducción.
     */
    public static function getLabel(string $value, ?string $locale = null): string
    {
        $locale     = $locale ?? app()->getLocale();
        $normalized = strtolower(trim($value));

        $type = collect(self::getAllTypes())->first(function ($t) use ($normalized) {
            return strtolower($t['value']) === $normalized
                || strtolower($t['value_en']) === $normalized;
        });

        if (!$type) {
            return ucfirst(str_replace(['_', '-'], ' ', $value));
        }

        if ($locale === 'en') {
            $key        = 'properties.types.' . $type['value_en'];
            $translated = __($key, [], 'en');
            return $translated !== $key ? $translated : $type['label'];
        }

        return $type['label'];
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
