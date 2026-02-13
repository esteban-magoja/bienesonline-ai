<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TransactionType extends Model
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
     * Obtener tipos de transacción por código de país
     * Usa fallback a INTL si no hay datos para el país
     */
    public static function getByCountry(string $countryCode): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember("transaction_types_{$countryCode}", 3600, function () use ($countryCode) {
            $types = self::where('country_code', $countryCode)
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('label')
                ->get();
            
            // Si no hay tipos para este país, usar INTL
            if ($types->isEmpty()) {
                \Log::info("País sin transaction_types definidos: {$countryCode}. Usando INTL.");
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
     * Obtener el valor en inglés de un tipo de transacción
     * Con fallback global si no se encuentra en el país especificado
     */
    public static function getValueEn(string $value, string $countryCode): ?string
    {
        // Buscar primero en el país especificado
        $valueEn = self::where('country_code', $countryCode)
            ->where('value', $value)
            ->value('value_en');
        
        // Si no se encuentra, buscar en cualquier país (fallback global)
        if (!$valueEn) {
            $valueEn = self::where('value', $value)
                ->value('value_en');
        }
        
        return $valueEn;
    }

    /**
     * Obtener valores equivalentes en diferentes países
     */
    public static function getEquivalentValues(string $value, string $countryCode): array
    {
        $valueEn = self::getValueEn($value, $countryCode);
        
        if (!$valueEn) {
            return [$value];
        }
        
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
            Cache::forget("transaction_types_{$countryCode}");
        } else {
            $codes = self::distinct('country_code')->pluck('country_code');
            foreach ($codes as $code) {
                Cache::forget("transaction_types_{$code}");
            }
        }
    }
}
