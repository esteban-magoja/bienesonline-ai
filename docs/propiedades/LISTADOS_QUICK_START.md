# Quick Start - Sistema de Listados Públicos

**Última actualización:** 5 de febrero de 2026

---

## 🎯 Lo Esencial

Sistema de listados públicos con URLs SEO-friendly completamente funcional.

### URLs que Funcionan
```
/es/argentina
/es/argentina/venta
/es/argentina/venta/casas
/en/argentina/sale/houses
```

---

## 📁 Archivos Clave

| Archivo | Función |
|---------|---------|
| `app/Helpers/PropertySlugHelper.php` | Validación y mapeo i18n |
| `app/Http/Controllers/PropertyListingController.php` | Controlador principal |
| `resources/views/property-listing.blade.php` | Vista (NO en themes/) |
| `routes/web.php` | Ruta catch-all al final del grupo locale |
| `resources/lang/{es,en}/properties.php` | Traducciones |

---

## ⚠️ Errores Comunes y Soluciones

### Error: "Route [wave.home] not defined"
```php
// ❌ INCORRECTO
route('wave.home', ['locale' => $locale])

// ✅ CORRECTO
route('home', ['locale' => $locale])
```

### Error: "column covered_area does not exist"
```php
// ❌ INCORRECTO
->where('covered_area', '>=', $value)

// ✅ CORRECTO
->where('area', '>=', $value)
```

### Error 404 en URLs con parámetros
**Causa:** Slugs españoles no mapean a BD (valores en inglés)  
**Solución:** Ya implementado en `validateTransactionType()` y `validatePropertyType()`

### Error: "View [property-listing] not found"
```bash
# ✅ Ubicación correcta
resources/views/property-listing.blade.php

# ❌ Ubicación incorrecta
resources/themes/anchor/pages/property-listing.blade.php
```

---

## 🔧 Comandos Rápidos

### Después de cambios en código:
```bash
composer dump-autoload -o
php artisan optimize:clear
```

### Deploy a producción:
```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Probar URLs:
```bash
curl -s http://127.0.0.1:8000/es/argentina | grep "<title>"
curl -s http://127.0.0.1:8000/es/argentina/venta/casas | grep "hreflang"
```

---

## 🗺️ Mapeo i18n

| Español | Inglés (BD) |
|---------|-------------|
| venta | sale |
| alquiler | rent |
| casas | house |
| departamentos | apartment |
| oficinas | office |
| locales | commercial |
| terrenos | land |

**Ver completo en:** `app/Helpers/PropertySlugHelper.php` líneas 62-130

---

## 📊 Checklist de Verificación Rápida

```bash
# 1. URLs funcionan
✓ /es/argentina
✓ /es/argentina/venta
✓ /es/argentina/venta/casas

# 2. SEO incluye
✓ Canonical URL
✓ Hreflang (es, en, x-default)
✓ Open Graph tags

# 3. Funcionalidades
✓ Filtros mantienen valores
✓ Ordenamiento funciona
✓ Paginación con query string
✓ Breadcrumbs traducidos
✓ Lazy loading imágenes
```

---

## 📚 Documentación Completa

Ver: `SISTEMA_LISTADOS_PUBLICOS.md` (documentación detallada con todos los problemas y soluciones)

---

## 🚀 Para Continuar Mañana

1. **Verificar que el servidor esté corriendo:**
   ```bash
   php artisan serve
   ```

2. **Probar una URL:**
   ```bash
   curl http://127.0.0.1:8000/es/argentina
   ```

3. **Si hay problemas, limpiar caches:**
   ```bash
   php artisan optimize:clear
   ```

4. **Revisar logs si algo falla:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

**Estado:** ✅ Sistema completo y funcional  
**Próximos pasos opcionales:** Cache, índices BD, sitemap (ver doc completa)
