# I18N Troubleshooting Guide

Problemas comunes y sus soluciones durante la implementación de internacionalización.

---

## 🔴 Problema: Traducciones no se cargan (keys literales en vista)

### Síntomas
- Al visitar una página, se ven las keys de traducción en lugar del texto traducido
- Ejemplo: `messages.home` en lugar de "Home" o "Inicio"
- Ejemplo: `properties.contact_advertiser` en lugar de "Contact Advertiser"
- Las traducciones existen en los archivos `.php` pero no se muestran

### Causa Raíz
**Laravel busca traducciones en un directorio diferente al que estamos editando.**

Wave usa la estructura antigua de Laravel (pre-11):
- **Laravel lee de**: `/resources/lang/`
- **Nosotros editábamos**: `/lang/`

### Diagnóstico
```bash
# 1. Verificar dónde busca Laravel
php artisan tinker --execute="echo app()->langPath();"
# Output esperado para Wave: /var/www/html/wave/resources/lang

# 2. Verificar que las traducciones existan en el archivo
php -r '$data = require "lang/en/properties.php"; echo $data["contact_advertiser"] ?? "NOT FOUND";'

# 3. Probar si Laravel puede cargar las traducciones
php artisan tinker --execute="app()->setLocale('en'); echo trans('properties.contact_advertiser');"
# Si muestra la key literal, hay problema de ubicación
```

### Solución

**Opción A: Copiar archivos al directorio correcto (Recomendado)**
```bash
# Copiar todos los archivos de traducción actualizados
cp lang/en/*.php resources/lang/en/
cp lang/es/*.php resources/lang/es/

# Limpiar cache
php artisan optimize:clear
```

**Opción B: Cambiar la configuración de Laravel** (No recomendado para Wave)
```php
// En config/app.php o AppServiceProvider
app()->useLangPath(base_path('lang'));
```

### Prevención
**Durante el desarrollo i18n:**

1. **SIEMPRE editar archivos en**: `/resources/lang/`
2. **NO editar archivos en**: `/lang/` (a menos que cambies la config)
3. Después de cada edición:
   ```bash
   php artisan optimize:clear
   ```
4. Verificar con `trans()` en tinker antes de probar en navegador

### Archivos Afectados
```
✅ CORRECTO (Wave):
resources/lang/es/properties.php
resources/lang/es/messages.php
resources/lang/es/dashboard.php
resources/lang/es/seo.php
resources/lang/es/attributes.php
resources/lang/es/validation.php

resources/lang/en/properties.php
resources/lang/en/messages.php
resources/lang/en/dashboard.php
resources/lang/en/seo.php
resources/lang/en/attributes.php
resources/lang/en/validation.php

❌ INCORRECTO para Wave (pero existe por Laravel 11):
lang/es/*.php
lang/en/*.php
```

---

## 🟡 Problema: Traducciones funcionan en tinker pero no en navegador

### Síntomas
- `php artisan tinker` muestra las traducciones correctamente
- En el navegador se ven las keys literales

### Causas Posibles
1. **Cache de OPcache** (PHP)
2. **Cache del navegador**
3. **Servidor no reiniciado** después de cambios

### Solución
```bash
# 1. Resetear OPcache (si está habilitado)
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'; }"

# 2. Limpiar todo el cache de Laravel
php artisan optimize:clear

# 3. Reiniciar servidor de desarrollo
# Detener: Ctrl+C
php artisan serve

# 4. En el navegador: Ctrl+Shift+R (recarga forzada)
```

---

## 🟢 Problema: Traducciones con parámetros no funcionan

### Síntomas
```blade
{{ __('properties.whatsapp_message', ['property' => $property->title]) }}
```
Muestra: `Hello, I'm interested in the property: :property`

### Causa
Parámetro mal nombrado o sintaxis incorrecta

### Solución
```php
// ✅ CORRECTO - lang/en/properties.php
'whatsapp_message' => 'Hello, I\'m interested in the property: :property',

// ✅ CORRECTO - Blade
{{ __('properties.whatsapp_message', ['property' => $title]) }}

// ❌ INCORRECTO
'whatsapp_message' => 'Hello, I\'m interested in the property: {property}',
'whatsapp_message' => 'Hello, I\'m interested in the property: $property',
```

---

## 🔵 Problema: Algunas traducciones funcionan, otras no

### Síntomas
- `__('properties.types.house')` funciona
- `__('properties.contact_advertiser')` NO funciona

### Causa
Las nuevas traducciones están fuera del array principal

### Diagnóstico
```bash
# Verificar estructura del archivo
php -r '
$data = require "resources/lang/en/properties.php";
echo "Total keys: " . count($data) . "\n";
echo isset($data["contact_advertiser"]) ? "✓ Found" : "✗ Missing";
'
```

### Solución
Verificar que todas las traducciones estén **dentro** del `return [ ... ];`

```php
<?php

return [
    // Sección 1
    'key1' => 'Value 1',
    'key2' => 'Value 2',
    
    // Sección 2
    'key3' => 'Value 3',
    
    // TODAS las keys deben estar ANTES de este cierre
]; // ← Este es el ÚNICO cierre del return

// ❌ NADA debe estar aquí fuera
```

---

## 📋 Checklist de Verificación Rápida

Cuando las traducciones no funcionan:

- [ ] ✅ Archivos están en `/resources/lang/` (no en `/lang/`)
- [ ] ✅ Ejecuté `php artisan optimize:clear`
- [ ] ✅ Reinicié `php artisan serve`
- [ ] ✅ Recarga forzada en navegador (Ctrl+Shift+R)
- [ ] ✅ Sintaxis PHP correcta (`php -l resources/lang/en/properties.php`)
- [ ] ✅ Keys dentro del array de retorno
- [ ] ✅ Mismo número de keys en ES y EN
- [ ] ✅ Middleware `SetLocale` activo en ruta

---

## 🛠️ Comandos Útiles

```bash
# Ver dónde busca Laravel las traducciones
php artisan tinker --execute="echo app()->langPath();"

# Probar una traducción específica
php artisan tinker --execute="app()->setLocale('en'); echo trans('properties.contact_advertiser');"

# Contar traducciones en un archivo
php -r '$d = require "resources/lang/en/properties.php"; echo count($d);'

# Comparar keys entre idiomas
php -r '
$es = require "resources/lang/es/properties.php";
$en = require "resources/lang/en/properties.php";
$diff = array_diff(array_keys($es), array_keys($en));
echo empty($diff) ? "✓ Match" : "✗ Diff: " . implode(", ", $diff);
'

# Ver traducción específica
php -r '$d = require "resources/lang/en/properties.php"; echo $d["contact_advertiser"] ?? "NOT FOUND";'
```

---

## 📝 Notas Importantes

1. **Wave usa `/resources/lang/`** (estructura Laravel <11)
2. **Laravel 11+ usa `/lang/`** (pero Wave NO)
3. **Siempre verificar con `app()->langPath()`** antes de editar
4. **No cachear en desarrollo**: Evitar `php artisan config:cache` durante desarrollo i18n
5. **OPcache puede causar problemas**: Reiniciar servidor después de editar archivos PHP

---

**Fecha creación**: 2025-11-21  
**Última actualización**: 2025-11-21  
**Autor**: Proyecto i18n Wave
