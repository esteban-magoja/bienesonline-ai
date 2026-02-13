# Sistema de Tipos Regionales por País

## Resumen Ejecutivo

**Fecha**: 13 Febrero 2026  
**Estado**: ✅ Implementado y testeado  
**Commits**: 4 commits principales

---

## Problema Resuelto

Los países hispanohablantes usan diferentes términos para tipos de inmuebles y operaciones:

- **Transacciones**: "Alquiler" (🇦🇷 AR) vs "Renta" (🇲🇽 MX) vs "Arriendo" (🇨🇱 CL)
- **Tipos**: "Departamento" (🇦🇷 AR/🇲🇽 MX) vs "Piso" (🇪🇸 ES) vs "Apartamento" (🇨🇴 CO)

**Antes**: Datos guardados en inglés (house, sale) causaba confusión y fallos de matching  
**Ahora**: Datos en español regional + sistema de equivalencias universales

---

## Solución Implementada

### 1. Base de Datos Regionalizada
- **Tabla `property_types`**: 50 registros para 6 países
- **Tabla `transaction_types`**: 18 registros para 6 países
- **Campo clave**: `value_en` (puente universal para matching)

### 2. Carga Dinámica por País
- Usuario selecciona país → Se cargan tipos específicos de ese país
- México muestra "Renta", Argentina muestra "Alquiler"
- España muestra "Piso", Colombia muestra "Apartamento"

### 3. Fallback Inteligente
- **Nivel 1**: Buscar en país seleccionado (ej: AR)
- **Nivel 2**: Si no hay datos → Fallback a INTL (7 tipos genéricos)
- **Nivel 3**: Si término no existe en país → Buscar globalmente

### 4. Matching con Equivalencias
```
Anuncio: "departamento" (AR) + value_en="apartment"
Solicitud: "piso" (ES) + value_en="apartment"  
MATCH ✅ → Ambos son "apartment"
```

---

## Países Configurados

| País | Código | Tipos Propiedad | Especiales |
|------|--------|----------------|------------|
| 🌐 Internacional | INTL | 7 | Genéricos |
| 🇦🇷 Argentina | AR | 9 | PH, Cochera |
| 🇲🇽 México | MX | 8 | Rancho, Bodega |
| 🇨🇱 Chile | CL | 8 | Parcela |
| 🇪🇸 España | ES | 10 | Piso, Chalet, Ático |
| 🇨🇴 Colombia | CO | 7 | Apartamento, Parqueadero |

---

## Resultados del Testing

### ✅ Test 1: Carga Dinámica
```bash
Usuario selecciona Argentina → 9 tipos (casa, departamento, PH...)
Usuario selecciona México → 8 tipos + "Renta" en lugar de "Alquiler"
```

### ✅ Test 2: Fallback INTL
```bash
Usuario selecciona Paraguay (sin configurar) → 7 tipos genéricos INTL
```

### ✅ Test 3: Equivalencias Cross-Regionales
```bash
Anuncio: "departamento" en México
Solicitud: "departamento" en México
Match: 90% EXACT ✅
```

### ✅ Test 4: Fallback Global
```bash
Anuncio: "apartamento" (término CO) publicado en Argentina
Sistema busca en AR → No encuentra
Sistema busca globalmente → Encuentra en CO (value_en="apartment")
Match con solicitud "departamento" (AR): 60% SEMANTIC ✅
```

### ✅ Test 5: Filtrado por País
```bash
Anuncio en México NO matchea solicitud en España ✅
(Aunque ambos busquen el mismo tipo de inmueble)
```

---

## Archivos Principales

### Backend
- `app/Models/PropertyType.php` - Modelo con cache y fallback
- `app/Models/TransactionType.php` - Modelo con cache y fallback
- `app/Services/PropertyMatchingService.php` - Matching con equivalencias
- `database/seeders/RegionalTypesSeeder.php` - 68 registros

### Frontend
- `resources/themes/anchor/pages/property-listings/create.blade.php`
  - Líneas 88-91: Arrays dinámicos
  - Líneas 124-147: Método updatedSelectedCountry()
  - Selects deshabilitados hasta seleccionar país

### Traducciones
- `resources/lang/es/listings.php` (líneas 45-47)
- `resources/lang/en/listings.php` (líneas 45-47)

---

## Características Técnicas

### Cache Strategy
- **Duración**: 1 hora por país
- **Key**: `property_types_{$countryCode}`
- **Método**: `PropertyType::clearCache($countryCode)`

### Equivalencias
- **Método**: `PropertyType::getEquivalentValues($value, $countryCode)`
- **Retorno**: Array de valores equivalentes
- **Ejemplo**: `['departamento', 'piso', 'apartamento']`

### Fallback Jerárquico
1. Buscar en país especificado (AR)
2. Si no existe → Buscar en INTL
3. Si valor no existe en país → Buscar globalmente
4. Si no existe en ningún lado → Retornar valor original

---

## Comandos Útiles

### Verificar Cache
```bash
php artisan tinker
PropertyType::getByCountry('AR')->count(); // Debería retornar 9
```

### Limpiar Cache
```bash
php artisan tinker
PropertyType::clearCache('AR');
TransactionType::clearCache('MX');
```

### Re-ejecutar Seeder
```bash
php artisan db:seed --class=RegionalTypesSeeder
```

---

## Próximos Pasos Recomendados

### Agregar Más Países
1. Editar `RegionalTypesSeeder.php`
2. Agregar registros con country_code (ej: 'PE', 'UY')
3. Ejecutar seeder
4. Limpiar cache

### Agregar Tipos Nuevos
```php
PropertyType::create([
    'country_code' => 'AR',
    'value' => 'loft',
    'label' => 'Loft',
    'value_en' => 'loft',
    'order' => 10,
    'is_active' => true,
]);
PropertyType::clearCache('AR');
```

---

## Documentación Completa

- **`SISTEMA_TIPOS_REGIONALES.md`** - Documento de diseño (9.3KB)
- **`CLAUDE.md`** - Sección completa con ejemplos de código
- **`TEST_RESULTS.txt`** - Resultados detallados de testing

---

## Commits Realizados

1. `feat: crear tablas y modelos para tipos regionales`
2. `feat: modificar formulario para carga dinámica de tipos`
3. `feat: usar equivalencias regionales en PropertyMatchingService`
4. `fix: agregar fallback global en getValueEn`
5. `docs: agregar documentación completa del sistema`

---

**Status Final**: ✅ Sistema listo para producción  
**Testing**: ✅ 5 tests pasados exitosamente  
**Documentación**: ✅ Completa y actualizada
