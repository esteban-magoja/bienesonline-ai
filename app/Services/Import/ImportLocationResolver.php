<?php

namespace App\Services\Import;

use App\Helpers\ChileRegions;
use App\Helpers\PlaceName;
use Illuminate\Support\Facades\Cache;
use Nnjeim\World\Models\State;

/**
 * Normaliza el estado/región de una fuente externa al nombre que usa la plataforma
 * (tabla de estados del país), tolerando acentos, prefijos ("Región de") y, en Chile,
 * provincias informadas en lugar de la región.
 */
class ImportLocationResolver
{
    public function state(?string $raw, string $countryName): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $states = $this->statesByNormalizedName($countryName);

        $match = $this->match($raw, $states);
        if ($match) {
            return $match;
        }

        if (mb_strtolower(trim($countryName)) === 'chile') {
            $region = ChileRegions::fromProvince($this->stripPrefix($raw));

            return ($region ? $this->match($region, $states) : null) ?? $region ?? $raw;
        }

        return $raw;
    }

    /**
     * @param  array<string, string>  $states  nombre normalizado → nombre oficial
     */
    private function match(string $name, array $states): ?string
    {
        return $states[$this->stripPrefix($name)] ?? null;
    }

    private function stripPrefix(string $name): string
    {
        $normalized = PlaceName::normalize($name);

        return preg_replace('/^(region|provincia) (de la |del |de )?/', '', $normalized) ?: $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function statesByNormalizedName(string $countryName): array
    {
        return Cache::remember('import_states_' . md5($countryName), 3600, function () use ($countryName) {
            $map = [];

            State::whereHas('country', fn ($q) => $q->where('name', $countryName))
                ->pluck('name')
                ->each(function (string $name) use (&$map) {
                    $map[$this->stripPrefix($name)] = $name;
                });

            return $map;
        });
    }
}
