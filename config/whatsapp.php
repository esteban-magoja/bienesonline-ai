<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business (Meta Cloud API)
    |--------------------------------------------------------------------------
    |
    | Configuración para envío de mensajes via WhatsApp Business Platform.
    | Los templates deben crearse y aprobarse en Meta Business Suite antes de
    | poder usarlos: https://business.facebook.com/
    |
    */

    'enabled' => env('WHATSAPP_ENABLED', false),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),

    'api_version' => env('WHATSAPP_API_VERSION', 'v19.0'),

    'api_url' => 'https://graph.facebook.com',

    /*
    |--------------------------------------------------------------------------
    | Webhook (mensajes entrantes y estados de entrega)
    |--------------------------------------------------------------------------
    |
    | URL a configurar en developers.facebook.com → WhatsApp → Configuración:
    | https://{dominio}/webhook/whatsapp, suscripta al campo "messages".
    | - app_secret: App Secret de la app de Meta (Configuración de la app → Básica),
    |   valida la firma X-Hub-Signature-256 de cada POST.
    | - webhook_verify_token: texto elegido por nosotros, se carga igual en Meta.
    |
    */
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Bandeja de entrada (admin)
    |--------------------------------------------------------------------------
    */
    'inbox' => [
        // Mensajes que, enviados solos, dan de baja los avisos por WhatsApp (sin acentos ni mayúsculas)
        'opt_out_keywords' => ['baja', 'stop', 'unsubscribe', 'darme de baja'],

        // Adjuntos más grandes que esto no se descargan (Meta permite hasta 100 MB en video)
        'max_media_bytes' => (int) env('WHATSAPP_INBOX_MAX_MEDIA_BYTES', 16 * 1024 * 1024),

        // Disco privado donde se guardan los adjuntos recibidos
        'media_disk' => 'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates de mensajes
    |--------------------------------------------------------------------------
    |
    | Nombres de los templates aprobados en Meta Business Suite.
    | Cada template tiene una versión por idioma.
    | Formato del código de idioma según Meta: es_AR, es_MX, en_US, etc.
    |
    */
    'templates' => [
        'welcome' => [
            'es' => [
                'name' => env('WHATSAPP_WELCOME_TEMPLATE_ES', 'bienvenida'),
                'language' => env('WHATSAPP_WELCOME_LANGUAGE_ES', 'es_AR'),
            ],
            'en' => [
                'name' => env('WHATSAPP_WELCOME_TEMPLATE_EN', 'welcome'),
                'language' => env('WHATSAPP_WELCOME_LANGUAGE_EN', 'en_US'),
            ],
        ],
        'verify' => [
            'es' => [
                'name' => env('WHATSAPP_VERIFY_TEMPLATE_ES', 'verify_es'),
                'language' => env('WHATSAPP_VERIFY_LANGUAGE_ES', 'es'),
            ],
            'en' => [
                'name' => env('WHATSAPP_VERIFY_TEMPLATE_EN', 'verify_en'),
                'language' => env('WHATSAPP_VERIFY_LANGUAGE_EN', 'en'),
            ],
        ],

        /*
         * Template enviado a propietarios de anuncios cuando una nueva solicitud
         * de búsqueda coincide con su propiedad publicada.
         */
        'match_ad' => [
            'es' => [
                'name' => env('WHATSAPP_MATCH_AD_TEMPLATE_ES', 'matchanuncio'),
                'language' => env('WHATSAPP_MATCH_AD_LANGUAGE_ES', 'es'),
            ],
            'en' => [
                'name' => env('WHATSAPP_MATCH_AD_TEMPLATE_EN', 'matchad'),
                'language' => env('WHATSAPP_MATCH_AD_LANGUAGE_EN', 'en'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => env('WHATSAPP_LOGGING', true),

];
