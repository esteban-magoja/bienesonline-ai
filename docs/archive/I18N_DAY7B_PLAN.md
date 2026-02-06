# Día 7B - Panel de Control (Vistas Restantes)
## Settings, Property Listings, Notifications

**Fecha estimada:** 2025-11-21  
**Duración estimada:** 6-9 horas  
**Dependencias:** Día 7 completado ✓

---

## 🎯 OBJETIVO

Completar la traducción de todas las vistas del panel de control que NO están en la carpeta `/dashboard/`:
- Settings (configuración del usuario)
- Property Listings (gestión de anuncios)
- Notifications (notificaciones)
- Subscription (suscripción)
- Otros formularios autenticados

---

## 📋 VISTAS A TRADUCIR (11 total)

### 1️⃣ PRIORIDAD ALTA - Property Listings (2-3h)

#### property-listings/index.blade.php
- Lista de anuncios del usuario
- Filtros y búsqueda
- Acciones (editar, eliminar, activar/desactivar)
- Estados (activo, inactivo, destacado)

#### property-listings/create.blade.php
- Formulario completo de creación
- Tabs ES/EN para título y descripción
- Upload de imágenes
- Campos de ubicación
- Características de la propiedad
- Precio y moneda

**Archivos de traducción:**
- `lang/es/listings.php` (nuevo)
- `lang/en/listings.php` (nuevo)

---

### 2️⃣ PRIORIDAD ALTA - Settings Profile & Security (2h)

#### settings/profile.blade.php
- Formulario de edición de perfil
- Avatar/foto de perfil
- Datos personales (nombre, email, etc.)
- Campos adicionales (agencia, móvil, dirección)
- Botones de acción

#### settings/security.blade.php
- Cambio de contraseña
- Two-factor authentication (si aplica)
- Sesiones activas
- Alertas de seguridad

**Archivos de traducción:**
- `lang/es/settings.php` (nuevo)
- `lang/en/settings.php` (nuevo)

---

### 3️⃣ PRIORIDAD MEDIA - Settings Subscription & Invoices (1.5h)

#### settings/subscription.blade.php
- Plan actual
- Cambiar plan
- Cancelar suscripción
- Detalles de facturación

#### settings/invoices.blade.php
- Lista de facturas
- Descargar factura
- Historial de pagos

---

### 4️⃣ PRIORIDAD MEDIA - Notifications (30min)

#### notifications/index.blade.php
- Lista de notificaciones
- Marcar como leído
- Filtros por tipo
- Acciones

**Archivos de traducción:**
- `lang/es/notifications.php` (nuevo)
- `lang/en/notifications.php` (nuevo)

---

### 5️⃣ PRIORIDAD BAJA - Wave Features (1-2h)

#### settings/api.blade.php
- API tokens
- Crear/revocar tokens
- Documentación API

#### subscription/welcome.blade.php
- Mensaje de bienvenida post-suscripción
- Primeros pasos

---

### 6️⃣ DEBATE - Vistas públicas vs panel (revisar)

#### search-property-listings/index.blade.php
- ¿Es pública o del panel?
- Verificar middleware

#### post-request.blade.php
- ¿Es pública o del panel?
- Verificar middleware

---

## 📊 ARCHIVOS DE TRADUCCIÓN A CREAR

### Nuevos archivos necesarios:

1. **lang/es/listings.php** - Gestión de anuncios
   - Títulos y descripciones
   - Formularios
   - Estados
   - Acciones

2. **lang/en/listings.php** - Versión inglés

3. **lang/es/settings.php** - Configuración
   - Profile
   - Security
   - Subscription
   - Invoices
   - API

4. **lang/en/settings.php** - Versión inglés

5. **lang/es/notifications.php** - Notificaciones
   - Tipos de notificaciones
   - Acciones
   - Estados

6. **lang/en/notifications.php** - Versión inglés

---

## 🔄 WORKFLOW

### Morning Setup (15 min)
```bash
cd /var/www/html/wave
git checkout i18n/day-07  # Desde donde partimos
git checkout -b i18n/day-07b
./VIEW_I18N_STATUS.sh
```

### Fase 1: Property Listings (2-3h)
1. Crear `lang/es/listings.php` y `lang/en/listings.php`
2. Traducir `property-listings/index.blade.php`
3. Traducir `property-listings/create.blade.php`
4. Testing básico
5. Commit: `[Day 7B] Traducidas vistas property-listings`

### Fase 2: Settings Profile & Security (2h)
1. Crear `lang/es/settings.php` y `lang/en/settings.php`
2. Traducir `settings/profile.blade.php`
3. Traducir `settings/security.blade.php`
4. Testing básico
5. Commit: `[Day 7B] Traducidas settings profile y security`

### Fase 3: Settings Subscription & Invoices (1.5h)
1. Expandir `settings.php`
2. Traducir `settings/subscription.blade.php`
3. Traducir `settings/invoices.blade.php`
4. Testing básico
5. Commit: `[Day 7B] Traducidas settings subscription e invoices`

### Fase 4: Notifications (30min)
1. Crear `lang/es/notifications.php` y `lang/en/notifications.php`
2. Traducir `notifications/index.blade.php`
3. Testing básico
4. Commit: `[Day 7B] Traducida vista notifications`

### Fase 5: Wave Features (1-2h)
1. Traducir `settings/api.blade.php`
2. Traducir `subscription/welcome.blade.php`
3. Revisar y decidir sobre vistas públicas
4. Commit: `[Day 7B] Traducidas vistas Wave y review final`

### End of Day
```bash
./FINISH_I18N_DAY.sh 7B completed [HORAS]
git log --oneline -10
```

---

## ✅ CHECKLIST

### Preparación
- [ ] Crear branch `i18n/day-07b`
- [ ] Revisar vistas pendientes
- [ ] Crear archivos de traducción base

### Property Listings
- [ ] listings.php (ES/EN)
- [ ] property-listings/index.blade.php
- [ ] property-listings/create.blade.php
- [ ] Testing

### Settings
- [ ] settings.php (ES/EN)
- [ ] settings/profile.blade.php
- [ ] settings/security.blade.php
- [ ] settings/subscription.blade.php
- [ ] settings/invoices.blade.php
- [ ] settings/api.blade.php
- [ ] Testing

### Notifications
- [ ] notifications.php (ES/EN)
- [ ] notifications/index.blade.php
- [ ] Testing

### Otros
- [ ] subscription/welcome.blade.php
- [ ] Revisar search-property-listings
- [ ] Revisar post-request
- [ ] Testing final

### Finalización
- [ ] Todos los commits realizados
- [ ] Documentación actualizada
- [ ] FINISH_I18N_DAY.sh ejecutado
- [ ] Resumen creado

---

## 📝 NOTAS IMPORTANTES

### Diferencias con Día 7
- Día 7: Solo carpeta `/dashboard/*` (solicitudes, matches, mensajes)
- Día 7B: Resto del panel de control (settings, listings, etc.)

### Reutilización de Traducciones
- Muchas keys de `dashboard.php` se pueden reutilizar
- Acciones comunes ya están traducidas
- Estados y badges similares

### Estrategia de Traducciones
- Usar estructura similar a `dashboard.php`
- Agrupar por secciones lógicas
- Reutilizar keys cuando sea posible

---

## 🎯 META DEL DÍA

Al finalizar el Día 7B tendremos:
- ✅ 100% del panel de control traducido (20/20 vistas)
- ✅ Todos los formularios bilingües
- ✅ Settings completamente funcional en ES/EN
- ✅ Gestión de anuncios bilingüe
- ✅ Sistema de notificaciones traducido

---

## ⏱️ ESTIMACIÓN TIEMPO

| Tarea | Tiempo | Prioridad |
|-------|--------|-----------|
| Property Listings | 2-3h | ALTA |
| Settings Profile/Security | 2h | ALTA |
| Settings Subscription/Invoices | 1.5h | MEDIA |
| Notifications | 30min | MEDIA |
| Wave Features | 1-2h | BAJA |
| **TOTAL** | **7-9h** | - |

---

_Creado: 2025-11-21_  
_Actualizado: 2025-11-21_
