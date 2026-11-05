<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correos enviados desde el admin (Comunicaciones → Mensajes)
    |--------------------------------------------------------------------------
    |
    | mailer: mailer de config/mail.php que se usa para enviar. En producción 'admin' (casilla
    | contacto@). En desarrollo conviene 'log' para que no salga ningún correo real.
    |
    | per_minute: correos por minuto. El límite por hora del hosting es desconocido, así que el
    | valor por defecto es conservador (5/min = 300/h).
    |
    */

    'mailer' => env('ADMIN_EMAIL_MAILER', 'admin'),

    'per_minute' => max(1, (int) env('ADMIN_EMAIL_PER_MINUTE', 5)),

];
