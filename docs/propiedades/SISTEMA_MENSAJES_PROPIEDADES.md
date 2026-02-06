# Sistema de Mensajes para Anuncios de Propiedades

## Implementación Completada - Diciembre 2025

### Descripción
Sistema completo de mensajes de contacto para los anuncios de propiedades inmobiliarias. Los usuarios registrados pueden enviar consultas a los anunciantes directamente desde la página de detalle de la propiedad.

## Características Implementadas

### 1. Base de Datos
- **Tabla**: `property_messages`
- **Campos**:
  - `id`: Identificador único
  - `property_listing_id`: ID de la propiedad (relación con `property_listings`)
  - `user_id`: ID del usuario que envía el mensaje (relación con `users`)
  - `name`: Nombre del remitente
  - `email`: Email del remitente
  - `phone`: Teléfono del remitente (opcional)
  - `message`: Contenido del mensaje
  - `is_read`: Estado de lectura (boolean)
  - `created_at`, `updated_at`: Timestamps

### 2. Modelo
- **Modelo**: `PropertyMessage`
- **Relaciones**:
  - `propertyListing()`: Pertenece a una propiedad
  - `user()`: Pertenece a un usuario
- **Ubicación**: `app/Models/PropertyMessage.php`

### 3. Sistema de Email
- **Mail Class**: `PropertyMessageReceived`
- **Vista del Email**: `resources/views/emails/property-message-received.blade.php`
- **Destinatario**: Propietario del anuncio
- **Contenido**: 
  - Información de la propiedad
  - Datos del interesado
  - Mensaje completo
  - Botón para responder

### 4. Formulario de Contacto
- **Ubicación**: `/property/{id}` (Vista de detalle)
- **Requisitos**: Usuario registrado y autenticado
- **Validaciones**:
  - Nombre: requerido
  - Email: requerido, formato email válido
  - Teléfono: opcional
  - Mensaje: requerido, máximo 2000 caracteres
- **Protecciones**:
  - No permite enviar mensajes a propiedades propias
  - Solo usuarios autenticados
- **Auto-completado**: Los campos se llenan automáticamente con los datos del usuario logueado

### 5. Panel de Administración (Filament)
- **Recurso**: `PropertyMessageResource`
- **Ubicación en menú**: "Mensajes"
- **Badge**: Muestra contador de mensajes no leídos
- **Características**:
  - Lista de mensajes filtrados por propiedades del usuario
  - Filtro por estado (leído/no leído)
  - Vista en modal con información completa
  - Marcar como leído automáticamente al visualizar
  - Botones de acción rápida (email, WhatsApp)
  - Campos de datos de contacto copiables
  - Solo lectura de mensajes (no se pueden editar)

### 6. Rutas
- **POST** `/property/{id}/message`: Enviar mensaje (requiere autenticación)
- **GET** `/admin/property-messages`: Lista de mensajes en Filament
- **GET** `/admin/property-messages/{record}/edit`: Ver/editar mensaje
- **GET** `/dashboard/messages`: Lista de mensajes en Dashboard (nuevo)
- **GET** `/dashboard/messages/{id}`: Ver detalle de mensaje (nuevo)
- **POST** `/dashboard/messages/{id}/mark-read`: Marcar como leído (nuevo)
- **POST** `/dashboard/messages/{id}/mark-unread`: Marcar como no leído (nuevo)
- **DELETE** `/dashboard/messages/{id}`: Eliminar mensaje (nuevo)

## Flujo de Uso

### Para Usuarios Interesados:
1. Navegar a la página de detalle de una propiedad `/property/{id}`
2. Iniciar sesión si no está autenticado
3. Completar el formulario de contacto (auto-llenado con datos del usuario)
4. Enviar el mensaje

### Para Propietarios:
1. Reciben email con la consulta
2. Acceden al panel de administración `/admin` o al dashboard `/dashboard`
3. Ven la notificación en el menú "Mensajes" (con badge/contador)
4. Revisan y responden las consultas
5. Los mensajes se marcan como leídos automáticamente

## Acceso a Mensajes

### Panel de Administración (Filament) - `/admin`
- Recurso completo con filtros avanzados
- Vista en modal
- Badge con contador de no leídos
- Solo para administradores y usuarios avanzados

### Dashboard de Usuario - `/dashboard/messages`
- Interfaz simplificada y amigable
- Lista de mensajes recibidos
- Vista detallada de cada mensaje
- Acciones rápidas (email, WhatsApp, teléfono)
- Marcar como leído/no leído
- Eliminar mensajes
- Contador en el dashboard principal
- Accesible para todos los usuarios

## Archivos Creados/Modificados

### Nuevos Archivos:
- `database/migrations/2025_10_10_190842_create_property_messages_table.php`
- `app/Models/PropertyMessage.php`
- `app/Mail/PropertyMessageReceived.php`
- `resources/views/emails/property-message-received.blade.php`
- `app/Filament/Resources/PropertyMessages/PropertyMessageResource.php`
- `app/Filament/Resources/PropertyMessages/Tables/PropertyMessagesTable.php`
- `app/Filament/Resources/PropertyMessages/Schemas/PropertyMessageForm.php`
- `app/Filament/Resources/PropertyMessages/Pages/ListPropertyMessages.php`
- `app/Filament/Resources/PropertyMessages/Pages/EditPropertyMessage.php`
- `resources/views/filament/resources/property-messages/view-message.blade.php`
- `app/Http/Controllers/PropertyMessageController.php` (nuevo)
- `resources/themes/anchor/pages/dashboard/messages/index.blade.php` (nuevo)
- `resources/themes/anchor/pages/dashboard/messages/show.blade.php` (nuevo)

### Archivos Modificados:
- `app/Models/PropertyListing.php` (agregada relación `messages()`)
- `app/Http/Controllers/PropertyController.php` (agregado método `sendMessage()`)
- `routes/web.php` (agregadas rutas POST para mensajes y rutas del dashboard)
- `resources/views/property-detail.blade.php` (formulario actualizado con autenticación)
- `resources/themes/anchor/pages/dashboard/index.blade.php` (agregada tarjeta de mensajes)

## Seguridad

- ✅ Requiere autenticación para enviar mensajes
- ✅ Validación de datos en backend
- ✅ Protección CSRF en formularios
- ✅ Solo propietarios ven mensajes de sus propiedades
- ✅ No se pueden enviar mensajes a propiedades propias
- ✅ Manejo de errores en envío de emails

## Notificaciones

- ✅ Email al propietario cuando recibe un mensaje
- ✅ Badge en menú de Filament con contador de no leídos
- ✅ Mensajes de éxito/error en frontend
- ✅ Marca automática de lectura al visualizar

## Próximas Mejoras Sugeridas

1. Sistema de respuestas desde el panel
2. Notificaciones en tiempo real
3. Historial de conversaciones
4. Estadísticas de mensajes recibidos
5. Plantillas de respuesta rápida
6. Integración con WhatsApp Business API
