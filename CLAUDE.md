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