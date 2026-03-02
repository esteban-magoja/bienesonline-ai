<?php

return [
    'title'             => 'Importar anuncios',
    'subtitle'          => 'Importa tus anuncios desde el sistema anterior',
    'background_notice' => 'El proceso se ejecuta en segundo plano y puede tardar varios minutos.',
    'button'            => 'Importar mis anuncios',
    'select_country'    => 'País de origen',
    'choose_country'    => 'Elegir país',
    'started'           => 'Importación iniciada. Procesando en segundo plano...',
    'already_running'   => 'Ya hay una importación en curso.',
    'not_configured'    => 'El sistema de importación no está configurado.',
    'invalid_country'   => 'País no válido o no configurado.',
    'connection_error'  => 'No se pudo conectar con el sistema anterior.',
    'api_error'         => 'El sistema anterior devolvió un error.',
    'no_listings'       => 'No se encontraron anuncios para importar en tu cuenta.',
    'processing'        => 'Importando anuncios...',
    'completed'         => 'Importación completada',
    'failed'            => 'La importación falló',
    'status'            => [
        'pending'    => 'En cola',
        'processing' => 'Procesando',
        'completed'  => 'Completada',
        'failed'     => 'Fallida',
    ],
    'results' => [
        'imported' => ':count importados',
        'skipped'  => ':count ya existían',
        'failed'   => ':count fallidos',
        'of'       => 'de :total anuncios',
    ],
];
