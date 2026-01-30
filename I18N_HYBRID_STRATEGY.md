# Estrategia Híbrida de Localización (i18n)

**Fecha de implementación:** 2025-11-20  
**Día del plan:** 2  
**Estado:** ✅ Implementado y funcionando

---

## 📋 Decisión Arquitectónica

Durante la implementación del **Día 2**, se tomó la decisión estratégica de usar un **enfoque híbrido** para la gestión de idiomas:

### Rutas Públicas: Prefijo `{locale}` en URL
- **Ámbito:** Todas las páginas públicas (búsqueda, detalle de propiedad, home, etc.)
- **Formato:** `/es/search-properties`, `/en/property/123`
- **Razón:** **SEO** - Google indexa mejor con URLs diferentes por idioma

### Rutas Privadas: Locale en Sesión
- **Ámbito:** Todo el dashboard (`/dashboard/*`) y funciones privadas
- **Formato:** `/dashboard/requests` (sin prefijo)
- **Razón:** **UX** - Evita complejidad innecesaria para usuarios autenticados

---

## 🏗️ Implementación Técnica

### 1. Middleware `SetLocale`

Detecta el locale con la siguiente **prioridad**:

```
1. URL ({locale} parámetro de ruta)  → Rutas públicas
2. Sesión (session('locale'))         → Rutas privadas  
3. Header Accept-Language             → Primera visita
4. Config default (español)           → Fallback
```

**Ubicación:** `app/Http/Middleware/SetLocale.php`

### 2. Estructura de Rutas (`routes/web.php`)

```php
// ============================================================================
// 1. REDIRECT RAÍZ AL LOCALE POR DEFECTO
// ============================================================================
Route::get('/', function () {
    $locale = session('locale', config('locales.default', 'es'));
    return redirect("/{$locale}");
});

// ============================================================================
// 2. RUTAS PÚBLICAS CON PREFIJO {locale} (para SEO)
// ============================================================================
Route::prefix('{locale}')->where(['locale' => 'es|en'])->group(function () {
    Route::get('/', ...)->name('home');
    Route::get('/search-properties', ...)->name('property.search');
    Route::get('/property/{id}', ...)->name('property.show');
    // ... más rutas públicas
});

// ============================================================================
// 3. RUTA PARA CAMBIAR LOCALE (guarda en sesión)
// ============================================================================
Route::post('/locale/switch', function(Request $request) {
    $locale = $request->input('locale', 'es');
    if (in_array($locale, ['es', 'en'])) {
        session(['locale' => $locale]);
    }
    return back();
})->name('locale.switch');

// ============================================================================
// 4. RUTAS PRIVADAS SIN PREFIJO (usan locale de sesión)
// ============================================================================
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', ...)->name('dashboard');
    Route::resource('/dashboard/requests', PropertyRequestController::class);
    Route::resource('/dashboard/matches', PropertyMatchController::class);
    // ... más rutas privadas
});
```

### 3. Helper `route_localized()`

Genera rutas con o sin prefijo según corresponda:

```php
/**
 * Genera una ruta localizada automáticamente.
 * - Rutas públicas: incluye {locale} en URL
 * - Rutas privadas: usa locale de sesión
 */
function route_localized(string $name, array $parameters = [], bool $absolute = true): string
{
    // Rutas públicas que necesitan {locale}
    $publicRoutes = ['home', 'property.search', 'property.show', 'requests.search'];
    
    if (in_array($name, $publicRoutes)) {
        // Agregar locale a parámetros si no está presente
        if (!isset($parameters['locale'])) {
            $parameters['locale'] = app()->getLocale();
        }
    }
    
    return route($name, $parameters, $absolute);
}
```

**Ubicación:** `app/helpers.php`

---

## 📊 Comparación de Estrategias

| Aspecto | URLs Públicas | Dashboard Privado |
|---------|--------------|-------------------|
| **Formato URL** | `/es/property/123` | `/dashboard/requests` |
| **Locale en URL** | ✅ Sí (parámetro) | ❌ No |
| **Locale en Session** | ✅ Sí (guardado) | ✅ Sí (prioritario) |
| **SEO** | ⭐⭐⭐⭐⭐ Excelente | N/A (no indexable) |
| **UX** | ⭐⭐⭐⭐ Muy bueno | ⭐⭐⭐⭐⭐ Excelente |
| **Complejidad URLs** | Media | Baja |

---

## ✅ Ventajas de la Estrategia Híbrida

### Para Rutas Públicas (con `{locale}`)
1. **SEO optimizado:** Google indexa `/es/` y `/en/` por separado
2. **Hreflang tags:** Fácil implementar `<link rel="alternate" hreflang="es" href="/es/...">`
3. **Compartir enlaces:** Los usuarios comparten el idioma correcto
4. **Canonical URLs:** URLs únicas por idioma

### Para Dashboard (con sesión)
1. **URLs limpias:** `/dashboard/requests/create` es más simple que `/es/panel/solicitudes/crear`
2. **Mejor UX:** El usuario autenticado no necesita ver el idioma en cada URL
3. **Menos complejidad:** Formularios, AJAX, redirects son más simples
4. **Consistencia:** El dashboard mantiene el idioma durante toda la sesión

---

## 🎯 Ejemplos de Uso

### Enlace a Página Pública (con locale en URL)
```blade
{{-- En cualquier vista --}}
<a href="{{ route_localized('property.search') }}">
    {{ __('properties.search_properties') }}
</a>
{{-- Genera: /es/search-properties o /en/search-properties --}}
```

### Enlace a Dashboard (sin locale en URL)
```blade
{{-- En cualquier vista --}}
<a href="{{ route('dashboard.requests.create') }}">
    {{ __('dashboard.create_request') }}
</a>
{{-- Genera: /dashboard/requests/create (usa locale de sesión) --}}
```

### Cambiar Idioma (actualiza sesión)
```blade
{{-- Language Switcher Component --}}
<form action="{{ route('locale.switch') }}" method="POST">
    @csrf
    <input type="hidden" name="locale" value="en">
    <button type="submit">English</button>
</form>
{{-- Actualiza session('locale') y redirige back() --}}
```

---

## 🔧 Configuración Requerida

### 1. Archivo `config/locales.php`
```php
return [
    'available' => ['es', 'en'],
    'default' => 'es',
    'fallback' => 'es',
    // ... más configuración
];
```

### 2. Middleware en `app/Http/Kernel.php`
```php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\SetLocale::class,
        // ... otros middleware
    ],
];
```

### 3. Helper registrado en `composer.json`
```json
"autoload": {
    "files": [
        "app/helpers.php"
    ]
}
```

---

## 🐛 Problemas Conocidos y Soluciones

### Problema 1: Logout redirige sin locale
**Síntoma:** Al hacer logout, redirige a `/` en vez de `/es/`

**Solución:** Guardar locale antes de invalidar sesión
```php
// En AuthController o logout handler
$locale = session('locale', 'es');
Auth::logout();
$request->session()->invalidate();
session(['locale' => $locale]); // Restaurar locale
return redirect("/{$locale}");
```

### Problema 2: Laravel Folio y rutas con {locale}
**Síntoma:** Folio no soporta prefijos dinámicos como `{locale}`

**Solución:** Crear rutas manuales para páginas Folio críticas
```php
Route::prefix('{locale}')->group(function () {
    Route::get('/', function () {
        return view('theme::pages.index', compact('seo'));
    })->name('home');
});
```

Ver más detalles en: [FOLIO_I18N_NOTES.md](FOLIO_I18N_NOTES.md)

### Problema 3: Enlaces en sidebar del dashboard
**Síntoma:** Links del sidebar generan URLs con `/es/dashboard/...`

**Solución:** Usar `route()` directo en lugar de `route_localized()` para rutas privadas
```blade
{{-- ✅ Correcto --}}
<a href="{{ route('dashboard.requests.index') }}">Solicitudes</a>

{{-- ❌ Incorrecto --}}
<a href="{{ route_localized('dashboard.requests.index') }}">Solicitudes</a>
```

---

## 📚 Referencias

- **Implementación:** Commit `ad5a067` - "Implemented hybrid strategy"
- **Middleware:** `app/Http/Middleware/SetLocale.php`
- **Rutas:** `routes/web.php` (ver secciones 2 y 4)
- **Helper:** `app/helpers.php` → función `route_localized()`
- **Folio:** [FOLIO_I18N_NOTES.md](FOLIO_I18N_NOTES.md)

---

## 🚀 Próximos Pasos

Esta estrategia híbrida ya está **completamente implementada** y funcionando. 

Para los próximos días del plan:
- ✅ **Día 3-5:** Usar `route_localized()` solo para rutas públicas
- ✅ **Día 6-8:** En vistas, distinguir entre enlaces públicos y privados
- ✅ **Día 9:** SEO tags con hreflang funcionan gracias a URLs con `{locale}`
- ✅ **Día 10:** Emails usan `session('locale')` del usuario autenticado

**No se requieren más cambios arquitectónicos.**

---

**Documentado por:** Sistema  
**Última actualización:** 2025-11-20  
**Versión:** 1.0
