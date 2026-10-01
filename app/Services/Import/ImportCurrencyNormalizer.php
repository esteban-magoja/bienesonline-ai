<?php

namespace App\Services\Import;

/**
 * Lleva las monedas de una fuente externa a códigos que usa la plataforma.
 */
class ImportCurrencyNormalizer
{
    private const ALIASES = [
        'uf' => 'CLF', 'ufs' => 'CLF', 'unidad de fomento' => 'CLF',
        'u$d' => 'USD', 'u$s' => 'USD', 'us$' => 'USD', 'dolares' => 'USD', 'dólares' => 'USD', 'dolar' => 'USD', 'dólar' => 'USD',
        'euros' => 'EUR', 'euro' => 'EUR', '€' => 'EUR',
    ];

    /**
     * @param  string  $default  moneda local del país, para valores vacíos o ambiguos ("$", "pesos")
     */
    public function normalize(?string $raw, string $default): string
    {
        $value = mb_strtolower(trim((string) $raw));

        if ($value === '' || in_array($value, ['$', 'peso', 'pesos'], true)) {
            return $default;
        }

        if (isset(self::ALIASES[$value])) {
            return self::ALIASES[$value];
        }

        return preg_match('/^[a-z]{3}$/', $value) ? strtoupper($value) : $default;
    }
}
