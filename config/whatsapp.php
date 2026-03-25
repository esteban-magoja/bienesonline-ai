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
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => env('WHATSAPP_LOGGING', true),

];
