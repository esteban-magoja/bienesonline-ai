# Sistema de Listados Públicos con URLs SEO - Documentación

**Fecha de implementación:** 5 de febrero de 2026  
**Sesión de trabajo:** Sistema completo de listados públicos con URLs amigables

---

## 📋 Resumen de la Implementación

Se implementó un sistema completo de listados de propiedades con URLs SEO-friendly que sigue una jerarquía geográfica y funcional. El sistema es **100% dinámico**, validando todo contra la base de datos usando consultas DISTINCT.

### Estructura de URLs Implementada

```
/{locale}/{país}/{operación?}/{tipo?}/{estado?}/{ciudad?}

Ejemplos reales funcionando:
- /es/argentina
- /es/argentina/venta
- /es/argentina/venta/casas
- /en/argentina/sale/houses
```

---

## 🗂️ Archivos Creados y Modificados

### 1. Helper Principal
**Archivo:** `app/Helpers/PropertySlugHelper.php`

**Funciones clave:**
- `normalize()` - Normaliza texto a slug
- `validateCountry()` - Valida país en BD
- `validateState()` - Valida estado/provincia en BD
- `validateCity()` - Valida ciudad en BD
- `validateTransactionType()` - Valida operación (con mapeo i18n)
- `validatePropertyType()` - Valida tipo de propiedad (con mapeo i18n)
- `detectSlugType()` - Detecta automáticamente tipo de parámetro
- `generateBreadcrumbs()` - Genera breadcrumbs dinámicos

**Mapeos i18n implementados:**
```php
// Transacciones
'venta' => 'sale'
'alquiler' => 'rent'
'alquiler-temporal' => 'temporary_rent'

// Propiedades
'casas' => 'house'
'departamentos' => 'apartment'
'oficinas' => 'office'
'locales' => 'commercial'
'terrenos' => 'land'
'campos' => 'field'
'fincas' => 'farm'
'galpones' => 'warehouse'
```

### 2. Controlador
**Archivo:** `app/Http/Controllers/PropertyListingController.php`

**Método principal:**
```php
public function index(Request $request, string $locale, string $country, string $params = null)
```

**Características:**
- Parsea parámetros múltiples dividiendo por `/`
- Detecta automáticamente tipo de cada parámetro
- Construye query dinámico según filtros
- Aplica filtros de sidebar (precio, habitaciones, etc.)
- 7 opciones de ordenamiento
- Genera SEO completo (canonical, hreflang, OG)
- Paginación con `withQueryString()`

**⚠️ IMPORTANTE:**
- El parámetro `$params` se recibe como string completo: `"venta/casas"`
- Se divide con: `explode('/', trim($params, '/'))`
- Los slugs se mapean de español/inglés a valores de BD

### 3. Rutas
**Archivo:** `routes/web.php`

**Ruta agregada dentro del grupo `{locale}`:**
```php
Route::get('/{country}/{params?}', [PropertyListingController::class, 'index'])
    ->where(['country' => '[a-z\-]+', 'params' => '.*'])
    ->name('property.listings');
```

**⚠️ UBICACIÓN CRÍTICA:**
Esta ruta DEBE estar al FINAL del grupo de locale para evitar conflictos con otras rutas como `/search-properties`, `/property/{id}`, etc.

### 4. Vista Principal
**Archivo:** `resources/views/property-listing.blade.php`

**Ubicación correcta:** `resources/views/` NO `resources/themes/anchor/pages/`

**Componentes:**
- Hero section con título dinámico
- Breadcrumbs traducidos
- Sidebar de filtros (sticky)
- Grid responsive de propiedades (2 columnas en desktop)
- Paginación Tailwind
- SEO tags completos

**⚠️ IMPORTANTE:**
- Usa layout: `<x-layouts.marketing :seo="$seo">`
- Imágenes con `loading="lazy"` para optimización
- Filtros en formulario GET para mantener estado

### 5. Traducciones
**Archivos:** 
- `resources/lang/es/properties.php`
- `resources/lang/en/properties.php`

**Claves agregadas:**
```php
// Listados públicos
'properties' => 'Propiedades',
'all_properties' => 'Todas las Propiedades',
'browse_properties' => 'Explorar Propiedades',
'filters' => 'Filtros',
'apply_filters' => 'Aplicar Filtros',

// Ordenamiento
'sort' => [
    'featured' => 'Destacados',
    'newest' => 'Más Recientes',
    'price_asc' => 'Precio: Menor a Mayor',
    // ... etc
],

// Filtros
'filters_label' => [
    'price_range' => 'Rango de Precio',
    'min_bedrooms' => 'Habitaciones Mínimas',
    // ... etc
],

// Tipos traducidos para URLs
'house' => 'casa',
'houses' => 'casas',
'sale' => 'venta',
'rent' => 'alquiler',
// ... etc
```

---

## 🔧 Problemas Encontrados y Soluciones

### Problema 1: Error "Route [wave.home] not defined"
**Causa:** Breadcrumbs usaban `route('wave.home')` que no existe  
**Solución:** Cambiar a `route('home', ['locale' => $locale])`  
**Archivo:** `app/Helpers/PropertySlugHelper.php` línea 176

### Problema 2: Error "column covered_area does not exist"
**Causa:** La columna en BD se llama `area`, no `covered_area`  
**Solución:** Reemplazar todas las referencias a `covered_area` por `area`  
**Archivos afectados:** `PropertyListingController.php` (filtros y ordenamiento)

### Problema 3: Error 404 en URLs con múltiples parámetros
**Causa 1:** Parámetros no se dividían (recibidos como string único)  
**Solución:** Usar `explode('/', $params)` en el controlador

**Causa 2:** Slugs en español no mapeaban a valores en inglés de la BD  
**Solución:** Implementar mapas de traducción en los métodos `validate*Type()`

### Problema 4: Vista no encontrada "theme.pages.property-listing"
**Causa:** Las vistas están en `resources/views/` NO en `resources/themes/`  
**Solución:** Cambiar `return view('theme.pages.property-listing')` a `return view('property-listing')`  
**Ubicación final:** `resources/views/property-listing.blade.php`

### Problema 5: Meta tags SEO no aparecían
**Causa:** El formato del array `$seo` no coincidía con el layout  
**Solución:** Usar el formato correcto:
```php
[
    'title' => $title,
    'description' => $description,
    'image' => $image,
    'type' => 'website',
    'canonical' => $url,
    'hreflang_tags' => [
        ['rel' => 'alternate', 'hreflang' => 'es', 'href' => $url_es],
        ['rel' => 'alternate', 'hreflang' => 'en', 'href' => $url_en],
    ],
]
```

---

## ⚠️ Notas Críticas para Evitar Errores

### 1. Ubicación de Archivos
```
✅ CORRECTO:
- Vistas Blade: resources/views/
- Controladores: app/Http/Controllers/
- Helpers: app/Helpers/
- Traducciones: resources/lang/{locale}/

❌ INCORRECTO:
- NO poner vistas en resources/themes/anchor/pages/
```

### 2. Nombres de Columnas en BD
```php
// Tabla property_listings tiene:
'area'              // NO 'covered_area'
'parking_spaces'    // NO 'garages'
'bedrooms'          // OK
'bathrooms'         // OK
'transaction_type'  // valores en inglés: 'sale', 'rent'
'property_type'     // valores en inglés: 'house', 'apartment'
```

### 3. Autoload de Composer
Después de crear el Helper:
```bash
composer dump-autoload -o
```

### 4. Limpiar Caches
Después de cambios en rutas, vistas o config:
```bash
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

### 5. Orden de Rutas
La ruta `/{country}/{params?}` DEBE ir al final del grupo `{locale}` en `web.php`:
```php
Route::prefix('{locale}')->group(function () {
    // ... otras rutas primero
    Route::get('/search-properties', ...);
    Route::get('/property/{id}', ...);
    
    // Ruta catch-all AL FINAL
    Route::get('/{country}/{params?}', [PropertyListingController::class, 'index']);
});
```

### 6. Mapeo i18n
Los slugs de URL deben mapearse a valores de BD:
- URL: `/es/argentina/venta` → Query: `transaction_type = 'sale'`
- URL: `/es/argentina/venta/casas` → Query: `property_type = 'house'`

---

## 🚀 Comandos para Deploy

```bash
# 1. Actualizar autoload
composer dump-autoload -o

# 2. Limpiar caches
php artisan optimize:clear

# 3. Cachear para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Optimización general
php artisan optimize
```

---

## 📊 Estado Actual del Sistema

### Funcionalidades Completas ✅
- [x] Helper con validación dinámica
- [x] Mapeo i18n (español ↔ inglés)
- [x] Controlador con parseo inteligente
- [x] Detección automática de tipo de parámetro
- [x] Filtros: precio, habitaciones, baños, área, cocheras
- [x] 7 opciones de ordenamiento
- [x] Paginación con query string
- [x] Breadcrumbs dinámicos traducidos
- [x] Vista responsive (mobile, tablet, desktop)
- [x] SEO completo (canonical, hreflang, OG, Twitter)
- [x] Lazy loading de imágenes
- [x] Manejo de errores 404

### URLs Probadas y Funcionando ✅
```
✓ /es/argentina (200)
✓ /es/argentina/venta (200)
✓ /es/argentina/venta/casas (200)
✓ /en/argentina (200)
✓ /en/argentina/sale (200)
✓ /en/argentina/sale/houses (200)
```

### SEO Verificado ✅
- Canonical URL: ✓
- Hreflang tags: 3 (es, en, x-default)
- Open Graph tags: 6
- Twitter Cards: ✓
- Lazy loading: ✓

---

## 🔄 Próximos Pasos Opcionales

### Mejoras Futuras (No Urgentes)
1. **Cache de queries frecuentes**
   - Cachear resultados de países/estados más visitados
   - TTL: 1 hora
   - Implementar en `PropertySlugHelper`

2. **Índices de BD** (si performance es lenta)
   ```sql
   CREATE INDEX idx_active_country ON property_listings(is_active, country);
   CREATE INDEX idx_transaction_type ON property_listings(transaction_type);
   CREATE INDEX idx_property_type ON property_listings(property_type);
   ```

3. **Sitemap XML**
   - Generar URLs de países/estados principales
   - Actualizar `SitemapController`

4. **Componentes reutilizables**
   - Extraer breadcrumbs a componente Blade
   - Extraer sidebar de filtros a componente
   - Extraer card de propiedad a componente

5. **Testing automatizado**
   - Test de rutas con múltiples parámetros
   - Test de mapeo i18n
   - Test de generación de breadcrumbs

---

## 🐛 Debugging

### Ver qué slug se está validando:
```php
// En PropertySlugHelper
dd($slug, $country, $dbValue);
```

### Ver qué query se está ejecutando:
```php
// En PropertyListingController
dd($query->toSql(), $query->getBindings());
```

### Ver estructura de $seo:
```php
// En la vista
@php dd($seo); @endphp
```

### Probar URLs manualmente:
```bash
curl -s http://127.0.0.1:8000/es/argentina/venta/casas | grep "<title>"
```

---

## 📞 Contacto y Referencias

### Documentación relacionada:
- `CLAUDE.md` - Guías generales del proyecto
- `I18N_INDEX.md` - Sistema de internacionalización
- `SISTEMA_SOLICITUDES.md` - Sistema de solicitudes de propiedades

### Plan original:
- Ubicación: `/home/esteban/.copilot/session-state/7f898bb0-487c-49c9-8e78-cc75eb2d4797/plan.md`
- Todas las 8 fases fueron completadas

---

## ✅ Checklist de Verificación

Antes de continuar en otra sesión, verificar:

- [ ] Las URLs básicas funcionan (`/es/argentina`, `/es/argentina/venta`)
- [ ] El mapeo i18n funciona (slugs en español → valores en inglés)
- [ ] Los filtros mantienen sus valores al aplicar
- [ ] La paginación incluye query params
- [ ] Las traducciones se muestran correctamente
- [ ] Los breadcrumbs se generan bien
- [ ] El SEO incluye canonical y hreflang
- [ ] Las imágenes tienen lazy loading

---

**Última actualización:** 5 de febrero de 2026  
**Estado:** Sistema completo y funcional ✅
