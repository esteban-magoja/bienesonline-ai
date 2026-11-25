<?php

namespace App\Services\WhatsAppInbox;

/**
 * Comparación de números de WhatsApp con los guardados en el sitio.
 *
 * Meta informa el número (wa_id) solo con dígitos y con el formato propio de WhatsApp,
 * que en algunos países difiere del número que carga el usuario:
 * - Argentina: los móviles llevan un 9 después del 54 (549 351...). Muchos usuarios lo cargan sin el 9.
 * - México: números viejos con 1 después del 52 (521 55...), los nuevos sin él.
 */
class WhatsAppPhone
{
    /**
     * Solo los dígitos de un número (quita +, espacios, guiones y el prefijo internacional 00).
     */
    public static function digits(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    /**
     * Formas equivalentes de un número para buscarlo entre los móviles cargados.
     *
     * @return array<int, string>
     */
    public static function variants(?string $phone): array
    {
        $digits = self::digits($phone);

        if ($digits === '') {
            return [];
        }

        $variants = [$digits];

        if (str_starts_with($digits, '549')) {
            $variants[] = '54'.substr($digits, 3);
        } elseif (str_starts_with($digits, '54')) {
            $variants[] = '549'.substr($digits, 2);
        }

        if (str_starts_with($digits, '521') && strlen($digits) === 13) {
            $variants[] = '52'.substr($digits, 3);
        } elseif (str_starts_with($digits, '52') && strlen($digits) === 12) {
            $variants[] = '521'.substr($digits, 2);
        }

        return array_values(array_unique($variants));
    }
}
