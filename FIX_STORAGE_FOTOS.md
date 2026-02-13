# 🔧 Fix: Fotos no se Guardan en Producción

## Problema
Las fotos se seleccionan correctamente pero no se guardan al publicar anuncio.

## Causa Común
El enlace simbólico de `storage/app/public` a `public/storage` no existe en producción.

---

## ✅ Solución

### Paso 1: Crear Enlace Simbólico
```bash
# En servidor de producción
cd /var/www/html/bienesonline-ai
php artisan storage:link
```

**Resultado esperado:**
```
The [public/storage] link has been connected to [storage/app/public].
The links have been created.
```

### Paso 2: Verificar Permisos
```bash
# Dar permisos correctos a storage
chmod -R 775 storage
chown -R www-data:www-data storage

# Dar permisos al directorio public
chmod -R 775 public/storage
chown -R www-data:www-data public/storage
```

### Paso 3: Verificar Directorios
```bash
# Crear directorio si no existe
mkdir -p storage/app/public/property_images
chmod -R 775 storage/app/public/property_images
chown -R www-data:www-data storage/app/public/property_images
```

---

## 🔍 Diagnóstico

### Verificar si el enlace existe:
```bash
ls -la public/ | grep storage
```

**Debería mostrar algo como:**
```
lrwxrwxrwx ... storage -> ../storage/app/public
```

Si NO aparece, ejecutar `php artisan storage:link`

### Verificar permisos:
```bash
ls -ld storage/app/public
ls -ld public/storage
```

**Debería mostrar:**
```
drwxrwxr-x ... storage/app/public
lrwxrwxrwx ... public/storage -> ../storage/app/public
```

### Probar guardado manual:
```bash
php artisan tinker
# Ejecutar:
Storage::disk('public')->put('test.txt', 'test');
Storage::disk('public')->exists('test.txt'); // Debería retornar true
file_exists(public_path('storage/test.txt')); // Debería retornar true
exit
```

---

## 🧪 Test Completo

### 1. Subir foto de prueba
1. Ir a `/dashboard/property-listings/create`
2. Completar formulario
3. Subir foto
4. Publicar

### 2. Verificar en base de datos
```bash
php artisan tinker
$listing = \App\Models\PropertyListing::latest()->first();
$listing->images; // Debería mostrar imágenes
$listing->primaryImage; // Debería mostrar imagen principal
exit
```

### 3. Verificar archivo físico
```bash
# Ver últimas imágenes guardadas
ls -lh storage/app/public/property_images/ | tail -10

# Verificar acceso público
# Abrir en navegador: https://tudominio.com/storage/property_images/NOMBRE_ARCHIVO.jpg
```

---

## 🚨 Otros Problemas Posibles

### Problema: "Disk [public] not found"
**Solución**: Verificar `config/filesystems.php`
```bash
php artisan config:clear
php artisan config:cache
```

### Problema: Fotos muy pesadas (timeout)
**Solución**: Aumentar límites en `php.ini`
```ini
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 300
```

Luego reiniciar PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm  # Ajustar versión de PHP
```

### Problema: Disco lleno
**Solución**: Verificar espacio disponible
```bash
df -h
# Si storage/ está lleno, limpiar archivos antiguos
```

---

## 📋 Checklist de Verificación

- [ ] Ejecutar `php artisan storage:link`
- [ ] Verificar permisos 775 en `storage/`
- [ ] Verificar permisos 775 en `public/storage`
- [ ] Propietario `www-data:www-data` en ambos
- [ ] Directorio `storage/app/public/property_images` existe
- [ ] Test de subida funciona
- [ ] Imagen visible en base de datos
- [ ] Archivo físico existe
- [ ] Imagen accesible desde navegador

---

## ✅ Comandos Rápidos (Copy-Paste)

```bash
# Todo en uno (ejecutar en servidor de producción)
cd /var/www/html/bienesonline-ai
php artisan storage:link
mkdir -p storage/app/public/property_images
chmod -R 775 storage
chown -R www-data:www-data storage
chmod -R 775 public/storage
chown -R www-data:www-data public/storage
php artisan config:clear
```

Luego probar subiendo una foto desde el dashboard.

