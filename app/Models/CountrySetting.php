<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Nnjeim\World\Models\Country;

class CountrySetting extends Model
{
    protected $primaryKey = 'iso2';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['iso2', 'is_enabled', 'display_order'];

    protected $casts = [
        'is_enabled'    => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Retorna los países habilitados, ordenados por display_order → nombre.
     * Usado en todos los formularios públicos y del dashboard.
     */
    public static function getEnabledCountries(): \Illuminate\Database\Eloquent\Collection
    {
        $enabledCodes = Cache::remember('enabled_country_iso2_codes', 3600, function () {
            return self::where('is_enabled', true)
                ->orderBy('display_order')
                ->pluck('iso2')
                ->toArray();
        });

        if (empty($enabledCodes)) {
            return collect();
        }

        // Mantener el orden de display_order usando orderByRaw
        $orderCase = 'CASE iso2 ' .
            implode(' ', array_map(
                fn($iso2, $i) => "WHEN '{$iso2}' THEN {$i}",
                $enabledCodes,
                array_keys($enabledCodes)
            )) . ' ELSE 999 END';

        return Country::whereIn('iso2', $enabledCodes)
            ->orderByRaw($orderCase)
            ->get();
    }

    /**
     * Limpia el cache de países habilitados.
     */
    public static function clearCache(): void
    {
        Cache::forget('enabled_country_iso2_codes');
    }

    /**
     * Habilita un país. Si no existe el registro, lo crea.
     */
    public static function enable(string $iso2, int $order = 999): void
    {
        self::updateOrCreate(
            ['iso2' => $iso2],
            ['is_enabled' => true, 'display_order' => $order]
        );
        self::clearCache();
    }

    /**
     * Deshabilita un país.
     */
    public static function disable(string $iso2): void
    {
        self::where('iso2', $iso2)->update(['is_enabled' => false]);
        self::clearCache();
    }
}
