# Sistema de Solicitudes y Matching de Propiedades

## 📋 Resumen

Sistema completo de solicitudes/pedidos de propiedades con matching inteligente usando IA (embeddings de OpenAI y pgvector). Permite que los usuarios publiquen lo que buscan y reciban coincidencias automáticas con los anuncios disponibles.

## 🗂️ Estructura

### Modelos

#### PropertyRequest (`app/Models/PropertyRequest.php`)
Modelo principal para las solicitudes de búsqueda.

**Campos principales:**
- `title`: Título de la solicitud
- `description`: Descripción detallada
- `property_type`: casa, departamento, local, terreno, etc.
- `transaction_type`: venta, alquiler
- `min_budget` / `max_budget`: Rango de presupuesto
- `currency`: USD, ARS, EUR
- `min_bedrooms`, `min_bathrooms`, `min_parking_spaces`, `min_area`: Características mínimas
- `city`, `state`, `country`: Ubicación deseada
- `is_active`: Estado de la solicitud
- `expires_at`: Fecha de expiración opcional
- `embedding`: Vector de embeddings (1536 dimensiones) para búsqueda semántica

**Relaciones:**
- `user()`: Pertenece a un usuario
- `scopeActive()`: Solo solicitudes activas y no expiradas
- `scopeExpired()`: Solo solicitudes expiradas

**Atributos:**
- `budget_range`: Formatea el rango de presupuesto

### Servicios

#### PropertyMatchingService (`app/Services/PropertyMatchingService.php`)
Servicio centralizado para encontrar matches entre solicitudes y anuncios.

**Métodos principales:**

1. **`findMatchesForRequest(PropertyRequest $request, int $limit = 20)`**
   - Encuentra anuncios que coinciden con una solicitud
   - Retorna Collection de PropertyListing con metadatos de match

2. **`findMatchesForListing(PropertyListing $listing, int $limit = 20)`**
   - Encuentra solicitudes que coinciden con un anuncio
   - Retorna Collection de PropertyRequest con metadatos de match

**Niveles de Matching:**

1. **Match Exacto (exact)** - Score >= 85%
   - Tipo de propiedad coincide
   - Tipo de transacción coincide
   - Precio dentro del presupuesto
   - Ubicación (ciudad o provincia)
   - Características cumplen requisitos mínimos

2. **Match Inteligente (semantic)** - Score 60-84%
   - Usa embeddings de OpenAI para similitud semántica
   - Búsqueda por vector usando pgvector
   - Considera descripción completa

3. **Match Flexible (flexible)** - Score < 60%
   - Coincidencias parciales
   - Ubicación flexible (mismo país)
   - Precio flexible (±20%)

**Sistema de Puntuación:**
- Tipo de propiedad: 25 puntos
- Tipo de transacción: 25 puntos
- Precio dentro presupuesto: 20 puntos
- Ciudad coincide: 15 puntos
- Provincia coincide: 10 puntos
- País coincide: 5 puntos
- Habitaciones suficientes: 5 puntos
- Baños suficientes: 5 puntos
- Área suficiente: 5 puntos

### Controladores

#### PropertyRequestController (`app/Http/Controllers/PropertyRequestController.php`)
Gestiona el CRUD de solicitudes.

**Rutas:**
- `GET /dashboard/requests` - Lista de solicitudes del usuario
- `GET /dashboard/requests/create` - Formulario crear
- `POST /dashboard/requests` - Guardar nueva solicitud
- `GET /dashboard/requests/{id}` - Ver solicitud con matches
- `GET /dashboard/requests/{id}/edit` - Formulario editar
- `PUT /dashboard/requests/{id}` - Actualizar solicitud
- `DELETE /dashboard/requests/{id}` - Eliminar solicitud
- `POST /dashboard/requests/{id}/toggle-active` - Activar/desactivar

**Funcionalidades:**
- Generación automática de embeddings con OpenAI
- Validación de datos
- Control de acceso (solo propietario)
- Regenera embeddings al editar descripción

#### PropertyMatchController (`app/Http/Controllers/PropertyMatchController.php`)
Muestra matches entre anuncios del usuario y solicitudes.

**Rutas:**
- `GET /dashboard/matches` - Resumen de todos los matches
- `GET /dashboard/matches/listing/{id}` - Matches de un anuncio específico

## 🎨 Vistas

### Dashboard Principal
**Archivo:** `resources/themes/anchor/pages/dashboard/index.blade.php`

**Mejoras agregadas:**
- Cards de estadísticas (Anuncios, Solicitudes, Matches)
- Enlaces rápidos a secciones
- Cuenta de matches en tiempo real

### Solicitudes

#### Index (`dashboard/requests/index.blade.php`)
- Lista paginada de solicitudes
- Badges de estado (Activa, Inactiva, Expirada)
- Botones: Ver Matches, Editar, Activar/Desactivar
- Diseño responsivo con cards

#### Create (`dashboard/requests/create.blade.php`)
Formulario completo con:
- Título y descripción (mín. 20 caracteres)
- Tipo de propiedad (7 opciones)
- Tipo de operación (venta/alquiler)
- Presupuesto (mínimo y máximo)
- Moneda (USD, ARS, EUR)
- Ubicación (país, provincia, ciudad)
- Características mínimas opcionales
- Fecha de expiración opcional

#### Show (`dashboard/requests/show.blade.php`)
- Detalles completos de la solicitud
- Grid de propiedades coincidentes
- Badges de nivel de match (Exacto, Inteligente, Flexible)
- Porcentaje de coincidencia
- Lista de razones del match
- Enlace a ver detalles de cada propiedad

#### Edit (`dashboard/requests/edit.blade.php`)
- Formulario pre-llenado
- Checkbox de activar/desactivar
- Botón eliminar con confirmación

### Matches

#### Index (`dashboard/matches/index.blade.php`)
- Agrupado por anuncio del usuario
- Muestra hasta 5 matches por anuncio
- Grid de solicitudes con info del solicitante
- Badges de nivel de match
- Enlaces de contacto (email)

#### Show (`dashboard/matches/show.blade.php`)
- Info completa del anuncio
- Todas las solicitudes coincidentes
- Detalles completos de cada solicitud
- Info de contacto del solicitante
- Botones de email y WhatsApp

## 🔧 Configuración

### Variables de Entorno
```env
OPENAI_API_KEY=sk-...
```

### Dependencias
- Laravel 10+
- PostgreSQL con extensión pgvector
- OpenAI API (modelo text-embedding-ada-002)
- Paquete pgvector/pgvector

## 📊 Base de Datos

### Tabla: property_requests
```sql
- id (bigint, PK)
- user_id (bigint, FK → users)
- title (varchar)
- description (text)
- property_type (varchar)
- transaction_type (varchar)
- min_budget (decimal, nullable)
- max_budget (decimal)
- currency (varchar, default: USD)
- min_bedrooms (int, nullable)
- min_bathrooms (int, nullable)
- min_parking_spaces (int, nullable)
- min_area (int, nullable)
- city (varchar, nullable)
- state (varchar, nullable)
- country (varchar)
- is_active (boolean, default: true)
- expires_at (timestamp, nullable)
- embedding (vector(1536), nullable)
- created_at, updated_at (timestamps)
```

### Índices
- `hnsw (embedding vector_cosine_ops)` - Búsqueda vectorial eficiente
- `user_id` - Foreign key
- `is_active, expires_at` - Filtrado de solicitudes activas

## 🚀 Flujo de Uso

### Para Usuarios que Buscan

1. **Crear Solicitud**
   - `/dashboard/requests/create`
   - Completa formulario con lo que busca
   - Sistema genera embedding automáticamente
   - Guarda solicitud

2. **Ver Matches**
   - `/dashboard/requests/{id}`
   - Sistema busca anuncios compatibles
   - Muestra resultados ordenados por score
   - Puede contactar directamente a anunciantes

3. **Gestionar Solicitudes**
   - Editar detalles
   - Activar/desactivar
   - Establecer fecha de expiración
   - Eliminar

### Para Anunciantes

1. **Publicar Anuncio**
   - Ya existente: `/dashboard/property-listings`
   - Sistema genera embedding del anuncio

2. **Ver Solicitudes Compatibles**
   - `/dashboard/matches`
   - Muestra solicitudes que buscan su tipo de propiedad
   - Agrupado por anuncio propio

3. **Contactar Interesados**
   - Ver detalles de solicitud
   - Email o WhatsApp directo

## 🎯 Características Destacadas

### Matching Inteligente
- **3 niveles de coincidencia** con scores precisos
- **Búsqueda semántica** usando IA (OpenAI embeddings)
- **Filtros tradicionales** (precio, ubicación, características)
- **Ranking automático** prioriza matches exactos

### Embeddings Automáticos
- Generados al crear/editar solicitud o anuncio
- Modelo: `text-embedding-ada-002` (1536 dimensiones)
- Almacenados en PostgreSQL con pgvector
- Búsqueda eficiente con índice HNSW

### UX Optimizada
- Badges visuales de nivel de match
- Porcentajes de coincidencia
- Explicación de por qué coincide
- Contacto directo (email/WhatsApp)
- Diseño responsivo

### Gestión Flexible
- Solicitudes con expiración opcional
- Activar/desactivar sin eliminar
- Edición con regeneración de embeddings
- Paginación en listados

## 🔒 Seguridad

- Middleware `auth` en todas las rutas
- Verificación de propiedad en edit/update/delete
- Validación de datos en formularios
- Sanitización de inputs
- Control de acceso por usuario

## 📈 Escalabilidad

- Índice HNSW para búsqueda vectorial rápida
- Paginación en listados
- Límite configurable de resultados
- Caché potencial en conteo de matches
- Queries optimizadas con eager loading

## 🛠️ Mantenimiento

### Limpiar Solicitudes Expiradas
```php
// Crear comando Artisan
PropertyRequest::expired()->update(['is_active' => false]);
```

### Regenerar Embeddings
```php
// Si cambia el modelo de OpenAI
$request->update([
    'embedding' => generateEmbedding($request->title, $request->description)
]);
```

## 📝 Notas de Implementación

1. **OpenAI API**: Requiere clave válida en `.env`
2. **pgvector**: Debe estar instalado en PostgreSQL
3. **Caché de vistas**: Limpiar con `php artisan view:clear` después de cambios
4. **Rutas**: Todas bajo `/dashboard` con middleware auth
5. **Relaciones**: User tiene `propertyRequests()` y `propertyListings()`

## 🔄 Mejoras Futuras Posibles

- Notificaciones de nuevos matches
- Sistema de favoritos
- Chat directo entre usuarios
- Historial de contactos
- Estadísticas de matches
- Exportar matches a PDF
- Filtros avanzados en búsqueda
- Ordenamiento personalizado
- Match score ajustable por usuario
- Sistema de alertas por email

---

**Versión:** 1.0  
**Fecha:** Diciembre 2025  
**Autor:** Sistema implementado para Wave SaaS
