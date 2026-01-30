<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | Lista de idiomas soportados por la aplicación.
    | Formato: código ISO 639-1 (2 letras)
    |
    */
    'available' => ['es', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | Idioma por defecto de la aplicación.
    | Se usa cuando no se puede detectar el idioma del usuario.
    |
    */
    'default' => 'es',

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | Idioma de respaldo cuando una traducción no está disponible.
    |
    */
    'fallback' => 'es',

    /*
    |--------------------------------------------------------------------------
    | Locale Names
    |--------------------------------------------------------------------------
    |
    | Nombres de los idiomas para mostrar en la UI.
    |
    */
    'names' => [
        'es' => 'Español',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Flags
    |--------------------------------------------------------------------------
    |
    | Emojis de banderas para cada idioma.
    |
    */
    'flags' => [
        'es' => '🇪🇸',
        'en' => '🇬🇧',
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Locale Mapping
    |--------------------------------------------------------------------------
    |
    | Mapeo de códigos de idioma a nombres para URLs amigables.
    | Ejemplo: /es/propiedad vs /en/property
    |
    */
    'url_names' => [
        'es' => [
            'property' => 'propiedad',
            'properties' => 'propiedades',
            'search' => 'buscar',
            'request' => 'solicitud',
            'requests' => 'solicitudes',
            'dashboard' => 'panel',
            'matches' => 'coincidencias',
            'messages' => 'mensajes',
        ],
        'en' => [
            'property' => 'property',
            'properties' => 'properties',
            'search' => 'search',
            'request' => 'request',
            'requests' => 'requests',
            'dashboard' => 'dashboard',
            'matches' => 'matches',
            'messages' => 'messages',
        ],
    ],
];
