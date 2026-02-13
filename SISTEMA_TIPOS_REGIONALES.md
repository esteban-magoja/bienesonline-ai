# Sistema de Tipos de Inmuebles y Operaciones por País

## 🎯 Objetivo

Implementar un sistema que cargue dinámicamente los tipos de inmuebles y tipos de operación según el país seleccionado, respetando las variantes regionales del español.

## 📊 Arquitectura Propuesta

### 1. Base de Datos

#### Tabla: `property_types`
```sql
CREATE TABLE property_types (
    id BIGINT PRIMARY KEY,
    country_code VARCHAR(2),        -- Código ISO: AR, MX, CL, etc.
    value VARCHAR(50),               -- Valor para BD: departamento, casa, etc.
    label VARCHAR(100),              -- Label para mostrar al usuario
    value_en VARCHAR(50),            -- Valor en inglés (para matching cross-country)
    order INT DEFAULT 0,             -- Orden de visualización
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(country_code, value)
);
```

#### Tabla: `transaction_types`
```sql
CREATE TABLE transaction_types (
    id BIGINT PRIMARY KEY,
    country_code VARCHAR(2),
    value VARCHAR(50),               -- venta, alquiler, arriendo, renta
    label VARCHAR(100),
    value_en VARCHAR(50),            -- sale, rent
    order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(country_code, value)
);
```

### 2. Datos Iniciales (Seeder)

#### Argentina (AR)
```php
PropertyTypes:
- departamento → Departamento (apartment)
- casa → Casa (house)
- ph → PH (townhouse)
- local → Local Comercial (commercial)
- oficina → Oficina (office)
- terreno → Terreno (land)
- campo → Campo (farm)
- galpon → Galpón (warehouse)

TransactionTypes:
- venta → Venta (sale)
- alquiler → Alquiler (rent)
- alquiler_temporal → Alquiler Temporal (temporary_rent)
```

#### México (MX)
```php
PropertyTypes:
- departamento → Departamento (apartment)
- casa → Casa (house)
- local → Local Comercial (commercial)
- oficina → Oficina (office)
- terreno → Terreno (land)
- rancho → Rancho (farm)
- bodega → Bodega (warehouse)

TransactionTypes:
- venta → Venta (sale)
- renta → Renta (rent)
```

#### Chile (CL)
```php
PropertyTypes:
- departamento → Departamento (apartment)
- casa → Casa (house)
- local → Local Comercial (commercial)
- oficina → Oficina (office)
- terreno → Terreno (land)
- parcela → Parcela (farm)
- bodega → Bodega (warehouse)

TransactionTypes:
- venta → Venta (sale)
- arriendo → Arriendo (rent)
```

#### España (ES)
```php
PropertyTypes:
- piso → Piso (apartment)
- casa → Casa (house)
- chalet → Chalet (villa)
- local → Local Comercial (commercial)
- oficina → Oficina (office)
- terreno → Terreno (land)
- finca → Finca (farm)
- nave → Nave Industrial (warehouse)

TransactionTypes:
- venta → Venta (sale)
- alquiler → Alquiler (rent)
```

#### Colombia (CO)
```php
PropertyTypes:
- apartamento → Apartamento (apartment)
- casa → Casa (house)
- local → Local Comercial (commercial)
- oficina → Oficina (office)
- lote → Lote (land)
- finca → Finca (farm)
- bodega → Bodega (warehouse)

TransactionTypes:
- venta → Venta (sale)
- arriendo → Arriendo (rent)
```

### 3. Modelos Eloquent

```php
// app/Models/PropertyType.php
class PropertyType extends Model
{
    protected $fillable = ['country_code', 'value', 'label', 'value_en', 'order', 'is_active'];
    
    public static function getByCountry(string $countryCode)
    {
        return self::where('country_code', $countryCode)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }
    
    public static function getValueEn(string $value, string $countryCode)
    {
        return self::where('country_code', $countryCode)
            ->where('value', $value)
            ->value('value_en');
    }
}

// app/Models/TransactionType.php
class TransactionType extends Model
{
    protected $fillable = ['country_code', 'value', 'label', 'value_en', 'order', 'is_active'];
    
    public static function getByCountry(string $countryCode)
    {
        return self::where('country_code', $countryCode)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }
}
```

### 4. Modificación del Formulario Livewire

```php
// En create.blade.php component

public $propertyTypes = [];
public $transactionTypes = [];

public function updatedSelectedCountry($countryId)
{
    // ... código existente para states/cities ...
    
    // Cargar tipos de inmuebles del país
    $country = Country::find($countryId);
    if ($country) {
        $countryCode = $country->code; // Asumiendo que Country tiene campo 'code'
        $this->propertyTypes = PropertyType::getByCountry($countryCode);
        $this->transactionTypes = TransactionType::getByCountry($countryCode);
    }
    
    // Resetear valores seleccionados
    $this->property_type = '';
    $this->transaction_type = '';
}
```

### 5. Vista del Formulario

```blade
{{-- Tipo de Propiedad - Carga dinámica --}}
<div class="sm:col-span-3">
    <label for="property_type">{{ __('listings.form.property_type') }}</label>
    <select wire:model="property_type" id="property_type">
        <option value="">{{ __('listings.select_property_type') }}</option>
        @foreach($propertyTypes as $type)
            <option value="{{ $type->value }}">{{ $type->label }}</option>
        @endforeach
    </select>
    @error('property_type') <p class="error">{{ $message }}</p> @enderror
    
    @if(empty($propertyTypes) && $selectedCountry)
        <p class="text-sm text-gray-500 mt-1">
            {{ __('listings.select_country_first') }}
        </p>
    @endif
</div>

{{-- Tipo de Operación - Carga dinámica --}}
<div class="sm:col-span-3">
    <label for="transaction_type">{{ __('listings.form.transaction_type') }}</label>
    <select wire:model="transaction_type" id="transaction_type">
        <option value="">{{ __('listings.select_transaction_type') }}</option>
        @foreach($transactionTypes as $type)
            <option value="{{ $type->value }}">{{ $type->label }}</option>
        @endforeach
    </select>
    @error('transaction_type') <p class="error">{{ $message }}</p> @enderror
</div>
```

### 6. Sistema de Matching Mejorado

El matching debe ser **inteligente** y soportar equivalencias:

```php
// app/Services/PropertyMatchingService.php

protected function getExactMatchesForListing(PropertyListing $listing): Collection
{
    // Obtener el valor en inglés (universal) del tipo de propiedad
    $country = Country::where('name', $listing->country)->first();
    $propertyTypeEn = PropertyType::getValueEn($listing->property_type, $country->code);
    
    // Buscar solicitudes que tengan tipos equivalentes en cualquier país
    $equivalentTypes = PropertyType::where('value_en', $propertyTypeEn)
        ->pluck('value')
        ->toArray();
    
    $query = PropertyRequest::active()
        ->whereIn('property_type', $equivalentTypes)  // Match con tipos equivalentes
        ->where('transaction_type', $listing->transaction_type)
        ->where('country', $listing->country);
    
    // ... resto de filtros ...
}
```

**Ejemplo:**
- Un anuncio de "piso" en España → `value_en = apartment`
- Puede matchear con solicitudes de:
  - "departamento" en Argentina
  - "apartamento" en Colombia
  - "piso" en España

## 🔄 Migración de Datos Existentes

### Script de Migración
```php
// Mapear valores actuales a códigos de país y nuevos valores
$listings = PropertyListing::all();
foreach ($listings as $listing) {
    // Determinar country_code del país
    $country = Country::where('name', $listing->country)->first();
    $countryCode = $country->code ?? 'AR'; // Default Argentina
    
    // Los valores ya están en español, no necesitan conversión
    // Solo necesitamos asegurar que existan en la nueva tabla
}

$requests = PropertyRequest::all();
// Mismo proceso...
```

## ✅ Ventajas de Esta Solución

1. **Escalable**: Agregar nuevos países es solo agregar datos al seeder
2. **Flexible**: Cada país puede tener sus propios tipos específicos
3. **Matching Inteligente**: Usa valores en inglés como "puente" entre países
4. **UX Consistente**: Mismo patrón que países/estados/ciudades
5. **Datos en Español**: Los valores guardados son los regionales
6. **Traducciones No Necesarias**: Los labels ya vienen de BD
7. **Admin-Friendly**: Se pueden agregar/editar tipos desde panel admin

## 🚀 Plan de Implementación

1. ✅ Crear migraciones para tablas
2. ✅ Crear modelos PropertyType y TransactionType
3. ✅ Crear seeder con datos de 5 países (AR, MX, CL, ES, CO)
4. ✅ Agregar campo `code` a tabla countries si no existe
5. ✅ Modificar componente Livewire del formulario
6. ✅ Actualizar PropertyMatchingService con equivalencias
7. ✅ Script de migración de datos existentes
8. ✅ Testing del flujo completo
9. ✅ Documentación

## 📝 Notas Importantes

- **Retro-compatibilidad**: El sistema actual seguirá funcionando durante la migración
- **Valores en BD**: Se mantienen en español como se solicitó
- **Country.code**: Necesitamos asegurar que la tabla countries tenga el campo ISO code
- **Filament Admin**: Crear recursos para gestionar PropertyType y TransactionType

## ⏱️ Estimación

- Implementación completa: 3-4 horas
- Testing exhaustivo: 1-2 horas
- **Total**: ~5-6 horas

---

**Fecha**: Febrero 13, 2026  
**Estado**: Propuesta para aprobación
