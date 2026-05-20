# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⚠️ REGLA CRÍTICA: Dependencias Composer

**NUNCA agregar, eliminar ni actualizar paquetes de Composer sin autorización explícita del propietario del proyecto.**

- ❌ No ejecutar `composer require`, `composer remove`, ni `composer update`
- ❌ No modificar `composer.json` ni `composer.lock`
- ✅ Si una funcionalidad requiere un paquete nuevo, informar al propietario y esperar su autorización
- ✅ Resolver problemas usando librerías ya instaladas o funciones nativas de PHP (GD, etc.)

**Motivo**: El servidor de producción tiene restricciones de acceso a Composer. Agregar/quitar paquetes rompe el autoloader del servidor y puede dejar el sitio caído.

---

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
  - 3 niveles: Exacto (≥85%), Inteligente/Semántico (≥70%), Flexible (50-69%)
  - Matches con score < 50% se filtran completamente
  - Usa embeddings de OpenAI para similitud semántica
  - Scoring: tipo propiedad (25pts), transacción (25pts), precio (20pts), ciudad (15pts), provincia (10pts), características (5pts c/u), similitud semántica (hasta 15pts bonus)
  - Ubicación es **acumulativa**: ciudad Y provincia suman independientemente
  - **⚠️ Invariante crítica**: las búsquedas semánticas (`getSemanticMatches`, `getSemanticMatchesForListing`) DEBEN filtrar por `property_type` y `transaction_type` igual que el exact search. Sin este filtro, embeddings similares entre una "casa" y un "departamento" pueden producir falsos positivos.
  - Comparaciones de strings con `LOWER()` por case-sensitivity de PostgreSQL (imports legacy tienen 'Casa', usuarios crean 'casa')
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

## Sistema de Tipos Regionales por País (Febrero 2026)

### Objetivo
Resolver variaciones terminológicas en tipos de inmuebles y operaciones entre países hispanohablantes.

**Ejemplos de variaciones:**
- **Transacciones**: "Alquiler" (AR) vs "Renta" (MX) vs "Arriendo" (CL)
- **Tipos**: "Departamento" (AR/MX) vs "Piso" (ES) vs "Apartamento" (CO)

### Arquitectura

#### Tablas en Base de Datos
- **property_types**: Tipos de inmuebles por país
- **transaction_types**: Tipos de operación por país

**Estructura:**
```
- country_code: ISO2 (AR, MX, CL, ES, CO, INTL)
- value: Valor en español regional (departamento, piso, apartamento)
- label: Etiqueta mostrada al usuario (Departamento, Piso, Apartamento)
- value_en: Valor universal en inglés (apartment) - CLAVE PARA MATCHING
- order: Orden de presentación
- is_active: Habilitado/deshabilitado
```

#### Modelos
- **PropertyType** (`app/Models/PropertyType.php`)
- **TransactionType** (`app/Models/TransactionType.php`)

**Métodos clave:**
- `getByCountry($countryCode)`: Obtiene tipos por país con fallback automático a INTL
- `getValueEn($value, $countryCode)`: Obtiene valor universal con fallback global
- `getEquivalentValues($value, $countryCode)`: Retorna todos los valores equivalentes
- `clearCache()`: Limpia cache (1 hora por país)

### Países Configurados
- **INTL**: Fallback internacional (7 tipos genéricos)
- **AR** (Argentina): 9 tipos - Incluye PH, cochera, alquiler
- **MX** (México): 8 tipos - Usa "renta", incluye bodega, rancho
- **CL** (Chile): 8 tipos - Usa "arriendo", incluye parcela
- **ES** (España): 10 tipos - Usa "piso", "chalet", "ático", "garaje"
- **CO** (Colombia): 7 tipos - Usa "apartamento", incluye parqueadero

### Flujo de Funcionamiento

#### 1. Carga Dinámica en Formulario
```php
// Usuario selecciona país → dispara updatedSelectedCountry()
// Se obtiene ISO2 del país
$country = Country::find($this->selected_country);
$countryCode = $country->iso2; // ej: 'AR', 'MX'

// Se cargan tipos para ese país
$this->propertyTypes = PropertyType::getByCountry($countryCode);
$this->transactionTypes = TransactionType::getByCountry($countryCode);
```

#### 2. Fallback Automático a INTL
Si el país seleccionado no tiene datos (ej: Paraguay, Uruguay):
```php
// Busca tipos con country_code = 'PY'
// No encuentra registros
// Automáticamente carga country_code = 'INTL'
// Usuario ve opciones genéricas
```

#### 3. Matching con Equivalencias
**Problema**: Anuncio con "departamento" (AR) debe matchear solicitud con "piso" (ES)

**Solución**: value_en como puente universal
```php
// Anuncio: property_type = 'departamento' (AR)
$equivalents = PropertyType::getEquivalentValues('departamento', 'AR');
// Retorna: ['departamento', 'piso', 'apartamento']

// Todos tienen value_en = 'apartment'
// Query usa: whereIn('property_type', $equivalents)
```

#### 4. Fallback Global para Términos de Otros Países
**Caso edge**: Usuario colombiano publica en Argentina usando "apartamento"

```php
// getValueEn('apartamento', 'AR')
// 1. Busca en AR: NO existe
// 2. Busca globalmente: Encuentra en CO con value_en='apartment'
// 3. getEquivalentValues retorna: ['departamento', 'piso', 'apartamento']
// 4. Matching funciona correctamente
```

### Archivos del Sistema

**Migraciones:**
- `database/migrations/2026_02_13_192616_create_property_types_table.php`
- `database/migrations/2026_02_13_192630_create_transaction_types_table.php`

**Seeder:**
- `database/seeders/RegionalTypesSeeder.php` (50 property_types + 18 transaction_types)

**Modelos:**
- `app/Models/PropertyType.php`
- `app/Models/TransactionType.php`

**Formulario:**
- `resources/themes/anchor/pages/property-listings/create.blade.php`
  - Imports en líneas 1-13
  - Arrays $propertyTypes/$transactionTypes (líneas 88-91)
  - updatedSelectedCountry() método (líneas 124-147)
  - Selects dinámicos deshabilitados hasta seleccionar país

**Servicio de Matching:**
- `app/Services/PropertyMatchingService.php`
  - getExactMatches(): usa whereIn con equivalencias
  - getExactMatchesForListing(): mismo patrón
  - getCountryCode(): helper para obtener ISO2

**Traducciones:**
- `resources/lang/es/listings.php` (líneas 45-47)
- `resources/lang/en/listings.php` (líneas 45-47)
  - `select_property_type`
  - `select_transaction_type`
  - `select_country_first`

### Cache Strategy
```php
// Por país, 1 hora
Cache::remember("property_types_{$countryCode}", 3600, function() {
    return PropertyType::where('country_code', $countryCode)->get();
});
```

### Testing Realizado
Ver `TEST_RESULTS.txt` para detalles completos.

**Resultados:**
- ✅ Carga dinámica por país (AR: 9 tipos, MX: renta)
- ✅ Fallback INTL para países no configurados
- ✅ Equivalencias cross-regionales (piso=departamento=apartamento)
- ✅ Fallback global para términos de otros países
- ✅ Matching respeta filtro por país

### Documentación Adicional
- **`SISTEMA_TIPOS_REGIONALES.md`** - Documento de diseño completo

---

## Sistema de Países Habilitados (Marzo 2026)

### Objetivo
Mostrar solo países relevantes en los formularios públicos y del dashboard, sin modificar el paquete `nnjeim/world`.

### Tabla y Modelo
- **Tabla**: `country_settings` — `iso2` (PK), `is_enabled` (bool), `display_order` (smallint)
- **Modelo**: `app/Models/CountrySetting.php`

**Métodos clave:**
```php
CountrySetting::getEnabledCountries()   // → Collection de Country, ordenada por display_order (cache 1h)
CountrySetting::enable($iso2, $order)   // Habilita un país (crea registro si no existe)
CountrySetting::disable($iso2)          // Deshabilita (no elimina el registro)
CountrySetting::clearCache()            // Limpia cache 'enabled_country_iso2_codes'
```

### Países habilitados por defecto
AR (1), MX (2), CL (3), ES (4), CO (5) — configurados en la migración inicial vía `tinker`.

### Formularios actualizados
Todos usan `CountrySetting::getEnabledCountries()` en lugar de `Country::all()`:
- `resources/themes/anchor/pages/es/post-request.blade.php`
- `resources/themes/anchor/pages/en/post-request.blade.php`
- `resources/themes/anchor/pages/dashboard/requests/create.blade.php`
- `resources/themes/anchor/pages/property-listings/create.blade.php`
- `resources/themes/anchor/pages/property-listings/[id]/edit.blade.php`

**Nota**: `ImportListingsJob` y `PropertyMatchingService` siguen usando `Country::where('name', ...)` porque trabajan con datos existentes — NO filtrar ahí.

### Gestión en el Panel Admin
En `/admin/country-types` hay una sección **"Países Habilitados"** al inicio de la página con:
- Lista ordenada de países activos con botones ↑↓ para reordenar
- Botón ✕ para deshabilitar con confirmación
- Botón **"Agregar país"** → abre buscador con los 250 países del mundo, cada uno con botón "Habilitar"
- Al seleccionar un país en el dropdown de configuración de tipos: aparece botón verde **"Habilitar país"** si no está activo, o badge verde **"País habilitado (#N)"** si ya lo está

### Nota Livewire v3
En Livewire v3, las propiedades computadas con `getXxxProperty()` NO se acceden como `$this->getXxxProperty` en Blade (eso intenta acceder a una *propiedad* PHP, no llamar al método). Usar `$filteredCountries` (propiedad pública actualizada por `updatedCountrySearch()`) en su lugar.

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

## Sistema de Importación desde Proyecto Legacy (Febrero 2026)

### Objetivo
Permite a los usuarios importar sus anuncios del proyecto viejo con un botón en el dashboard.

### Flujo
1. Usuario selecciona país de origen y clickea "Importar mis anuncios"
2. Controller llama `GET {LEGACY_URL}/app/export-listings.php?email={email}`
3. Se despacha `ImportListingsJob` a la cola (background)
4. El job crea cada `PropertyListing` → Observer genera embedding automáticamente
5. Descarga imágenes con `Http::get()` → `Storage::disk('public')`
6. Dashboard muestra barra de progreso con polling cada 2 segundos

### Configuración (.env)
```bash
QUEUE_CONNECTION=database   # IMPORTANTE: no usar sync (el job tarda demasiado)

# Agregar URL por cada país que tenga dominio propio
LEGACY_URL_AR=https://argentina.bienesonline.com
LEGACY_URL_EC=https://www.bienesonline.ec
# Solo los países configurados aparecen en el selector del dashboard
```

### Países configurados en config/import.php
`Argentina`, `México`, `Chile`, `España`, `Colombia`, `Uruguay`, `Paraguay`, `Bolivia`, `Perú`, `Venezuela`, `Ecuador`

### Queue Worker — Producción
```bash
# Cron recomendado (usar ruta completa de php, verificar con `which php`)
* * * * * cd /home/bienesai/laravel-app && flock -n /tmp/laravel-queue.lock /usr/local/bin/ea-php84 artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

⚠️ **Notas importantes de producción**:
- Usar `flock -n` para evitar workers concurrentes (sin esto, cada minuto lanza un nuevo worker y se acumula carga)
- Usar la ruta completa de PHP (`which php` para obtenerla, puede ser `/usr/local/bin/ea-php84`)
- La tabla `failed_jobs` debe existir: `php artisan queue:failed-table && php artisan migrate`
- Después de deploy siempre ejecutar: `php -d memory_limit=-1 artisan optimize`

### Archivos del Sistema
- `config/import.php` — Mapa país→URL con `array_filter` (auto-detecta países configurados)
- `app/Models/ImportJob.php` — Tracking de progreso (pending/processing/completed/failed)
- `app/Jobs/ImportListingsJob.php` — Job async: crea listings + descarga imágenes
- `app/Http/Controllers/ImportController.php` — trigger / status / latest
- `resources/lang/es/import.php` + `en/import.php` — Traducciones
- Migraciones: `external_id` + `source` en `property_listings`, tabla `import_jobs`

### Importación de Solicitudes/Requests desde línea de comandos
Comando para importar solicitudes del proyecto legacy desde un archivo JSON exportado de PHPMyAdmin:
```bash
php artisan import:legacy-requests docs/request_legacy/{ISO2}.json
php artisan import:legacy-requests docs/request_legacy/CL.json --limit=20 --dry-run
php artisan import:legacy-requests docs/request_legacy/CL.json --skip-embeddings
php artisan import:legacy-requests docs/request_legacy/CL.json --only-embeddings
```

**Archivo**: `app/Console/Commands/ImportLegacyRequests.php`

**Opciones**:
- `--user=1` — ID del usuario al que se asociarán las solicitudes (default: 1)
- `--limit=N` — Importar solo los primeros N registros (útil para pruebas)
- `--dry-run` — Simula sin guardar en BD (muestra reporte de mapeos y errores)
- `--skip-embeddings` — Omite la generación de embeddings (Fase 2)
- `--only-embeddings` — Solo genera embeddings para registros ya importados sin embedding
- `--chunk=50` — Registros por lote al generar embeddings

**Conversión provincia → región para Chile**: Al importar solicitudes de Chile (`CL.json`), el campo `provincia_inmueble` se convierte automáticamente a la Región correspondiente antes de guardarse en `state`. El mapa completo está en `chileProvinceToRegion()`. El mismo mapeo existe en `ImportListingsJob` para la importación de anuncios.

**Tipo no reconocido en Chile**: `"Agricola"` no está en los tipos configurados para CL — se aplica fallback al primer tipo disponible (Casa). No es un error bloqueante.

### Formato del endpoint legacy
```
GET /app/export-listings.php?email={email}
```
Respuesta esperada: `{"listings": [...]}` con campos: `id`, `title`, `description`, `property_type`, `transaction_type`, `price`, `currency`, `bedrooms`, `bathrooms`, `parking_spaces`, `area`, `lotsize`, `address`, `city`, `state`, `country`, `is_active`, `images[]`

⚠️ **Notas del endpoint**:
- `currency` puede venir como `"U$D"` en lugar de `"USD"` (normalizar al importar si se necesita)
- `description` puede contener entidades HTML (`&#128680;` etc.)
- `property_type` viene en español con mayúscula (`"Casa"`, `"Hacienda"`)

### Resetear importación (para re-importar desde cero)
```bash
php artisan tinker
App\Models\PropertyListing::where('source', 'legacy')->delete();
DB::table('import_jobs')->truncate();
exit
```

### Performance — PropertyMatchController
- `index()` usa una query JOIN SQL (sin pgvector), cacheada **1 hora** (`matches_index_{userId}`), con paginación en memoria (10/página via `LengthAwarePaginator`)
- `show()` usa pgvector completo, cacheado **1 hora** (`matches_listing_{listingId}`, `matches_listing_count_{listingId}`)
- El dashboard **no** calcula matches en tiempo real (se eliminó para evitar carga en cada visita)
- **Cache de conteos en `/property-listings`**: primer check `matches_listing_{id}` (1h), luego `matches_listing_count_{id}` (1h), luego SQL fallback. El SQL usa `LOWER()` para case-insensitivity y maneja `max_budget IS NULL`.
- **Invalidación de cache**: `PropertyListingObserver` limpia caches al actualizar/eliminar un anuncio. `PropertyRequestObserver` limpia `matches_listing_count_{id}`, `matches_listing_{id}` y `matches_index_{userId}` para todos los anuncios afectados cuando cambia una solicitud.

### Performance — Dashboard (/dashboard)

**Problema resuelto (Mayo 2026)**: El dashboard tardaba varios segundos en producción, especialmente al usar "Impersonate" en un usuario con muchos anuncios.

**Causa raíz original**: Las tablas `property_listings`, `property_requests` e `import_jobs` no tenían índice en `user_id`, causando full table scans en producción con miles de registros importados.

**Causa raíz adicional (Mayo 2026)**: Los conteos de matches en el dashboard iteraban en PHP (`->sum(fn($listing) => $service->countExact...($listing))`) ejecutando 1 query por anuncio/solicitud del usuario. Un usuario admin con cientos de requests del legacy causaba timeout de 30s.

**Solución implementada**:

1. **Cache de stats del dashboard** (`resources/themes/anchor/pages/dashboard/index.blade.php`):
   - Stats de conteo: TTL 300s — `dashboard_listings_{userId}`, `dashboard_requests_{userId}`, `dashboard_contacts_total_{userId}`, `dashboard_contacts_unseen_{userId}`
   - Import job: TTL 60s — `dashboard_import_{userId}`
   - Match counts: TTL 21600s (6h) — `dashboard_matches_inbound_{userId}`, `dashboard_matches_outbound_{userId}`

2. **Match counts con queries agregadas únicas** (no loop):
   - `$matchesInbound`: 1 `DB::table()->join()->count(DISTINCT ...)` — sin importar cuántos anuncios tenga el usuario
   - `$matchesOutbound`: 1 `DB::table()->join()->count(DISTINCT ...)` — sin importar cuántas solicitudes tenga
   - **No usa `PropertyMatchingService`** — queries directas con `DB::table()`

3. **Invalidación de cache en Observers**:
   - `PropertyListingObserver`: limpia `dashboard_listings_{userId}` en created/updated/deleted
   - `PropertyRequestObserver`: limpia `dashboard_requests_{userId}` en created/updated/deleted

4. **Índices de base de datos** (migración `2026_05_04_181549_add_user_id_indexes_to_dashboard_tables`):
   - `property_listings(user_id, is_active)` — composite para `WHERE user_id=X AND is_active=true`
   - `property_requests(user_id, is_active)` — ídem
   - `import_jobs(user_id, created_at)` — para `latest()->first()` por usuario

**Nota**: La lentitud con Impersonate era el mismo problema — al acceder al dashboard de un usuario diferente, todas sus cache keys están frías. Los índices resuelven la primera carga en frío.

### Deduplicación de Solicitudes Legacy (Mayo 2026)

Las solicitudes importadas del sitio legacy generan duplicados (misma persona, misma solicitud). Se identifican por `client_email`.

**Criterio de deduplicación**: `COALESCE(client_email, id::text)` — si la solicitud tiene email, se usa como clave; si no (solicitud creada por usuario real), el `id` garantiza unicidad.

**Implementado en**:
- `PropertyMatchController::index()` JOIN: `COUNT(DISTINCT COALESCE(client_email, id::text))`
- `PropertyMatchingService::countExactMatchesForListing()`: `->count(DB::raw('DISTINCT COALESCE(client_email, id::text)'))`
- `PropertyMatchingService::getExactMatchesForListing()`: `->orderByDesc('id')->get()->unique(fn($r) => $r->client_email ?? $r->id)`
- `PropertyMatchingService::getSemanticMatchesForListing()`: `->unique(fn($r) => $r->client_email ?? $r->id)` tras filtro
- `PropertyMatchingService::getAllScoredMatchesForListing()`: `->unique(fn($r) => $r->client_email ?? $r->id)` tras scoring (mantiene el de mayor score por email)
- Dashboard (`dashboard/index.blade.php`): `COUNT(DISTINCT COALESCE(client_email, id::text))` en queries agregadas

### ⚠️ Filtro Ciudad/Estado Obligatorio en Queries de Matching

**Invariante crítica**: toda query que cuente o busque solicitudes compatibles con un anuncio DEBE incluir el filtro de ciudad/estado, igual que `getExactMatchesForListing()`:

```sql
AND (property_requests.city IS NULL
     OR property_requests.city = property_listings.city
     OR property_requests.state = property_listings.state)
```

Sin este filtro, se cuentan solicitudes de otras ciudades que luego el score descarta, generando una diferencia importante entre el conteo del índice y el del detalle.

**Se aplica en**: dashboard inbound, dashboard outbound, `PropertyMatchController::index()`, `PropertyMatchingService::getExactMatchesForListing()`, `PropertyMatchingService::countExactMatchesForListing()`.

### Arquitectura de Matches por Nivel de Precisión

| Vista | Tecnología | Cache | Propósito |
|---|---|---|---|
| `/dashboard` cards | SQL JOIN agregado | 6h | Indicador rápido (sin timeout) |
| `/dashboard/matches` index | SQL JOIN paginado | 1h | Lista de anuncios con candidatos |
| `/dashboard/matches/{id}` show | pgvector completo + score | 1h | Matches reales con ranking |

**Diferencia aceptable**: el índice y dashboard muestran conteos SQL (sin filtro score>=50%), el detalle muestra el conteo real con pgvector. El índice siempre puede mostrar un número ligeramente mayor que el detalle.

---

## Sistema de Sitemaps (Abril 2026)

### Archivos
- **Controlador**: `app/Http/Controllers/SitemapController.php`
- **Vistas**: `resources/views/sitemap/` (index, pages, listings, profiles)
- **Rutas**: `routes/web.php` (sin prefijo de locale, al inicio del archivo)

### URLs
```
/sitemap.xml                          → índice dinámico (lista todos los hijos)
/sitemap-pages.xml                    → páginas estáticas
/sitemap-properties-{locale}-{N}.xml  → anuncios paginados (es/en, página 1, 2, ...)
/sitemap-properties-{locale}.xml      → 301 redirect a página 1 (compatibilidad)
/sitemap-listings-{locale}.xml        → páginas de listado SEO (país/operación/tipo/ciudad)
/sitemap-profiles.xml                 → perfiles de usuarios/inmobiliarias
```

### Paginación (límite Google: 50,000 URLs por archivo)
- `SITEMAP_PAGE_SIZE = 50000` definido como constante en el controlador
- `sitemap.xml` calcula dinámicamente cuántas páginas hay con `ceil(total / 50000)`
- Cada página usa `StreamedResponse` (no acumula en RAM): carga IDs con OFFSET/LIMIT, luego procesa en lotes de 200 con eager loading de `primaryImage`

### ⚠️ Notas importantes
- **Memory exhaustion**: El sitemap de propiedades usa streaming — nunca cargar todas las propiedades en un array. Si se agrega lógica nueva, mantener el patrón de `response()->stream()`
- **404 en producción**: Si las rutas de sitemap dan 404 después de deploy, ejecutar `php artisan optimize:clear` (cache de rutas desactualizada)
- **Imágenes**: Se incluye `<image:image>` solo con `primaryImage` (no todas las imágenes) para mantener el XML liviano

---

## Sistema de WhatsApp con Meta Business Suite (Marzo 2026)

### Objetivo
Enviar mensajes de WhatsApp a los usuarios usando la Meta Cloud API (WhatsApp Business Platform).
- Mensaje de bienvenida automático al registrarse
- Opt-in obligatorio en el formulario de registro
- Toggle en perfil para activar/desactivar notificaciones
- Canal de notificaciones Laravel reutilizable para mensajes futuros

### Arquitectura
```
Registro → event(Registered) → UserRegistered listener (queued) → WelcomeWhatsAppNotification → WhatsAppChannel → WhatsAppService → Meta Cloud API
```

### Archivos del Sistema
- **config/whatsapp.php** — Configuración completa (token, phone_number_id, templates)
- **app/Services/WhatsAppService.php** — Cliente HTTP para Meta Cloud API v19.0
  - `sendTemplate()`: Envía un template aprobado con parámetros variables
  - `sendText()`: Envía texto libre (solo válido dentro de ventana de 24h)
- **app/Channels/WhatsAppChannel.php** — Canal de notificaciones Laravel
  - Llama a `toWhatsApp()` en la notificación
  - Retorna array `['template', 'language', 'params']` o string de texto libre
- **app/Notifications/WelcomeWhatsAppNotification.php** — Notificación de bienvenida (ShouldQueue)
- **app/Listeners/UserRegistered.php** — Escucha `Registered`, envía bienvenida si `whatsapp_opt_in && movil`
- **app/Providers/AppServiceProvider.php** — Registra `Event::listen(Registered::class, UserRegistered::class)`

### Migración
- **`2026_03_16_194920_add_whatsapp_opt_in_to_users_table.php`**
- Campos agregados: `whatsapp_opt_in` (boolean, default false), `whatsapp_opt_in_at` (timestamp nullable)
- Modelo `User`: ambos campos en `$fillable` y `$casts`

### Formularios
- **signup.blade.php**: Checkbox `whatsapp_opt_in` obligatorio (`accepted` rule). Sin aceptarlo no se puede registrar.
- **settings/profile.blade.php**: Toggle Filament para activar/desactivar. Guarda `whatsapp_opt_in` y `whatsapp_opt_in_at`.

### Templates en Meta Business Suite
- Crear en [business.facebook.com](https://business.facebook.com) → WhatsApp Manager → Plantillas de mensajes
- Tipo: **Predeterminado** (no Flows ni llamadas)
- Variable en el cuerpo: `{{customer_name}}` (Meta la mapea al primer parámetro del array)
- Templates configurados:
  - `bienvenida` (idioma: `es`) — español
  - `welcome` (idioma: `en`) — inglés
- Meta puede reclasificar templates de "Utility" a "Marketing" si detecta contenido promocional

### Variables de Entorno (.env)
```bash
WHATSAPP_ENABLED=true
WHATSAPP_ACCESS_TOKEN=          # System User Token (permanente, NO el temporal de 24h)
WHATSAPP_PHONE_NUMBER_ID=       # ID del número en developers.facebook.com → API Setup
WHATSAPP_BUSINESS_ACCOUNT_ID=  # ID de la cuenta de negocio
WHATSAPP_API_VERSION=v19.0
WHATSAPP_WELCOME_TEMPLATE_ES=bienvenida
WHATSAPP_WELCOME_TEMPLATE_EN=welcome
WHATSAPP_WELCOME_LANGUAGE_ES=es   # Debe coincidir exactamente con el idioma elegido en Meta
WHATSAPP_WELCOME_LANGUAGE_EN=en
WHATSAPP_LOGGING=true
```

### ⚠️ Notas Importantes
- **Token permanente**: Usar System User Token (Business Settings → Usuarios del sistema → Generar token con permiso `whatsapp_business_messaging`). El Temporary Token expira en 24 horas.
- **Número de teléfono**: La API de Meta requiere el número SIN el `+`. El servicio lo quita automáticamente con `ltrim($to, '+')`.
- **Los mensajes se envían en background**: Requiere queue worker corriendo (`queue:work` o cron).
- **Código de idioma**: Debe coincidir exactamente con lo seleccionado al crear el template en Meta (`es`, `en`, `es_AR`, `en_US`, etc.).

### Cómo agregar una nueva notificación WhatsApp
```php
class MiNotificacion extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        // Verificar opt-in para mensajes de notificación/marketing
        if (!$notifiable->whatsapp_opt_in || empty($notifiable->movil)) {
            return ['mail']; // fallback a email
        }
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp($notifiable): array
    {
        return [
            'template' => 'nombre_template',
            'language' => $notifiable->locale === 'en' ? 'en' : 'es',
            'params'   => [$notifiable->name, 'otro parámetro'],
        ];
    }
}
```

---

## IndexNow para Bing Webmaster Tools (Abril 2026)

### Objetivo
Notificar automáticamente a Bing cada vez que se publica o reactiva un anuncio, para acelerar su indexación en el buscador.

### Arquitectura
```
Publicar anuncio → PropertyListingObserver::created() → PropertyListingCreated event
                → SubmitToIndexNow listener (queued) → IndexNowService → POST api.indexnow.org
```

### Archivos del Sistema
- **config/indexnow.php** — Configuración: enabled, api_key, host, endpoint, logging
- **app/Services/IndexNowService.php** — Cliente HTTP para la API de IndexNow
  - `submitUrls(array $urls): bool` — POST a `api.indexnow.org/indexnow`
  - Nunca lanza excepciones (errores → `Log::warning`)
- **app/Listeners/SubmitToIndexNow.php** — Listener en cola para `PropertyListingCreated`
  - Genera URLs para todos los locales (`es` y `en`) usando `SeoService::generatePropertyUrl()`
  - Envía ambas URLs en un solo POST
- **app/Observers/PropertyListingObserver.php** — Método `updated()` añadido:
  - Dispara el evento cuando `is_active` cambia de `false` a `true` (reactivación)
- **public/c3c2f7f6f33349cba5f743647871eb23.txt** — Archivo de verificación de Bing (clave como contenido)
- **tests/Feature/IndexNow/SubmitToIndexNowTest.php** — 12 tests cubriendo el servicio, listener y ruta

### Variables de Entorno (.env)
```bash
INDEXNOW_ENABLED=true
INDEXNOW_API_KEY=c3c2f7f6f33349cba5f743647871eb23   # hex 32-128 chars
INDEXNOW_HOST=bienesonline.ai
INDEXNOW_LOGGING=true
```

### Archivo de verificación
Bing verifica la propiedad accediendo a `https://{host}/{api_key}.txt`. El archivo estático en `public/` sirve directamente (el servidor web lo sirve antes que Laravel).

También existe una ruta dinámica en `routes/web.php` como fallback (`/{key}.txt`).

### Comportamiento esperado en Bing Webmaster Tools
Cada anuncio genera **dos URLs** (una por locale) que Bing registra por separado:
```
/es/{país}/{ciudad}/propiedad/{id}-{slug}
/en/{país}/{ciudad}/propiedad/{id}-{slug}
```
Esto **no es una duplicación** — son dos páginas reales con contenido en distintos idiomas.

### ⚠️ Notas importantes
- **Auto-discovery de eventos**: Laravel descubre automáticamente los listeners en `app/Listeners/` por el type-hint de `handle()`. **No** registrar el mismo listener también en `AppServiceProvider` con `Event::listen()` — causaría ejecución doble. Ver `php artisan event:list` para verificar.
- **Archivo de verificación**: El archivo `.txt` en `public/` debe tener como contenido exactamente la clave (sin salto de línea al final). Generarlo con: `php -r "echo bin2hex(random_bytes(16));"`
- **Respuestas 200 y 202** son ambas consideradas éxito por IndexNow

---

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- filament/filament (FILAMENT) - v4
- laravel/folio (FOLIO) - v1
- laravel/framework (LARAVEL) - v12
- laravel/pail (PAIL) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/socialite (SOCIALITE) - v5
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- laravel/boost (BOOST) - v2
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- rector/rector (RECTOR) - v2
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `folio-routing` — Use this skill for Laravel Folio file-based routing tasks: creating pages with `folio:page`, setting up route parameters or model binding in filenames like `[User].blade.php`, defining named routes with `name()`, applying middleware, debugging Folio 404s, or running `folio:list`. Also trigger when a user is choosing between Folio and web.php for a new page, or wants to add a new URL or page in a Folio-enabled project (`resources/views/pages`). Folio automatically maps Blade templates to routes. Do not trigger for Livewire component, Normal Routing, Standard controller routes, or API endpoints
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `livewire-development` — Use for any task or question involving Livewire. Activate if user mentions Livewire, wire: directives, or Livewire-specific concepts like wire:model, wire:click, invoke this skill. Covers building new components, debugging reactivity issues, real-time form validation, loading states, migrating from Livewire 2 to 3, converting component formats (SFC/MFC/class-based), and performance optimization. Do not use for non-Livewire reactive UI (React, Vue, Alpine-only, Inertia.js) or standard Laravel forms without Livewire.
- `volt-development` — Develops single-file Livewire components with Volt. Activates when creating Volt components, converting Livewire to Volt, working with @volt directive, functional or class-based Volt APIs; or when the user mentions Volt, single-file components, functional Livewire, or inline component logic in Blade files.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit) or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for editing factories, seeders, migrations, controllers, models, or non-test PHP code.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
