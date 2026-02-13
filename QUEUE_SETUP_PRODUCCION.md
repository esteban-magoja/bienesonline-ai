# Setup de Queue en Producción

## Problema
Al publicar anuncio aparece error: `insert into "jobs"...`

**Causa**: Sistema de notificaciones intenta usar queue database pero tabla no existe.

---

## Solución 1: Deshabilitar Notificaciones Automáticas (Rápido) ⚡

```bash
# En servidor de producción
nano .env

# Agregar/modificar:
AUTO_MATCHING_ENABLED=false

# Guardar y limpiar cache
php artisan config:clear
```

**Resultado**: Sistema funciona normal, matches se ven al publicar pero NO se envían notificaciones.

---

## Solución 2: Crear Tabla Jobs y Configurar Queue (Completo) 🔧

### Paso 1: Crear Migración de Jobs
```bash
php artisan make:queue-table
php artisan make:queue-failed-table
```

### Paso 2: Ejecutar Migraciones
```bash
php artisan migrate --force
```

**Resultado esperado:**
```
Migrating: xxxx_create_jobs_table
Migrated:  xxxx_create_jobs_table
Migrating: xxxx_create_failed_jobs_table
Migrated:  xxxx_create_failed_jobs_table
```

### Paso 3: Configurar .env
```bash
nano .env

# Configurar:
QUEUE_CONNECTION=database
AUTO_MATCHING_ENABLED=true
MATCHING_MIN_SCORE=70
```

### Paso 4: Limpiar Cache
```bash
php artisan config:clear
php artisan config:cache
```

### Paso 5: Configurar Worker (Elegir A o B)

#### Opción A: Cron (Sin sudo)
```bash
crontab -e

# Agregar:
* * * * * cd /var/www/html/bienesonline-ai && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

#### Opción B: Supervisor (Con sudo - Recomendado)
```bash
sudo nano /etc/supervisor/conf.d/bienesonline-worker.conf

# Contenido:
[program:bienesonline-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/bienesonline-ai/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/bienesonline-ai/storage/logs/worker.log
stopwaitsecs=3600

# Activar:
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bienesonline-worker:*
```

### Paso 6: Verificar
```bash
# Ver trabajos en queue
php artisan queue:monitor

# Ver logs
tail -f storage/logs/laravel.log
```

---

## Verificación de Funcionamiento

### Test 1: Publicar Anuncio
1. Crear nuevo anuncio desde dashboard
2. NO debería aparecer error
3. Ver página de matches normalmente

### Test 2: Ver Jobs Procesados
```bash
php artisan tinker
\DB::table('jobs')->count(); // Debería estar vacío (0) si se procesaron
\DB::table('notifications')->count(); // Debería aumentar
exit
```

### Test 3: Ver Notificaciones
```bash
# Ver últimas notificaciones creadas
php artisan tinker
\App\Models\User::find(2)->notifications()->latest()->first();
exit
```

---

## Problemas Comunes

### "Class jobs does not exist"
**Solución**: Ejecutar migraciones del paso 2

### Jobs no se procesan
**Solución**: 
- Con Cron: Esperar 1 minuto
- Con Supervisor: `sudo supervisorctl status`

### Notificaciones duplicadas
**Solución**:
```bash
php artisan queue:flush
```

---

## ⚖️ Comparación de Soluciones

| Feature | Solución 1 (Deshabilitar) | Solución 2 (Queue) |
|---------|---------------------------|---------------------|
| Tiempo setup | 1 minuto | 10-15 minutos |
| Requiere sudo | No | Sí (para Supervisor) |
| Notificaciones automáticas | ❌ No | ✅ Sí |
| Matches al publicar | ✅ Sí (vista) | ✅ Sí (vista + notif) |
| Email de matches | ❌ No | ✅ Sí |
| Recomendado para | Testing/Inicial | Producción final |

---

## Recomendación

**Para ahora**: Usar Solución 1 (deshabilitar)
- Sistema funciona completamente
- Usuarios ven matches al publicar
- No hay notificaciones por email

**Para después**: Implementar Solución 2
- Cuando tengas acceso sudo
- Cuando quieras emails automáticos
- Setup de Supervisor en servidor

