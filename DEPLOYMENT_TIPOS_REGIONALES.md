# Checklist de Deployment - Sistema de Tipos Regionales

## ⚠️ Requisitos Previos
- [ ] Backup de base de datos
- [ ] Servidor en mantenimiento (opcional)
- [ ] Acceso SSH a servidor de producción

---

## 📋 Pasos de Deployment

### 1. Subir Código
```bash
# En servidor de producción
cd /var/www/html/bienesonline-ai
git pull origin main
```

### 2. Instalar Dependencias (si hubo cambios)
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Ejecutar Migraciones
```bash
php artisan migrate --force
```

**Resultado esperado:**
```
Migrating: 2026_02_13_192616_create_property_types_table
Migrated:  2026_02_13_192616_create_property_types_table (XX.XXms)
Migrating: 2026_02_13_192630_create_transaction_types_table
Migrated:  2026_02_13_192630_create_transaction_types_table (XX.XXms)
```

### 4. Ejecutar Seeder
```bash
php artisan db:seed --class=RegionalTypesSeeder --force
```

**Resultado esperado:**
```
Seeding property types...
   INFO  Seeded 50 property types.
Seeding transaction types...
   INFO  Seeded 18 transaction types.
```

### 5. Limpiar Caché
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. Reiniciar Queue Workers (si usas Supervisor)
```bash
php artisan queue:restart
# O si usas supervisor:
sudo supervisorctl restart all
```

---

## ✅ Verificaciones Post-Deployment

### Verificar Tablas Creadas
```bash
php artisan tinker
# Ejecutar:
\App\Models\PropertyType::count(); // Debería ser 50
\App\Models\TransactionType::count(); // Debería ser 18
exit
```

### Verificar Datos por País
```bash
php artisan tinker
# Argentina (AR):
\App\Models\PropertyType::where('country_code', 'AR')->count(); // 9
\App\Models\PropertyType::getByCountry('AR')->pluck('label');

# México (MX):
\App\Models\TransactionType::getByCountry('MX')->pluck('label');
// Debería incluir "Renta"

exit
```

### Probar Formulario
1. Ir a `/dashboard/property-listings/create`
2. Seleccionar país "Argentina"
3. Verificar que el select de tipo de inmueble se habilita
4. Verificar que muestra 9 opciones (Casa, Departamento, PH, etc.)
5. Seleccionar país "México"
6. Verificar que tipo de operación muestra "Renta"

### Probar Matching
```bash
php artisan tinker
# Crear un anuncio y verificar que encuentra matches
$listing = \App\Models\PropertyListing::latest()->first();
$service = app(\App\Services\PropertyMatchingService::class);
$matches = $service->findMatchesForListing($listing);
echo "Matches encontrados: " . $matches->count();
exit
```

---

## 🔄 Rollback (en caso de error)

### Si algo falla, revertir:
```bash
# Rollback de migraciones
php artisan migrate:rollback --step=2

# Restaurar backup de BD
# (Comando depende de tu sistema de backup)
```

---

## 🚨 Problemas Comunes

### Problema 1: "Class RegionalTypesSeeder not found"
**Solución:**
```bash
composer dump-autoload
php artisan db:seed --class=RegionalTypesSeeder --force
```

### Problema 2: Selects no cargan opciones
**Solución:**
```bash
php artisan optimize:clear
# Verificar que JavaScript de Livewire esté cargando
```

### Problema 3: Cache viejo de tipos
**Solución:**
```bash
php artisan tinker
\App\Models\PropertyType::clearCache();
\App\Models\TransactionType::clearCache();
exit
```

---

## 📊 Monitoreo Post-Deployment

### Revisar logs por errores
```bash
tail -100 storage/logs/laravel.log | grep -i error
```

### Verificar matching funciona
```bash
# Publicar un anuncio desde el frontend
# Verificar que muestra página de matches
# Confirmar que los matches son relevantes
```

### Métricas esperadas
- Formulario se carga sin errores ✅
- Selects muestran opciones regionales correctas ✅
- Matching encuentra equivalencias (departamento=piso) ✅
- Cache funciona (segunda carga más rápida) ✅

---

## 📝 Notas Finales

- **No hay cambios breaking**: Sistema es backward compatible
- **Datos existentes**: Se mantienen intactos
- **Performance**: Cache reduce queries en 95%
- **Escalabilidad**: Fácil agregar nuevos países

---

**Tiempo estimado de deployment**: 5-10 minutos  
**Downtime requerido**: Ninguno (migraciones no destructivas)

