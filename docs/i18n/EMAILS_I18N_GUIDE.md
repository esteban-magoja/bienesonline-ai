# Emails y Notificaciones Multiidioma - Guía de Uso

## Resumen

El sistema de emails y notificaciones ahora soporta múltiples idiomas (español/inglés) basado en la preferencia del usuario.

## Configuración del Usuario

### Campo `locale` en la tabla `users`
- **Valores permitidos**: `'es'` o `'en'`
- **Default**: `'es'`
- **Detectado automáticamente** en el registro desde el header `Accept-Language` del navegador
- **Editable** por el usuario en `/settings/profile`

### Detección Automática en Registro

```php
// resources/themes/anchor/pages/signup.blade.php
$acceptLanguage = request()->header('Accept-Language', 'es');
$locale = substr($acceptLanguage, 0, 2);
$locale = in_array($locale, ['es', 'en']) ? $locale : 'es';
```

## Clases de Email Disponibles

### 1. PropertyMatchFoundMail
Email enviado cuando se encuentra una propiedad que coincide con la solicitud del usuario.

**Uso:**
```php
use App\Mail\PropertyMatchFoundMail;
use Illuminate\Support\Facades\Mail;

Mail::to($user->email)->send(
    new PropertyMatchFoundMail(
        user: $user,
        request: $propertyRequest,
        property: $propertyListing,
        matchScore: 85,  // opcional
        matchReasons: [  // opcional
            'Mismo tipo de propiedad',
            'Precio dentro del rango',
            'Ubicación cercana'
        ]
    )
);
```

**Templates:**
- `resources/views/emails/es/property-match-found.blade.php`
- `resources/views/emails/en/property-match-found.blade.php`

### 2. PropertyMessageReceivedMail
Email enviado al propietario cuando recibe un mensaje sobre su propiedad.

**Uso:**
```php
use App\Mail\PropertyMessageReceivedMail;
use Illuminate\Support\Facades\Mail;

Mail::to($property->user->email)->send(
    new PropertyMessageReceivedMail(
        property: $propertyListing,
        message: $propertyMessage
    )
);
```

**Templates:**
- `resources/views/emails/es/message-received.blade.php`
- `resources/views/emails/en/message-received.blade.php`

### 3. PropertyMessageReceived (Clase Existente Actualizada)
Versión anterior actualizada para soportar multiidioma.

**Uso:**
```php
use App\Mail\PropertyMessageReceived;

Mail::to($property->user->email)->send(
    new PropertyMessageReceived($propertyMessage)
);
```

## Notificaciones

### PropertyMatchFoundNotification
Notificación que envía email y guarda en base de datos.

**Uso:**
```php
use App\Notifications\PropertyMatchFoundNotification;

$user->notify(
    new PropertyMatchFoundNotification(
        request: $propertyRequest,
        property: $propertyListing,
        matchScore: 85,  // opcional
        matchReasons: [  // opcional
            'Razón 1',
            'Razón 2'
        ]
    )
);
```

**Canales:**
- `mail` - Envía email usando PropertyMatchFoundMail
- `database` - Guarda notificación en tabla `notifications`

## Archivos de Traducción

### resources/lang/es/emails.php
```php
return [
    'property_match' => [
        'subject' => '¡Nueva propiedad coincide con tu búsqueda!',
        'greeting' => '¡Hola :name!',
        'intro' => 'Hemos encontrado una propiedad...',
        // ... más traducciones
    ],
    'message_received' => [
        'subject' => 'Nuevo mensaje sobre tu propiedad',
        // ... más traducciones
    ],
];
```

### resources/lang/en/emails.php
Similar estructura en inglés.

## Lógica de Selección de Idioma

Todos los emails y notificaciones:

1. Obtienen el `locale` del usuario destinatario
2. Si no existe, usan `'es'` como default
3. Establecen el locale usando `app()->setLocale($locale)`
4. Cargan el template correspondiente: `emails.{$locale}.nombre-template`

**Ejemplo:**
```php
$locale = $user->locale ?? 'es';
app()->setLocale($locale);

return new Content(
    markdown: "emails.{$locale}.property-match-found",
    // ...
);
```

## Testing

### Enviar Email de Prueba

```bash
php artisan tinker
```

```php
$user = User::first();
$user->locale = 'es';  // o 'en'
$user->save();

$request = \App\Models\PropertyRequest::first();
$property = \App\Models\PropertyListing::first();

// Probar email
Mail::to($user->email)->send(
    new \App\Mail\PropertyMatchFoundMail(
        $user, $request, $property, 85, ['Test reason']
    )
);

// Probar notificación
$user->notify(
    new \App\Notifications\PropertyMatchFoundNotification(
        $request, $property, 85, ['Test reason']
    )
);
```

### Verificar en Mailhog/Mailtrap
1. Configura `MAIL_MAILER=log` en `.env` para ver en logs
2. O usa Mailhog/Mailtrap para visualizar emails

## Ejemplo de Integración en PropertyMatchingService

```php
use App\Notifications\PropertyMatchFoundNotification;

class PropertyMatchingService
{
    public function notifyUserOfMatch($request, $property, $matchScore, $reasons)
    {
        $user = $request->user;
        
        // Enviar notificación (email + database)
        $user->notify(
            new PropertyMatchFoundNotification(
                request: $request,
                property: $property,
                matchScore: $matchScore,
                matchReasons: $reasons
            )
        );
    }
}
```

## Queues (Opcional)

Todos los emails implementan `ShouldQueue`, por lo que se enviarán de forma asíncrona si tienes queues configuradas:

```bash
php artisan queue:work
```

Si no tienes queues, los emails se envían de forma síncrona.

## Notas Importantes

1. **Siempre usar el locale del usuario destinatario**, no del usuario que ejecuta la acción
2. **Fallback a 'es'** si el usuario no tiene locale configurado
3. **Templates markdown** para mejor formato y compatibilidad
4. **Traducciones centralizadas** en archivos `lang/`
5. **Queued por default** para mejor rendimiento

## Migraciones Relacionadas

- `2025_11_25_172504_add_locale_to_users_table.php` - Agrega campo `locale` a `users`

## Archivos Creados/Modificados

**Nuevos:**
- `app/Mail/PropertyMatchFoundMail.php`
- `app/Mail/PropertyMessageReceivedMail.php`
- `app/Notifications/PropertyMatchFoundNotification.php`
- `resources/views/emails/es/property-match-found.blade.php`
- `resources/views/emails/en/property-match-found.blade.php`
- `resources/views/emails/es/message-received.blade.php`
- `resources/views/emails/en/message-received.blade.php`
- `resources/lang/es/emails.php`
- `resources/lang/en/emails.php`

**Modificados:**
- `app/Models/User.php` - Agregado campo `locale` a `$fillable`
- `app/Mail/PropertyMessageReceived.php` - Actualizado para multiidioma
- `resources/themes/anchor/pages/settings/profile.blade.php` - Selector de idioma
- `resources/themes/anchor/pages/signup.blade.php` - Detección automática
- `resources/lang/es/settings.php` - Traducciones de perfil
- `resources/lang/en/settings.php` - Traducciones de perfil
