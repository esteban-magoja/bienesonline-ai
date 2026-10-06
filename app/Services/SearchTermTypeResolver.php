<?php

namespace App\Services;

use App\Models\PropertyType;
use App\Models\TransactionType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Detecta en un texto de búsqueda el tipo de inmueble y la operación mencionados
 * ("casas en venta" → house + sale) y devuelve los valores equivalentes de todos
 * los países para filtrar anuncios (igual que el matching usa value_en como puente).
 */
class SearchTermTypeResolver
{
    private const CACHE_SECONDS = 3600;

    /**
     * Sinónimos que no están en las tablas de tipos regionales.
     *
     * @var array<string, string>
     */
    private const TRANSACTION_SYNONYMS = [
        'compra' => 'sale',
        'comprar' => 'sale',
        'vendo' => 'sale',
        'vender' => 'sale',
        'alquilar' => 'rent',
        'arrendar' => 'rent',
        'rentar' => 'rent',
    ];

    /**
     * @return array{property_types: list<string>, transaction_types: list<string>}
     */
    public function resolve(string $searchTerm): array
    {
        $text = ' ' . Str::of(Str::ascii($searchTerm))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish() . ' ';

        return [
            'property_types' => $this->matchingValues($text, $this->dictionary(PropertyType::class, [])),
            'transaction_types' => $this->matchingValues($text, $this->dictionary(TransactionType::class, self::TRANSACTION_SYNONYMS)),
        ];
    }

    /**
     * @param  array{terms: array<string, string>, values: array<string, list<string>>}  $dictionary
     * @return list<string>
     */
    private function matchingValues(string $text, array $dictionary): array
    {
        $valuesEn = collect($dictionary['terms'])
            ->filter(fn (string $valueEn, string $term): bool => (bool) preg_match('/ ' . preg_quote($term, '/') . '(s|es)? /', $text))
            ->unique()
            ->values();

        return $valuesEn
            ->flatMap(fn (string $valueEn): array => $dictionary['values'][$valueEn] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Términos detectables (value, label, value_en sin acentos) → value_en, y para
     * cada value_en todos los valores que pueden estar guardados en los anuncios.
     *
     * @param  class-string<PropertyType|TransactionType>  $model
     * @param  array<string, string>  $synonyms
     * @return array{terms: array<string, string>, values: array<string, list<string>>}
     */
    private function dictionary(string $model, array $synonyms): array
    {
        return Cache::remember('search_term_types_' . class_basename($model), self::CACHE_SECONDS, function () use ($model, $synonyms): array {
            $terms = [];
            $values = [];

            foreach ($model::query()->where('is_active', true)->whereNotNull('value_en')->get(['value', 'label', 'value_en']) as $type) {
                $valueEn = mb_strtolower($type->value_en);

                foreach ([$type->value, $type->label, $valueEn] as $term) {
                    $normalized = Str::of(Str::ascii((string) $term))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

                    if ($normalized !== '') {
                        $terms[$normalized] = $valueEn;
                    }
                }

                $values[$valueEn][] = mb_strtolower($type->value);
                $values[$valueEn][] = mb_strtolower($type->label);
                $values[$valueEn][] = $valueEn;
            }

            foreach ($synonyms as $term => $valueEn) {
                if (isset($values[$valueEn])) {
                    $terms[$term] = $valueEn;
                }
            }

            return [
                'terms' => $terms,
                'values' => array_map(fn (array $list): array => array_values(array_unique($list)), $values),
            ];
        });
    }
}
