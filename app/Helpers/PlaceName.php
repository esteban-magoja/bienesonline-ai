<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class PlaceName
{
    /**
     * Minúsculas, sin acentos ni espacios repetidos: para comparar nombres de lugares.
     */
    public static function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($name))) ?? '');
    }
}
