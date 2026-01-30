# Notas sobre Laravel Folio e i18n

## Problema Identificado

Laravel Folio maneja las rutas automáticamente basándose en la estructura de archivos en `resources/themes/anchor/pages/`. Sin embargo, **Folio no soporta nativamente el prefijo `{locale}`** que implementamos para i18n.

## Solución Temporal Implementada

Para la página home (`index.blade.php`), creamos una ruta manual en `routes/web.php`:

```php
Route::prefix('{locale}')->where(['locale' => 'es|en'])->group(function () {
    Route::get('/', function () {
        $seo = [
            'title' => setting('site.title', 'Raxta - Plataforma Inmobiliaria Inteligente'),
            'description' => setting('site.description', 'Conectamos propiedades con compradores y agentes de forma inteligente.'),
            'image' => url('/og_image.png'),
            'type' => 'website'
        ];
        return view('theme::pages.index', compact('seo'));
    })->name('home');
});
```

## Páginas Folio Identificadas

Las siguientes páginas usan Folio y necesitarán el mismo tratamiento:

- ✅ `index.blade.php` (home) - **SOLUCIONADO**
- ⚠️ `blog/index.blade.php`
- ⚠️ `changelog/index.blade.php`
- ⚠️ `pricing/index.blade.php`
- ⚠️ `profile/[username].blade.php`
- ⚠️ `settings/*.blade.php`
- ⚠️ `signup.blade.php`
- ⚠️ Otras páginas de Wave que usen Folio

## Soluciones Futuras

### Opción 1: Rutas Manuales (Actual)
Crear rutas manuales para cada página Folio dentro del grupo `{locale}`.

**Ventajas:**
- Control total sobre las rutas
- Fácil de implementar

**Desventajas:**
- Hay que crear una ruta por cada página Folio
- Pierde la magia de Folio

### Opción 2: Middleware de Folio Personalizado
Crear un middleware que intercepte las rutas de Folio y agregue el prefijo de locale automáticamente.

**Ventajas:**
- Mantiene la funcionalidad de Folio
- Solución centralizada

**Desventajas:**
- Más complejo de implementar
- Requiere entender internamente cómo funciona Folio

### Opción 3: Configurar Folio con Prefijo
Investigar si Folio permite configurar un prefijo global en su configuración.

## Recomendación

Para el plan de 12 días actual, **continuar con rutas manuales** para las páginas críticas que se vayan necesitando. 

En una fase posterior (post Día 12), evaluar implementar una solución más robusta con middleware personalizado.

## Páginas a Actualizar en Días Futuros

- **Día 6-8 (Vistas)**: Al trabajar en las vistas, identificar qué páginas Folio se están usando y crear rutas manuales según sea necesario
- **Post Día 12**: Evaluar solución permanente para Folio

## Ejemplo de Ruta Manual para Otras Páginas

```php
// Pricing
Route::get('/pricing', function () {
    return view('theme::pages.pricing.index');
})->name('pricing');

// Blog
Route::get('/blog', function () {
    return view('theme::pages.blog.index');
})->name('blog');

// Changelog
Route::get('/changelog', function () {
    return view('theme::pages.changelog.index');
})->name('changelog');
```

## Estado Actual

- ✅ Home funcionando con ruta manual
- ✅ Language switcher funcionando correctamente
- ⚠️ Otras páginas Folio pendientes según se necesiten

---

**Fecha:** 2025-11-19  
**Día del Plan:** 1  
**Estado:** Documentado para referencia futura
