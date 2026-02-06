# 🚀 Quick Start - Implementación i18n

## Primer día de trabajo

```bash
# 1. Iniciar el día
./START_I18N.sh

# 2. Trabajar en las tareas del día
#    Consulta: I18N_IMPLEMENTATION_PLAN.md (detalles)
#    Checklist: I18N_DAILY_CHECKLIST.md (tracking)

# 3. Commits frecuentes durante el día
git add .
git commit -m "[Day X] Feature: descripción"

# 4. Al terminar el día
./FINISH_I18N_DAY.sh
```

## Días siguientes

Simplemente ejecuta `./START_I18N.sh` cada vez que retomes el trabajo. El script:
- Te muestra en qué día estás
- Crea/cambia al branch correspondiente
- Te indica qué archivos consultar
- Mantiene tracking automático

## Archivos clave

| Archivo | Propósito |
|---------|-----------|
| `I18N_IMPLEMENTATION_PLAN.md` | Plan completo y detallado de 12 días |
| `I18N_DAILY_CHECKLIST.md` | Checklist rápido para tracking diario |
| `.i18n-progress` | Tracking automático (actualizado por scripts) |
| `START_I18N.sh` | Script para iniciar día de trabajo |
| `FINISH_I18N_DAY.sh` | Script para finalizar día y trackear |

## Estructura del workflow

```
Día 1: Fundamentos
  ↓
Día 2: Modelos
  ↓
Día 3: Traducciones
  ↓
Día 4-5: Controladores
  ↓
Día 6-7: Vistas (más largos)
  ↓
Día 8: Embeddings IA
  ↓
Día 9: SEO
  ↓
Día 10: Emails
  ↓
Día 11: Admin
  ↓
Día 12: Testing final
```

## Tips importantes

### Si te interrumpen
- Haz commit de lo que tengas: `git commit -am "[Day X] WIP: descripción"`
- Cuando regreses, simplemente ejecuta `./START_I18N.sh`

### Si te atrasas
- No hay problema, ajusta el siguiente día
- Los días 6-7 son los más largos, puedes dividirlos

### Si encuentras un bug
- Solo arregla si está relacionado con tu tarea
- Ignora bugs no relacionados (no es tu responsabilidad)

### Testing continuo
```bash
# Testing rápido
php artisan test --filter=Localization

# Limpiar caches
php artisan optimize:clear

# Ver logs
tail -f storage/logs/laravel.log
```

## Comandos útiles

```bash
# Ver progreso general
cat .i18n-progress | grep STATUS

# Ver en qué día estás
grep "CURRENT_DAY=" .i18n-progress

# Ver cambios sin commit
git status --short

# Ver todas las branches i18n
git branch | grep i18n

# Volver a un día anterior
git checkout i18n/day-05
```

## Troubleshooting rápido

### Problema: Script no ejecuta
```bash
chmod +x START_I18N.sh FINISH_I18N_DAY.sh
```

### Problema: Locale no cambia
```bash
php artisan route:list | grep locale
php artisan optimize:clear
```

### Problema: Traducciones no aparecen
```bash
php artisan view:clear
php artisan tinker
>>> __('properties.types.house')
```

### Problema: Testing falla
```bash
# Ver detalles del error
php artisan test --filter=Localization --stop-on-failure

# Limpiar todo
php artisan migrate:fresh --seed
php artisan optimize:clear
```

## Recursos de consulta

Durante cualquier día puedes consultar:

1. **Plan detallado:** `I18N_IMPLEMENTATION_PLAN.md`
   - Arquitectura completa
   - Troubleshooting extensivo
   - Ejemplos de código

2. **Checklist diario:** `I18N_DAILY_CHECKLIST.md`
   - Tareas específicas del día
   - Testing rápido
   - Comandos útiles

3. **CLAUDE.md:** Contexto del proyecto
   - Customizaciones existentes
   - Arquitectura actual
   - Comandos del proyecto

## Progreso visual

Puedes ver tu progreso en:
- Tabla de "Progreso General" en `I18N_IMPLEMENTATION_PLAN.md`
- Archivo `.i18n-progress` (raw data)
- Barra de progreso en `I18N_DAILY_CHECKLIST.md`

## Al finalizar

Cuando completes los 12 días:
1. El sistema te felicitará automáticamente 🎉
2. Tendrás todo el proyecto bilingüe funcional
3. La documentación estará completa
4. Los tests pasarán al 100%

## ¿Dudas?

Consulta la sección de **Troubleshooting** en:
- `I18N_IMPLEMENTATION_PLAN.md` (detallado)
- `I18N_DAILY_CHECKLIST.md` (quick reference)

---

**¡Éxito con la implementación! 🚀**

*Primera vez: ejecuta `./START_I18N.sh` para comenzar*
