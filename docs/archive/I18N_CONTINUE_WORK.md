# 🔄 Cómo Retomar el Trabajo i18n

Este archivo te ayuda a retomar el trabajo de i18n en futuras sesiones con Claude o cualquier otro asistente.

## 📝 Contexto para IA Assistant (Claude, ChatGPT, etc.)

Cuando inicies una nueva sesión con un asistente de IA, copia y pega lo siguiente:

---

### Prompt para IA Assistant:

```
Estoy trabajando en la implementación de internacionalización (i18n) para español/inglés 
en un proyecto Laravel llamado Wave (plataforma inmobiliaria).

Tenemos un plan estructurado de 12 días documentado en:
- I18N_IMPLEMENTATION_PLAN.md (plan detallado)
- I18N_DAILY_CHECKLIST.md (checklist diario)

Para ver el estado actual del proyecto ejecuta:
./VIEW_I18N_STATUS.sh

Por favor:
1. Lee el archivo I18N_IMPLEMENTATION_PLAN.md para entender la arquitectura
2. Revisa el progreso en .i18n-progress
3. Ayúdame a continuar desde donde dejé

[OPCIONAL: Indica en qué día estás]
Estoy en el Día X y necesito ayuda con [tarea específica].
```

---

## 🚀 Workflow de Continuación

### Primera sesión del día

1. **Ver estado actual:**
   ```bash
   ./VIEW_I18N_STATUS.sh
   ```

2. **Iniciar día de trabajo:**
   ```bash
   ./START_I18N.sh
   ```

3. **Consultar documentación del día:**
   - Abre `I18N_IMPLEMENTATION_PLAN.md`
   - Busca la sección de tu día actual
   - Usa `I18N_DAILY_CHECKLIST.md` para tracking

### Durante el trabajo

4. **Pedirle ayuda al asistente IA:**
   ```
   Claude, estoy en el Día X - [título del día].
   Necesito ayuda con: [descripción específica]
   
   Contexto adicional:
   - [Lo que ya hiciste]
   - [Dónde te quedaste]
   - [Problemas encontrados]
   ```

5. **Commits frecuentes:**
   ```bash
   git add .
   git commit -m "[Day X] Feature: descripción"
   ```

### Al finalizar el día

6. **Finalizar y trackear:**
   ```bash
   ./FINISH_I18N_DAY.sh
   ```

## 📋 Checklist de Continuación

Cuando retomes el trabajo (mismo día o días después):

- [ ] Ejecutar `./VIEW_I18N_STATUS.sh` para ver progreso
- [ ] Leer notas del último día en `.i18n-progress`
- [ ] Revisar últimos commits: `git log --oneline -5`
- [ ] Revisar cambios pendientes: `git status`
- [ ] Abrir `I18N_IMPLEMENTATION_PLAN.md` en la sección correspondiente
- [ ] Consultar Troubleshooting si hay problemas pendientes

## 🎯 Si te atrasaste o interrumpiste

**No hay problema. El sistema está diseñado para ser flexible.**

1. **Ver dónde te quedaste:**
   ```bash
   ./VIEW_I18N_STATUS.sh
   git log --oneline -10
   git status
   ```

2. **Leer notas del último día:**
   ```bash
   grep "DAY_.*_NOTES" .i18n-progress
   ```

3. **Continuar donde lo dejaste:**
   - El script `START_I18N.sh` te llevará al día correcto
   - Revisa el checklist de ese día
   - Marca las tareas ya completadas

## 🆘 Si algo no funciona

### Problema: Scripts no ejecutan
```bash
chmod +x *.sh
```

### Problema: Branch incorrecta
```bash
# Ver tu branch actual
git branch --show-current

# Ver todas las branches i18n
git branch | grep i18n

# Cambiar a la correcta
git checkout i18n/day-XX
```

### Problema: Perdí el tracking
No te preocupes, puedes reconstruirlo viendo los commits:
```bash
git log --oneline --grep="Day" | head -20
```

### Problema: Olvidé en qué estaba trabajando
```bash
# Ver último commit
git log -1 --stat

# Ver cambios no commiteados
git diff

# Ver archivos modificados
git status
```

## 💡 Tips para Trabajo Continuo

### 1. Documentar antes de cerrar sesión
Antes de terminar tu día:
```bash
# Agregar nota al día actual
# Edita .i18n-progress y agrega nota en DAY_X_NOTES
nano .i18n-progress
```

### 2. Crear checkpoints
Al completar una feature importante:
```bash
git tag -a checkpoint-day-X-feature -m "Descripción"
git push origin --tags
```

### 3. Usar branches descriptivas
Si necesitas experimentar:
```bash
git checkout -b i18n/day-X-experiment
# ... trabajo experimental
# Si funciona:
git checkout i18n/day-X
git merge i18n/day-X-experiment
```

### 4. Backup regular
Al final de cada día:
```bash
git push origin i18n/day-XX
```

## 🔍 Comandos Útiles de Git

```bash
# Ver progreso de commits por día
git log --oneline --grep="Day" --all

# Ver cambios en archivos específicos
git log --follow I18N_IMPLEMENTATION_PLAN.md

# Ver todas las branches i18n
git branch -a | grep i18n

# Restaurar archivo específico a versión anterior
git checkout HEAD~1 -- archivo.php

# Ver quien cambió qué (para context)
git blame app/Http/Middleware/SetLocale.php
```

## 📞 Comunicación con IA Assistant

### Para máxima eficiencia, proporciona:

1. **Contexto del día:**
   ```
   Día X: [nombre del día]
   Estado: [completadas X de Y tareas]
   ```

2. **Últimos cambios:**
   ```
   git log --oneline -5
   git status --short
   ```

3. **Problema específico:**
   ```
   Intenté: [lo que hiciste]
   Error: [mensaje de error]
   Esperaba: [resultado esperado]
   ```

4. **Archivos relevantes:**
   ```
   Estoy modificando:
   - app/Http/Controllers/PropertyController.php
   - resources/themes/anchor/pages/property-detail.blade.php
   ```

## 📚 Archivos de Referencia Rápida

| Archivo | Cuándo usarlo |
|---------|---------------|
| `I18N_QUICK_START.md` | Primera vez, overview rápido |
| `I18N_IMPLEMENTATION_PLAN.md` | Detalles de arquitectura, troubleshooting |
| `I18N_DAILY_CHECKLIST.md` | Tracking diario, tareas específicas |
| `.i18n-progress` | Ver/editar progreso manualmente |
| `CLAUDE.md` | Entender el proyecto completo |
| `VIEW_I18N_STATUS.sh` | Ver estado visual |
| `START_I18N.sh` | Iniciar día de trabajo |
| `FINISH_I18N_DAY.sh` | Finalizar día |

## 🎓 Aprendizajes y Decisiones

A medida que avances, documenta decisiones importantes aquí:

### Día 1:
- [Decisiones tomadas]
- [Problemas resueltos]

### Día 2:
- [Decisiones tomadas]
- [Problemas resueltos]

*(Actualiza esto conforme avances)*

## 🏁 Meta Final

Cuando completes los 12 días:
- ✅ Sistema bilingüe completo (ES/EN)
- ✅ SEO optimizado
- ✅ Búsqueda IA multiidioma
- ✅ Tests pasando al 100%
- ✅ Documentación completa

**¡Recuerda: El progreso es más importante que la perfección!**

---

*Última actualización: 2025-10-16*
*Proyecto: Wave - Plataforma Inmobiliaria*
*Feature: Internacionalización (i18n) ES/EN*
