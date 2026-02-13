# Checklist de Deployment a Producción

## 🔄 Alternativa: Usar Cron (Sin sudo)

Si **NO tienes permisos sudo** para Supervisor:

```bash
# En producción, editar crontab:
crontab -e

# Agregar:
* * * * * cd /var/www/html/bienesonline-ai && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

**⚠️ Limitación:** Jobs se procesan cada minuto, no en tiempo real.

**Verificación:**
```bash
# Ver cron configurado
crontab -l

# Probar manualmente
cd /var/www/html/bienesonline-ai
php artisan queue:work --stop-when-empty

# Ver logs
tail -f storage/logs/laravel.log | grep -i match

# Verificar notificaciones
php artisan tinker
\DB::table('notifications')->where('type', 'LIKE', '%PropertyMatch%')->count();
exit
```

---

## 🚀 Preparación Pre-Deployment

### 1. Variables de Entorno (.env)
```bash
# Agregar estas variables en el .env de producción:

# === Matching Automático ===
AUTO_MATCHING_ENABLED=true
MATCHING_MIN_SCORE=70
MATCHING_MAX_MATCHES=20
MATCHING_NOTIFICATION_DELAY=0
MATCHING_LOGGING_ENABLED=true
MATCHING_LOG_LEVEL=info

# === Queue Configuration ===
QUEUE_CONNECTION=database  # o redis/sqs según tu setup
```

### 2. Verificar OpenAI API Key
```bash
# En .env debe estar configurado:
OPENAI_API_KEY=sk-...
OPENAI_EMBEDDINGS_MODEL=text-embedding-3-small  # o el modelo que uses
```

### 3. Verificar Configuración de Mail
```bash
# Asegurar que el email esté configurado correctamente:
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com  # o tu proveedor
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 📦 Archivos a Subir

### Archivos Nuevos (GIT)
```bash
# Eventos y Listeners
app/Events/PropertyListingCreated.php
app/Listeners/NotifyMatchingRequests.php

# Configuración
config/matching.php

# Controlador de perfiles
app/Http/Controllers/UserProfileController.php

# Vistas
resources/views/user-profile.blade.php

# Documentación
NOTIFICACIONES_AUTOMATICAS.md
ANALISIS_SISTEMA_MATCHING.md
RESUMEN_PERFILES_USUARIO.md
DEPLOYMENT_CHECKLIST.md
```

### Archivos Modificados (GIT)
```bash
# Observer actualizado
app/Observers/PropertyListingObserver.php

# Rutas actualizadas
routes/web.php

# Vista de detalle de propiedad
resources/views/property-detail.blade.php

# Traducciones
resources/lang/es/properties.php
resources/lang/en/properties.php

# CLAUDE.md actualizado
CLAUDE.md

# Variables de ejemplo
.env.example
```

---

## 🔧 Comandos en Producción

### Después de hacer git pull:

```bash
# 1. Instalar/actualizar dependencias (si hubo cambios)
composer install --no-dev --optimize-autoloader

# 2. Limpiar cache
php artisan optimize:clear

# 3. Cachear configuración y rutas (IMPORTANTE)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Migrar BD si hay cambios (en este caso NO hay migraciones nuevas)
# php artisan migrate --force

# 5. Reiniciar workers de queue
php artisan queue:restart
```

---

## ⚙️ Queue Workers (CRÍTICO)

El sistema de notificaciones **requiere** que los queue workers estén corriendo.

### Opción 1: Supervisor (RECOMENDADO para producción)

**Crear archivo de configuración:**
```bash
# /etc/supervisor/conf.d/laravel-worker.conf

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/a/tu/proyecto/artisan queue:work --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/ruta/a/tu/proyecto/storage/logs/worker.log
stopwaitsecs=3600
```

**Iniciar:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

**Verificar:**
```bash
sudo supervisorctl status
```

### Opción 2: Systemd (alternativa)

**Crear servicio:**
```bash
# /etc/systemd/system/laravel-queue.service

[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /ruta/a/tu/proyecto/artisan queue:work --tries=3

[Install]
WantedBy=multi-user.target
```

**Habilitar e iniciar:**
```bash
sudo systemctl enable laravel-queue
sudo systemctl start laravel-queue
sudo systemctl status laravel-queue
```

### Opción 3: Cron (NO RECOMENDADO para notificaciones)
```bash
# Agregar a crontab:
* * * * * cd /ruta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

---

## ✅ Verificación Post-Deployment

### 1. Verificar que el sitio carga
```bash
curl -I https://tudominio.com/es
# Debe retornar: HTTP/2 200
```

### 2. Verificar rutas
```bash
curl -I https://tudominio.com/es/post-request
curl -I https://tudominio.com/es/argentina
curl -I https://tudominio.com/es/inmobiliaria/admin
# Todas deben retornar 200
```

### 3. Verificar configuración de matching
```bash
php artisan tinker
config('matching.enabled');  // true
config('matching.min_score_to_notify');  // 70
exit
```

### 4. Verificar queue workers
```bash
# Ver si hay jobs pendientes
php artisan queue:work --once

# Ver workers corriendo
ps aux | grep "queue:work"
# Debe mostrar procesos activos

# Con Supervisor:
sudo supervisorctl status
# Debe mostrar: laravel-worker:* RUNNING
```

### 5. Verificar listeners registrados
```bash
php artisan tinker
$events = app('events');
$listeners = $events->getListeners('App\\Events\\PropertyListingCreated');
count($listeners);  // Debe ser 1
exit
```

### 6. Verificar logs
```bash
tail -100 storage/logs/laravel.log
# No debe haber errores críticos
```

---

## 🧪 Prueba de Funcionamiento

### Prueba Manual (Recomendada)

```bash
# 1. Conectar por SSH a producción
ssh usuario@servidor

# 2. Ir al proyecto
cd /ruta/a/tu/proyecto

# 3. Crear anuncio de prueba
php artisan tinker

$listing = App\Models\PropertyListing::create([
    'user_id' => 1,  // ID de usuario existente
    'title' => 'Test Casa Producción',
    'description' => 'Prueba del sistema de notificaciones',
    'property_type' => 'house',
    'transaction_type' => 'sale',
    'price' => 100000,
    'currency' => 'USD',
    'bedrooms' => 3,
    'bathrooms' => 2,
    'area' => 120,
    'address' => 'Calle Test 123',
    'city' => 'Córdoba',
    'state' => 'Córdoba',
    'country' => 'Argentina',
    'is_active' => true
]);

exit

# 4. Verificar logs
tail -50 storage/logs/laravel.log | grep -E "PropertyListing|match"

# 5. Verificar notificaciones
php artisan tinker
\DB::table('notifications')->where('type', 'LIKE', '%PropertyMatch%')->count();
exit

# 6. Eliminar anuncio de prueba
php artisan tinker
App\Models\PropertyListing::where('title', 'Test Casa Producción')->delete();
exit
```

---

## 🔒 Seguridad

### 1. Permisos de archivos
```bash
# Asegurar permisos correctos
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 2. Variables sensibles
```bash
# NUNCA subir a GIT:
# - .env (debe estar en .gitignore)
# - Archivos con API keys
# - Credenciales de BD
```

### 3. Rate Limiting (Opcional pero recomendado)
```bash
# En .env agregar (opcional):
MATCHING_MAX_NOTIFICATIONS_PER_DAY=10
MATCHING_MAX_NOTIFICATIONS_PER_HOUR=3
```

---

## 🚨 Troubleshooting en Producción

### Queue no procesa jobs
```bash
# Ver jobs pendientes
php artisan queue:work --once

# Ver failed jobs
php artisan queue:failed

# Reintentar failed jobs
php artisan queue:retry all

# Verificar que Supervisor esté corriendo
sudo supervisorctl status
sudo supervisorctl restart laravel-worker:*
```

### Notificaciones no llegan
```bash
# Verificar configuración de mail
php artisan tinker
config('mail');
exit

# Enviar email de prueba
php artisan tinker
$user = App\Models\User::first();
$user->notify(new App\Notifications\TestNotification());
exit

# Ver logs de mail
tail -100 storage/logs/laravel.log | grep -i mail
```

### Errores en logs
```bash
# Ver errores recientes
tail -200 storage/logs/laravel.log | grep ERROR

# Monitorear en tiempo real
tail -f storage/logs/laravel.log
```

---

## �� Monitoreo Post-Deployment

### Primera semana:

```bash
# Ver notificaciones enviadas
php artisan tinker
\DB::table('notifications')
    ->where('type', 'App\\Notifications\\PropertyMatchFoundNotification')
    ->where('created_at', '>=', now()->subWeek())
    ->count();
exit
```

### Verificar logs diariamente:
```bash
# Errores en las últimas 24h
grep "ERROR" storage/logs/laravel-$(date +%Y-%m-%d).log | wc -l

# Matches procesados hoy
grep "PropertyListing.*created.*Found.*matches" storage/logs/laravel-$(date +%Y-%m-%d).log | wc -l
```

---

## 🔄 Rollback (si algo sale mal)

```bash
# 1. Revertir código
git revert HEAD
# o
git reset --hard <commit-anterior>
git push --force

# 2. Deshabilitar matching automático temporalmente
php artisan tinker
\Illuminate\Support\Facades\Artisan::call('config:clear');
exit

# Editar .env:
AUTO_MATCHING_ENABLED=false

php artisan config:cache

# 3. Reiniciar workers
php artisan queue:restart
sudo supervisorctl restart laravel-worker:*
```

---

## ✅ Checklist Final Pre-Deploy

- [ ] Código commiteado y pusheado a repositorio
- [ ] Variables agregadas en .env de producción
- [ ] OpenAI API Key configurada
- [ ] Mail configurado y testeado
- [ ] Queue workers configurados (Supervisor/Systemd)
- [ ] Backup de BD realizado (por si acaso)
- [ ] Comandos artisan ejecutados post-deploy
- [ ] Config cacheada (config:cache, route:cache)
- [ ] Queue workers reiniciados
- [ ] Prueba manual realizada
- [ ] Logs verificados sin errores
- [ ] Notificaciones de prueba recibidas
- [ ] Rutas verificadas (200 OK)

---

## 📝 Notas Importantes

1. **Queue Workers son OBLIGATORIOS** - Sin ellos, las notificaciones no se enviarán
2. **Config Cache** - Siempre cachear config en producción para performance
3. **OpenAI Costs** - Cada embedding cuesta ~$0.0001. Monitorear uso
4. **Email Limits** - Verificar límites de tu proveedor de email (ej: SendGrid, Mailgun)
5. **Logs** - Rotar logs regularmente para no llenar disco

---

## 🆘 Contactos de Emergencia

- **Logs**: `storage/logs/laravel.log`
- **Queue Status**: `php artisan queue:work --once`
- **Supervisor**: `sudo supervisorctl status`
- **Desactivar**: `.env → AUTO_MATCHING_ENABLED=false`

---

**Última actualización**: 12 Febrero 2026  
**Versión**: 1.0  
**Crítico**: Queue Workers deben estar corriendo

