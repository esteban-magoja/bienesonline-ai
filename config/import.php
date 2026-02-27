<?php

return [
    /*
     * Mapa de País → URL base del proyecto legacy.
     * La clave es el nombre del país tal como aparece en el selector.
     * El valor se define en el .env como LEGACY_URL_{CODIGO}.
     *
     * Ejemplo .env:
     *   LEGACY_URL_AR=https://argentina.bienesonline.com
     *   LEGACY_URL_MX=https://mexico.bienesonline.com
     */
    'legacy_urls' => array_filter([
        'Argentina' => env('LEGACY_URL_AR'),
        'México'    => env('LEGACY_URL_MX'),
        'Chile'     => env('LEGACY_URL_CL'),
        'España'    => env('LEGACY_URL_ES'),
        'Colombia'  => env('LEGACY_URL_CO'),
        'Uruguay'   => env('LEGACY_URL_UY'),
        'Paraguay'  => env('LEGACY_URL_PY'),
        'Bolivia'   => env('LEGACY_URL_BO'),
        'Perú'      => env('LEGACY_URL_PE'),
        'Venezuela' => env('LEGACY_URL_VE'),
        'Ecuador'   => env('LEGACY_URL_EC'),
    ]),

    /*
     * Timeout en segundos para la llamada al API del proyecto legacy.
     */
    'api_timeout' => env('IMPORT_API_TIMEOUT', 30),

    /*
     * Timeout en segundos para la descarga de cada imagen.
     */
    'image_timeout' => env('IMPORT_IMAGE_TIMEOUT', 60),

    /*
     * Tamaño máximo de imagen en bytes (10MB por defecto).
     */
    'max_image_size' => env('IMPORT_MAX_IMAGE_SIZE', 10 * 1024 * 1024),

    /*
     * Identificador de la fuente para el campo `source` en property_listings.
     */
    'source_name' => env('IMPORT_SOURCE_NAME', 'legacy'),
];
