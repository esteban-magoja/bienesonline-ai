# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Wave is a Laravel-based SaaS framework that provides essential features for building subscription-based applications. The application uses a modular architecture with themes, plugins, and a custom admin panel built with Filament.

## Customizaciones Implementadas

### Campos de Usuario Adicionales (Octubre 2025)
- **Migración**: `2025_10_01_174705_add_additional_fields_to_users_table.php`
- **Campos agregados**: agency, movil, address, city, state, country (todos nullable)
- **Formulario de registro**: `/signup` con campo móvil opcional
- **Formulario de perfil**: `/settings/profile` con todos los campos (móvil requerido)
- **Redirecciones**: `/register` y `/auth/register` → `/signup`

### Configuración de Perfil
- **Archivo**: `config/profile.php`
- **Campo eliminado**: "What do you do for a living?" (occupation)
- **Campo 'About'**: Cambiado de requerido a opcional

### Estructura de Datos
- **Campos directos**: Guardados en tabla `users`
- **Campos dinámicos**: Guardados en `profile_key_values` via config
- **Remember token**: Configurado correctamente en registro personalizado

### Sistema de Propiedades Inmobiliarias (Diciembre 2025)

#### Modelos y Tablas
- **PropertyListing**: Modelo principal de anuncios inmobiliarios
  - Tabla: `property_listings`
  - Relaciones: `user`, `images`, `primaryImage`
  - Scopes: `active()`, `featured()`
  - Usa pgvector para embeddings de búsqueda semántica
  
- **PropertyImage**: Imágenes de propiedades
  - Tabla: `property_images`
  - Relación: `propertyListing`
  - Campo `is_primary` para imagen destacada

- **PropertyRequest**: Modelo de solicitudes/pedidos de búsqueda (Diciembre 2025)
  - Tabla: `property_requests`
  - Relaciones: `user`
  - Scopes: `active()`, `expired()`
  - Campos: title, description, property_type, transaction_type, presupuesto (min/max), características mínimas, ubicación
  - Usa pgvector para embeddings y matching inteligente con IA
  - Campo `expires_at` para solicitudes con fecha límite

#### Servicios
- **PropertyMatchingService**: Sistema de matching entre solicitudes y anuncios
  - 3 niveles: Exacto (85%+), Inteligente/Semántico (60-84%), Flexible (<60%)
  - Usa embeddings de OpenAI para similitud semántica
  - Scoring: tipo propiedad (25pts), transacción (25pts), precio (20pts), ubicación (15pts), características (5pts c/u)
  - Métodos: `findMatchesForRequest()`, `findMatchesForListing()`

#### Controladores
- **PropertySearchController**: Búsqueda de propiedades con IA
  - Ruta: `/search-properties` → `property.search`
  - Búsqueda semántica usando OpenAI embeddings (pgvector)
  - Filtrado por país (obligatorio)
  - Validación: mínimo 5 caracteres en búsqueda
  
- **PropertyController**: Detalle de propiedades
  - Ruta: `/property/{id}` → `property.show`
  - Vista: `property-detail.blade.php`
  - SEO dinámico (title, description, Open Graph)
  - Propiedades relacionadas (mismo tipo o ciudad)

- **PropertyRequestController**: CRUD de solicitudes/pedidos
  - Rutas bajo `/dashboard/requests`
  - Acciones: index, create, store, show, edit, update, destroy, toggle-active
  - Generación automática de embeddings con OpenAI
  - Muestra matches automáticos al ver solicitud
  - Solo el propietario puede editar/eliminar

- **PropertyMatchController**: Gestión de matches
  - Rutas bajo `/dashboard/matches`
  - `/dashboard/matches` → Resumen de todos los matches por anuncio
  - `/dashboard/matches/listing/{id}` → Matches de un anuncio específico
  - Muestra solicitudes compatibles con anuncios del usuario
  - Vista: `property-detail.blade.php`
  - SEO dinámico (title, description, Open Graph)
  - Propiedades relacionadas (mismo tipo o ciudad)

#### Vistas y Características

**Página de Búsqueda** (`property-search.blade.php`):
- Búsqueda inteligente con embeddings de OpenAI
- Filtro obligatorio por país
- Resultados con score de similitud
- Cards responsivas con imagen, precio, ubicación
- Botón "Ver Detalles" enlaza a ficha individual

**Página de Detalle** (`property-detail.blade.php`):
- Layout: `<x-layouts.marketing :seo="$seo">`
- Galería de imágenes con navegación (flechas, teclado)
- Estadísticas principales con iconos (horizontal layout):
  - Habitaciones (icono cama)
  - Baños (icono ducha)
  - m² Cubiertos (icono dimensiones)
  - Cocheras (icono auto)
  - m² Terreno (icono ubicación)
- Mapa interactivo OpenStreetMap + Leaflet.js:
  - Solo si tiene coordenadas (latitude/longitude)
  - Marcador personalizado (pin con emoji 🏠)
  - Círculo de área (100m radio)
  - Sin popup (marcador visual simple)
  - Centrado automático con `invalidateSize()`
- Sidebar de contacto:
  - Info del anunciante (avatar, nombre, agencia, email)
  - Botón WhatsApp (verde oscuro #128C7E)
  - Solo si user tiene campo `movil`
  - Formulario de contacto
  - Botón "Llamar Ahora" (solo con móvil)
- Sección "Compartir" (Facebook, Twitter, Copiar)
- Propiedades relacionadas (4 similares)

**Dashboard de Solicitudes** (`dashboard/requests/`):
- **index.blade.php**: Lista de solicitudes del usuario
  - Cards con badges de estado (Activa, Inactiva, Expirada)
  - Botones: Ver Matches, Editar, Activar/Desactivar
  - Paginación
  
- **create.blade.php**: Formulario de nueva solicitud
  - Título y descripción (mín. 20 caracteres)
  - Tipo de propiedad (casa, depto, local, oficina, terreno, campo, galpón)
  - Tipo de operación (venta/alquiler)
  - Presupuesto (mínimo y máximo) con moneda (USD, ARS, EUR)
  - Ubicación (país, provincia, ciudad)
  - Características mínimas opcionales (habitaciones, baños, cocheras, área)
  - Fecha de expiración opcional
  
- **show.blade.php**: Detalle de solicitud con matches
  - Info completa de la solicitud
  - Grid de propiedades coincidentes
  - Badges de nivel de match (Exacto, Inteligente, Flexible)
  - Porcentaje de coincidencia
  - Razones del match
  - Enlaces a ver detalles de cada propiedad
  
- **edit.blade.php**: Edición de solicitud
  - Formulario pre-llenado
  - Checkbox activar/desactivar
  - Botón eliminar con confirmación

**Dashboard de Matches** (`dashboard/matches/`):
- **index.blade.php**: Resumen de matches por anuncio
  - Agrupado por anuncios del usuario
  - Hasta 5 matches mostrados por anuncio
  - Info del solicitante con email
  - Enlace "Ver todos" si hay más de 5
  
- **show.blade.php**: Matches de un anuncio específico
  - Info completa del anuncio con imagen
  - Todas las solicitudes coincidentes
  - Detalles completos de cada solicitud
  - Contacto del solicitante (email + WhatsApp)
  - Explicación detallada del match

**Dashboard Principal** (`dashboard/index.blade.php`):
- Cards de estadísticas rápidas:
  - Total de anuncios publicados
  - Total de solicitudes activas
  - Total de matches encontrados
- Enlaces directos a cada sección
- Integración con PropertyMatchingService para conteo en tiempo real

#### SEO Optimización
Cada propiedad genera automáticamente:

**Title Tag**:
```
{título} - {transacción} en {ciudad}
Ejemplo: Casa moderna - Venta en Córdoba
```

**Meta Description** (límite 160 caracteres):
```
{tipo} en {transacción} • {ubicación} • {precio} • {características}
Ejemplo: Casa en venta • Córdoba, Argentina • USD 250,000 • 3 hab., 2 baños, 150m²
```

**Open Graph Tags**:
- og:title, og:description, og:image
- og:type: "article"
- Dimensiones imagen: 1200x630px
- Imagen: primaryImage → primera imagen → fallback

**Método**: `PropertyController::generateMetaDescription()`
- Construye descripción dinámica con datos de la propiedad
- Prioriza: tipo, ubicación, precio, características
- Trunca a 160 caracteres si excede

#### Integración OpenStreetMap
- **Librería**: Leaflet.js v1.9.4
- **Tiles**: OpenStreetMap (gratuito, sin API key)
- **CDN**: unpkg.com/leaflet@1.9.4
- **Características**:
  - Mapa responsive (h-80, 320px)
  - Zoom inicial: nivel 15
  - Marcador custom con pin azul y emoji casa
  - Control de escala métrico
  - Enlace a OpenStreetMap
  - Recalcula tamaño con `invalidateSize()` (fix centrado)

#### Notas Importantes
- **Blade Components**: Pasar variables a layouts con `:variable="$value"`
  - Ejemplo: `<x-layouts.marketing :seo="$seo">`
- **Embeddings**: Usa OpenAI API para búsqueda semántica
- **Validación búsqueda**: País obligatorio + mínimo 5 caracteres
- **Cache**: Limpiar vistas después de cambios (`php artisan view:clear`)
- **Iconos**: SVG outline style para mejor claridad visual

## Development Commands

### Frontend Development
- `npm run dev` - Start Vite development server
- `npm run build` - Build assets for production

### Backend Development
- `php artisan serve` - Start Laravel development server
- `composer run dev` - Start full development environment (server, queue, logs, and Vite)

### Database & Migrations
- `php artisan migrate` - Run database migrations
- `php artisan db:seed` - Seed the database
- `php artisan migrate:fresh --seed` - Fresh migration with seeding

### Testing
- `php artisan test` - Run PHPUnit tests
- `vendor/bin/pest` - Run Pest tests

### Queue Management
- `php artisan queue:work` - Process queued jobs
- `php artisan queue:listen --tries=1` - Listen for jobs with retry limit

### Wave-Specific Commands
- `php artisan wave:cancel-expired-subscriptions` - Cancel expired subscriptions
- `php artisan wave:create-plugin` - Create a new plugin

## Architecture Overview

### Core Structure
- `app/` - Standard Laravel application files
- `wave/` - Wave framework core files and components
- `resources/themes/` - Theme files (Blade templates, assets)
- `resources/plugins/` - Plugin system files
- `config/wave.php` - Main Wave configuration

### Key Components

#### Wave Service Provider (`wave/src/WaveServiceProvider.php`)
- Registers middleware, Livewire components, and Blade directives
- Handles plugin registration and theme management
- Configures Filament colors and authentication

#### Models & Database
- User model extends Wave User with subscription capabilities
- Subscription management with Stripe/Paddle integration
- Role-based permissions using Spatie Laravel Permission

#### Theme System
- Multiple themes available in `resources/themes/`
- Theme switching in demo mode via cookies
- Folio integration for page routing

#### Admin Panel
- Filament-based admin interface
- Resource management for users, posts, plans, etc.
- Located in `app/Filament/`

### Billing Integration
- Supports both Stripe and Paddle
- Configured via `config/wave.php` and environment variables
- Webhook handling for subscription events

### Plugin System
- Plugins located in `resources/plugins/`
- Auto-loading via `PluginServiceProvider`
- Plugin creation command available

## Configuration

### Environment Variables
- `WAVE_DOCS` - Show/hide documentation
- `WAVE_DEMO` - Enable demo mode
- `WAVE_BAR` - Show development bar
- `BILLING_PROVIDER` - Set to 'stripe' or 'paddle'

### Important Config Files
- `config/wave.php` - Main Wave configuration
- `config/themes.php` - Theme configuration
- `config/settings.php` - Application settings

## Testing

The application uses Pest for testing with PHPUnit as the underlying framework. Test files are located in `tests/` with separate directories for Feature and Unit tests.

## Development Notes

- The application uses Laravel Folio for page routing
- Livewire components handle dynamic UI interactions
- Filament provides the admin interface
- Theme development follows Blade templating conventions
- Plugin development follows Laravel package conventions

## Performance Optimizations

### Caching Strategy
- User subscription/admin status cached for 5-10 minutes
- Active plans cached for 30 minutes
- Categories cached for 1 hour
- Helper files cached permanently until cleared
- Theme colors cached for 1 hour
- Plugin lists cached for 1 hour

### Cache Clearing
- User caches cleared via `$user->clearUserCache()` method
- Plan caches cleared via `Plan::clearCache()` method
- Category caches cleared via `Category::clearCache()` method

### Database Optimizations
- Eager loading relationships to prevent N+1 queries
- Cached query results for frequently accessed data
- Optimized middleware to use cached user roles

### Usage Tips
- Use `Plan::getActivePlans()` instead of `Plan::where('active', 1)->get()`
- Use `Plan::getByName($name)` instead of `Plan::where('name', $name)->first()`
- Use `Category::getAllCached()` instead of `Category::all()`
- Always clear relevant caches when updating user roles, plans, or categories

### Installation & CI Compatibility
- All caching methods include fallbacks for when cache service is unavailable
- Service provider guards against cache binding issues during package discovery
- Compatible with automated testing environments and CI/CD pipelines

---

## Internacionalización (i18n) - Español/Inglés

### Estructura de Archivos
**⚠️ IMPORTANTE: Wave usa `/resources/lang/` NO `/lang/`**

```
resources/lang/
├── es/
│   ├── properties.php      # Traducciones de propiedades
│   ├── messages.php         # Mensajes generales
│   ├── dashboard.php        # Dashboard
│   ├── seo.php             # Meta tags SEO
│   ├── attributes.php      # Nombres de atributos para validación
│   └── validation.php      # Mensajes de validación
└── en/
    └── [mismos archivos]
```

### Documentación i18n
- **`I18N_INDEX.md`**: Índice principal del proyecto i18n
- **`I18N_IMPLEMENTATION_PLAN.md`**: Plan detallado de 12 días
- **`I18N_DAILY_CHECKLIST.md`**: Checklist diario
- **`I18N_TROUBLESHOOTING.md`**: ⭐ **Solución a problemas comunes**
- **`I18N_HYBRID_STRATEGY.md`**: Estrategia de rutas (público con locale, dashboard con sesión)

### Scripts de Gestión
```bash
./START_I18N.sh           # Iniciar día de trabajo
./FINISH_I18N_DAY.sh      # Finalizar día (commit + tracking)
./VIEW_I18N_STATUS.sh     # Ver estado del proyecto
```

### Problema Común: Traducciones No Se Cargan
**Síntoma**: Ver `messages.home` o `properties.contact_advertiser` en lugar del texto traducido.

**Solución**:
```bash
# 1. Asegurar que archivos estén en resources/lang/
cp lang/en/*.php resources/lang/en/
cp lang/es/*.php resources/lang/es/

# 2. Limpiar cache
php artisan optimize:clear

# 3. Reiniciar servidor
# Ctrl+C y luego:
php artisan serve
```

**Ver más**: `I18N_TROUBLESHOOTING.md`

---
- Compatible with automated testing environments and CI/CD pipelines
---

## Sistema de Listados Públicos con URLs SEO (Febrero 2026)

### 📚 Documentación
- **Completa**: `SISTEMA_LISTADOS_PUBLICOS.md` (11KB) - Todos los detalles
- **Quick Start**: `LISTADOS_QUICK_START.md` (3.3KB) - Referencia rápida
- **Resumen Sesión**: `RESUMEN_SESION_05FEB2026.txt` - Resumen ejecutivo

### 🎯 URLs Implementadas
```
/{locale}/{país}/{operación?}/{tipo?}/{estado?}/{ciudad?}

Funcionando:
✓ /es/argentina
✓ /es/argentina/venta
✓ /es/argentina/venta/casas
✓ /en/argentina/sale/houses
```

### 📁 Archivos del Sistema
- **Helper**: `app/Helpers/PropertySlugHelper.php` - Validación dinámica con mapeo i18n
- **Controlador**: `app/Http/Controllers/PropertyListingController.php` - Parseo inteligente
- **Vista**: `resources/views/property-listing.blade.php` - Grid responsive (NO en themes/)
- **Ruta**: `routes/web.php` (dentro grupo `{locale}`, AL FINAL)

### ⚠️ Puntos Críticos
1. **Vista en**: `resources/views/` NO `resources/themes/`
2. **Columna BD**: `area` NO `covered_area`
3. **Ruta home**: `route('home')` NO `route('wave.home')`
4. **Mapeo i18n**: Slugs españoles → valores inglés en BD
   - `venta` → `sale`
   - `casas` → `house`

### 🚀 Comandos Esenciales
```bash
# Desarrollo
composer dump-autoload -o
php artisan optimize:clear

# Deploy
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### ✅ Características Implementadas
- [x] Helper con validación dinámica DISTINCT
- [x] Mapeo i18n completo (es ↔ en)
- [x] Filtros: precio, habitaciones, baños, área
- [x] 7 opciones de ordenamiento
- [x] Paginación con query string
- [x] Breadcrumbs dinámicos traducidos
- [x] SEO completo (canonical, hreflang, OG)
- [x] Lazy loading de imágenes
- [x] Vista responsive

---

## URLs SEO-Friendly para Fichas de Anuncios (Febrero 2026)

### Estructura Implementada
**Nueva estructura jerárquica:**
```
/{locale}/{país}/{ciudad}/propiedad/{id}-{slug}
```

**Ejemplo:**
```
/es/argentina/villa-carlos-paz/propiedad/38-casa-en-venta-en-tanti
```

### Beneficios SEO
1. **Keywords relevantes**: País + ciudad + título en la URL
2. **Jerarquía clara**: Consistente con sistema de listados
3. **Descriptiva**: Google entiende el contexto geográfico
4. **Canonical correcto**: URLs únicas y consistentes
5. **Hreflang multilingual**: Alternativas es/en configuradas

### Archivos Modificados
- `routes/web.php` - Nueva estructura de rutas con parámetros `{country}/{city}/propiedad/{id}-{slug?}`
- `PropertyController.php` - Métodos `show()` y `sendMessage()` actualizados
- `SeoService.php` - Nuevo método `generatePropertyUrl()` para URLs consistentes
- `property-listing.blade.php` - Enlaces a fichas actualizados
- `property-detail.blade.php` - Enlaces de propiedades relacionadas
- `property-search.blade.php` - Enlaces del buscador

### Breadcrumbs Optimizados
**Cambios implementados:**
- ✅ Eliminado breadcrumb "Propiedades" (innecesario)
- ✅ URLs con slugs traducidos correctamente (venta/sale, casas/houses)
- ✅ Breadcrumbs de fichas: Home → País → Operación → Tipo → Estado → Ciudad → Título

**Estructura de breadcrumbs en listados:**
```
/es/argentina/venta/casas/cordoba
├── Inicio → /es
├── Argentina → /es/argentina
├── Venta → /es/argentina/venta
├── Casas → /es/argentina/venta/casas
└── Córdoba → /es/argentina/venta/casas/cordoba
```

### PropertySlugHelper Mejorado
**Normalización correcta de acentos:**
```php
// ANTES: Str::slug($text, '-', null) - Mantenía acentos
// AHORA: Str::slug($text, '-') - Quita acentos correctamente
```

**Validación en memoria:**
- `validateCountry()`, `validateState()`, `validateCity()` usan normalización en PHP
- Soluciona problema con acentos (Córdoba → cordoba)
- Mapeo dinámico de slugs traducidos en `generateBreadcrumbs()`

---

## Sistema de Perfiles de Usuario/Inmobiliaria (Febrero 2026)

### Página de Anuncios por Usuario
Permite ver todos los anuncios de un usuario específico (inmobiliaria o particular).

### URLs Implementadas
```
/es/inmobiliaria/{username}
/en/realtor/{username}
```

**Ejemplos:**
- `/es/inmobiliaria/inmobiliaria-rodriguez`
- `/en/realtor/john-doe-properties`

### Archivos del Sistema
- **Controlador**: `app/Http/Controllers/UserProfileController.php`
- **Vista**: `resources/views/user-profile.blade.php`
- **Rutas**: `routes/web.php` (líneas 79-87)
- **Traducciones**: `resources/lang/*/properties.php` (`user_profile.*`)

### Características Implementadas
- [x] Perfil público del usuario con avatar y datos
- [x] Información de contacto (email, móvil, ubicación)
- [x] Botones de contacto (WhatsApp, Llamar)
- [x] Estadísticas (propiedades activas, en venta, en alquiler)
- [x] Grid de propiedades con filtros y ordenamiento
- [x] Paginación con query string
- [x] Breadcrumbs dinámicos traducidos
- [x] SEO completo (canonical, hreflang, OG tags)
- [x] Botón "Ver todas las propiedades" en fichas individuales

---

## Sistema de Notificaciones Automáticas (Febrero 2026)

### Matching Inteligente con Notificaciones
Cuando se publica un nuevo anuncio, el sistema automáticamente:
1. Busca solicitudes (PropertyRequest) compatibles
2. Calcula score de coincidencia (0-100)
3. Envía notificación a usuarios con match >= 70%
4. **[NUEVO]** Muestra página de confirmación al anunciante con matches encontrados

### Flujo de Publicación con Matches
Al publicar un anuncio, después de guardar las imágenes:
```
1. Usuario completa formulario + sube imágenes
   ↓
2. Sistema guarda anuncio en BD
   ↓
3. [NUEVO] Redirección a /property-listings/matches-found/{id}
   ↓
4. Página muestra:
   - Confirmación de publicación exitosa
   - Número de solicitudes compatibles (score >= 70%)
   - Top 3 matches con datos del solicitante
   - Botones: Ver todos los matches, Ver anuncio público, Dashboard
   ↓
5. En paralelo: Sistema envía notificaciones a los solicitantes
```

### Archivos del Sistema
- `resources/themes/anchor/pages/property-listings/create.blade.php` - Formulario (modificado)
- `resources/themes/anchor/pages/property-listings/matches-found/[id].blade.php` - **[NUEVO]** Página de confirmación con matches
- `app/Events/PropertyListingCreated.php` - Evento
- `app/Listeners/NotifyMatchingRequests.php` - Listener
- `app/Observers/PropertyListingObserver.php` - Observer
- `app/Services/PropertyMatchingService.php` - Servicio de matching
- `config/matching.php` - Configuración

### Arquitectura
- **Event**: `PropertyListingCreated` - Disparado al crear anuncio
- **Listener**: `NotifyMatchingRequests` - Procesa matches (async/queued)
- **Observer**: `PropertyListingObserver` - Dispara evento en `created()`
- **Config**: `config/matching.php` - Configuración completa
- **[NUEVO] Vista**: Página de confirmación con matches encontrados

### Queue Workers en Producción

#### Opción A: Supervisor (Recomendado)
Requiere permisos sudo. Ver `DEPLOYMENT_CHECKLIST.md` para configuración.

#### Opción B: Cron (Sin sudo)
```bash
# Editar crontab
crontab -e

# Agregar línea (ejecuta cada minuto)
* * * * * cd /ruta/proyecto && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

**⚠️ Limitación:** Jobs procesados cada minuto, no en tiempo real.

### Verificación Rápida
```bash
# Ver si cron está configurado
crontab -l

# Probar manualmente
php artisan queue:work --stop-when-empty

# Ver notificaciones creadas
php artisan tinker
\DB::table('notifications')->where('type', 'LIKE', '%PropertyMatch%')->count();
exit
```

### Configuración (.env)
```bash
AUTO_MATCHING_ENABLED=true
MATCHING_MIN_SCORE=70
MATCHING_MAX_MATCHES=20
QUEUE_CONNECTION=database
```

### Documentación
- **`MATCHES_AFTER_PUBLISH.md`** - **[NUEVO]** Sistema de matches al publicar
- **`NOTIFICACIONES_AUTOMATICAS.md`** - Guía completa de uso
- **`ANALISIS_SISTEMA_MATCHING.md`** - Análisis técnico del sistema
- **`DEPLOYMENT_CHECKLIST.md`** - Checklist de producción

---

## Mejores Prácticas de Desarrollo

### ✅ Verificación con curl antes de confirmar cambios
**Siempre verificar que las páginas carguen sin errores:**

```bash
# Verificar que no haya errores 404 o 500
curl -s "http://127.0.0.1:8000/es/argentina" | grep -E "title|404|error" | head -3

# Verificar enlaces generados
curl -s "http://127.0.0.1:8000/es/argentina" | grep -o 'href="[^"]*propiedad[^"]*"' | head -5

# Verificar SEO tags
curl -s "http://127.0.0.1:8000/es/property/38" | grep -E "canonical|hreflang|og:title" | head -5

# Verificar breadcrumbs
curl -s "http://127.0.0.1:8000/es/argentina/venta/casas" | grep -A 50 "Breadcrumb" | grep href
```

### ✅ Comandos útiles de desarrollo

```bash
# Limpiar cache después de cambios en vistas
php artisan view:clear
php artisan optimize:clear

# Verificar rutas
php artisan route:list --path=argentina

# Probar en tinker
php artisan tinker --execute="echo App\Helpers\PropertySlugHelper::normalize('Córdoba');"
```

### ⚠️ Puntos importantes
1. **Wave usa `/resources/lang/` NO `/lang/`** para traducciones
2. **Vista de listados en**: `resources/views/` NO `resources/themes/`
3. **Columna BD**: `area` NO `covered_area`
4. **Ruta home**: `route('home')` NO `route('wave.home')`
5. **Blade components**: Pasar variables con `:variable="$value"`
6. **Normalización**: `Str::slug()` sin tercer parámetro para quitar acentos

---

