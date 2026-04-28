<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IndexNow
    |--------------------------------------------------------------------------
    |
    | IndexNow es un protocolo que permite notificar a motores de búsqueda
    | (Bing, Yandex, etc.) sobre contenido nuevo o actualizado de forma
    | inmediata, acelerando la indexación.
    |
    | Generar la API key: cadena hexadecimal de 32 caracteres (ej. UUID sin guiones).
    | Crear el archivo de verificación en public/{api_key}.txt con el mismo valor.
    |
    | Más info: https://www.indexnow.org/documentation
    |
    */

    'enabled' => env('INDEXNOW_ENABLED', false),

    'api_key' => env('INDEXNOW_API_KEY', ''),

    /**
     * Dominio del sitio sin protocolo ni barra final.
     * Ejemplo: bienesonline.com
     */
    'host' => env('INDEXNOW_HOST', ''),

    'endpoint' => 'https://api.indexnow.org/indexnow',

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => env('INDEXNOW_LOGGING', true),

];
