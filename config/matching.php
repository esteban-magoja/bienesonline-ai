<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Matching Enabled
    |--------------------------------------------------------------------------
    |
    | Determina si el sistema de matching automático está habilitado.
    | Cuando está activado, se envían notificaciones automáticas a los
    | solicitantes cuando se publica un anuncio que coincide con sus búsquedas.
    |
    */
    'enabled' => env('AUTO_MATCHING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Minimum Score to Notify
    |--------------------------------------------------------------------------
    |
    | Score mínimo (0-100) que debe tener un match para enviar notificación.
    | Recomendado: 70 (solo matches de buena calidad).
    | 
    | Niveles:
    | - 85-100: Exacto (todos los filtros coinciden)
    | - 60-84: Inteligente (similitud semántica por IA)
    | - 0-59: Flexible (coincidencias parciales)
    |
    */
    'min_score_to_notify' => env('MATCHING_MIN_SCORE', 70),

    /*
    |--------------------------------------------------------------------------
    | Maximum Matches Per Listing
    |--------------------------------------------------------------------------
    |
    | Número máximo de matches a buscar por cada anuncio publicado.
    | Limita la cantidad de solicitudes que se evalúan para evitar
    | procesamiento excesivo.
    |
    */
    'max_matches_per_listing' => env('MATCHING_MAX_MATCHES', 20),

    /*
    |--------------------------------------------------------------------------
    | Notification Delay
    |--------------------------------------------------------------------------
    |
    | Tiempo en minutos de retraso antes de enviar notificaciones.
    | Útil para agrupar notificaciones o dar tiempo a editar el anuncio.
    | 0 = enviar inmediatamente.
    |
    */
    'notification_delay_minutes' => env('MATCHING_NOTIFICATION_DELAY', 0),

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Canales por los cuales se envían las notificaciones de matches.
    | Opciones: 'mail', 'database', 'sms', 'whatsapp', 'slack'
    |
    */
    'channels' => [
        'mail',      // Email
        'database',  // Notificación en dashboard (bell icon)
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Límite de notificaciones por usuario por período de tiempo.
    | Evita spam de emails cuando hay muchos anuncios nuevos.
    | null = sin límite
    |
    */
    'rate_limit' => [
        'max_per_day' => env('MATCHING_MAX_NOTIFICATIONS_PER_DAY', null),
        'max_per_hour' => env('MATCHING_MAX_NOTIFICATIONS_PER_HOUR', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuración de logs para debugging del sistema de matching.
    |
    */
    'logging' => [
        'enabled' => env('MATCHING_LOGGING_ENABLED', true),
        'level' => env('MATCHING_LOG_LEVEL', 'info'), // debug, info, warning, error
    ],
];
