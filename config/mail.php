<?php

return [

    'mailers' => [
        'mailgun' => [
            'transport' => 'mailgun',
        ],

        // Correos enviados a mano desde el admin (Comunicaciones → Mensajes). Sale desde una casilla
        // real (contacto@) para recibir las respuestas; las notificaciones automáticas siguen usando
        // el mailer por defecto (no-response@). Para Amazon SES usar su servidor SMTP (sin paquetes).
        'admin' => [
            'transport' => 'smtp',
            'scheme' => env('ADMIN_MAIL_SCHEME'),
            'host' => env('ADMIN_MAIL_HOST', env('MAIL_HOST', '127.0.0.1')),
            'port' => env('ADMIN_MAIL_PORT', 465),
            'username' => env('ADMIN_MAIL_USERNAME'),
            'password' => env('ADMIN_MAIL_PASSWORD'),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
            'from' => [
                'address' => env('ADMIN_MAIL_FROM_ADDRESS', env('ADMIN_MAIL_USERNAME', env('MAIL_FROM_ADDRESS'))),
                'name' => env('ADMIN_MAIL_FROM_NAME', env('APP_NAME', 'bienesonline.ai')),
            ],
            'reply_to' => [
                'address' => env('ADMIN_MAIL_FROM_ADDRESS', env('ADMIN_MAIL_USERNAME', env('MAIL_FROM_ADDRESS'))),
                'name' => env('ADMIN_MAIL_FROM_NAME', env('APP_NAME', 'bienesonline.ai')),
            ],
        ],
    ],

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
