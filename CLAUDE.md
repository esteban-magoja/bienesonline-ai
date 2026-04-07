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
- `index()` y `show()` están cacheados 15 minutos (`matches_index_{userId}`, `matches_listing_{listingId}`)
- `index()` limita a 10 anuncios para evitar N búsquedas vectoriales en una sola carga
- El dashboard **no** calcula matches en tiempo real (se eliminó para evitar carga en cada visita)

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.18
- filament/filament (FILAMENT) - v4
- laravel/folio (FOLIO) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/socialite (SOCIALITE) - v5
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- rector/rector (RECTOR) - v2
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.


=== folio/core rules ===

## Laravel Folio

- Laravel Folio is a file based router. With Laravel Folio, a new route is created for every Blade file within the configured Folio directory. For example, pages are usually in in `resources/views/pages/` and the file structure determines routes:
    - `pages/index.blade.php` → `/`
    - `pages/profile/index.blade.php` → `/profile`
    - `pages/auth/login.blade.php` → `/auth/login`
- You may list available Folio routes using `php artisan folio:list`  or using Boost's `list-routes` tool.

### New Pages & Routes
- Always create new `folio` pages and routes using `php artisan folio:page [name]` following existing naming conventions.

<code-snippet name="Example folio:page Commands for Automatic Routing" lang="shell">
    // Creates: resources/views/pages/products.blade.php → /products
    php artisan folio:page "products"

    // Creates: resources/views/pages/products/[id].blade.php → /products/{id}
    php artisan folio:page "products/[id]"
</code-snippet>

- Add a 'name' to each new Folio page at the very top of the file so it has a named route available for other parts of the codebase to use.


<code-snippet name="Adding named route to Folio page" lang="php">
use function Laravel\Folio\name;

name('products.index');
</code-snippet>


### Support & Documentation
- Folio supports: middleware, serving pages from multiple paths, subdomain routing, named routes, nested routes, index routes, route parameters, and route model binding.
- If available, use Boost's `search-docs` tool to use Folio to its full potential and help the user effectively.


<code-snippet name="Folio Middleware Example" lang="php">
use function Laravel\Folio\{name, middleware};

name('admin.products');
middleware(['auth', 'verified', 'can:manage-products']);
?>
</code-snippet>


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== volt/core rules ===

## Livewire Volt

- This project uses Livewire Volt for interactivity within its pages. New pages requiring interactivity must also use Livewire Volt. There is documentation available for it.
- Make new Volt components using `php artisan make:volt [name] [--test] [--pest]`
- Volt is a **class-based** and **functional** API for Livewire that supports single-file components, allowing a component's PHP logic and Blade templates to co-exist in the same file
- Livewire Volt allows PHP logic and Blade templates in one file. Components use the `@volt` directive.
- You must check existing Volt components to determine if they're functional or class based. If you can't detect that, ask the user which they prefer before writing a Volt component.

### Volt Functional Component Example

<code-snippet name="Volt Functional Component Example" lang="php">
@volt
<?php
use function Livewire\Volt\{state, computed};

state(['count' => 0]);

$increment = fn () => $this->count++;
$decrement = fn () => $this->count--;

$double = computed(fn () => $this->count * 2);
?>

<div>
    <h1>Count: {{ $count }}</h1>
    <h2>Double: {{ $this->double }}</h2>
    <button wire:click="increment">+</button>
    <button wire:click="decrement">-</button>
</div>
@endvolt
</code-snippet>


### Volt Class Based Component Example
To get started, define an anonymous class that extends Livewire\Volt\Component. Within the class, you may utilize all of the features of Livewire using traditional Livewire syntax:


<code-snippet name="Volt Class-based Volt Component Example" lang="php">
use Livewire\Volt\Component;

new class extends Component {
    public $count = 0;

    public function increment()
    {
        $this->count++;
    }
} ?>

<div>
    <h1>{{ $count }}</h1>
    <button wire:click="increment">+</button>
</div>
</code-snippet>


### Testing Volt & Volt Components
- Use the existing directory for tests if it already exists. Otherwise, fallback to `tests/Feature/Volt`.

<code-snippet name="Livewire Test Example" lang="php">
use Livewire\Volt\Volt;

test('counter increments', function () {
    Volt::test('counter')
        ->assertSee('Count: 0')
        ->call('increment')
        ->assertSee('Count: 1');
});
</code-snippet>


<code-snippet name="Volt Component Test Using Pest" lang="php">
declare(strict_types=1);

use App\Models\{User, Product};
use Livewire\Volt\Volt;

test('product form creates product', function () {
    $user = User::factory()->create();

    Volt::test('pages.products.create')
        ->actingAs($user)
        ->set('form.name', 'Test Product')
        ->set('form.description', 'Test Description')
        ->set('form.price', 99.99)
        ->call('create')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Test Product')->exists())->toBeTrue();
});
</code-snippet>


### Common Patterns


<code-snippet name="CRUD With Volt" lang="php">
<?php

use App\Models\Product;
use function Livewire\Volt\{state, computed};

state(['editing' => null, 'search' => '']);

$products = computed(fn() => Product::when($this->search,
    fn($q) => $q->where('name', 'like', "%{$this->search}%")
)->get());

$edit = fn(Product $product) => $this->editing = $product->id;
$delete = fn(Product $product) => $product->delete();

?>

<!-- HTML / UI Here -->
</code-snippet>

<code-snippet name="Real-Time Search With Volt" lang="php">
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search..."
    />
</code-snippet>

<code-snippet name="Loading States With Volt" lang="php">
    <flux:button wire:click="save" wire:loading.attr="disabled">
        <span wire:loading.remove>Save</span>
        <span wire:loading>Saving...</span>
    </flux:button>
</code-snippet>


=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
