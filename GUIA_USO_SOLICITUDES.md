# 🏠 Guía de Uso: Sistema de Solicitudes y Matching

## Para Usuarios que Buscan Propiedades

### 1️⃣ Crear una Solicitud

1. **Accede al Dashboard**
   - Inicia sesión en tu cuenta
   - Visita: `/dashboard/requests`

2. **Haz clic en "Nueva Solicitud"**
   - Te llevará al formulario de creación

3. **Completa el Formulario**
   - **Título**: Describe brevemente lo que buscas
     - Ejemplo: "Busco casa con jardín en Córdoba"
   
   - **Descripción**: Sé específico (mínimo 20 caracteres)
     - Menciona características deseadas
     - Zona preferida
     - Condiciones especiales
     - Ejemplo: "Busco una casa de 3 habitaciones con jardín amplio en zona norte de Córdoba. Preferiblemente cerca de colegios y supermercados. Presupuesto flexible para la propiedad correcta."
   
   - **Tipo de Propiedad**:
     - Casa, Departamento, Local, Oficina, Terreno, Campo, Galpón
   
   - **Tipo de Operación**:
     - Venta o Alquiler
   
   - **Presupuesto**:
     - Mínimo (opcional)
     - Máximo (obligatorio)
     - Selecciona moneda: USD, ARS o EUR
   
   - **Ubicación**:
     - País (obligatorio)
     - Provincia/Estado (opcional)
     - Ciudad (opcional)
     - *Tip: Cuanto más específico, mejores matches*
   
   - **Características Mínimas** (todo opcional):
     - Habitaciones
     - Baños
     - Cocheras
     - Área en m²
   
   - **Fecha de Expiración** (opcional):
     - Si no la defines, la solicitud permanece activa indefinidamente
     - Útil para búsquedas temporales

4. **Guarda la Solicitud**
   - El sistema generará automáticamente un "embedding" (huella digital con IA)
   - Este embedding se usa para encontrar propiedades similares

### 2️⃣ Ver Matches (Coincidencias)

1. **Desde la Lista de Solicitudes**
   - Haz clic en "Ver Matches" en cualquier solicitud

2. **En la Página de Matches Verás**:
   - **Badge de nivel de match**:
     - 🟢 **Match Exacto**: 85%+ coincidencia
       - Cumple todos tus requisitos principales
     - 🔵 **Match Inteligente**: 60-84% coincidencia
       - IA encontró similitud semántica
     - 🟡 **Match Flexible**: <60% coincidencia
       - Coincidencias parciales
   
   - **Porcentaje de coincidencia**: Qué tan compatible es
   
   - **Razones del match**: Por qué se recomienda
     - Ejemplo: "Tipo de propiedad coincide", "Precio dentro del presupuesto", "Ciudad coincide"
   
   - **Info de la Propiedad**:
     - Foto
     - Precio
     - Ubicación
     - Características (habitaciones, baños, m²)

3. **Contactar al Anunciante**
   - Haz clic en "Ver Detalles" para ver la ficha completa
   - Desde allí puedes:
     - Enviar email
     - Contactar por WhatsApp (si disponible)
     - Llamar por teléfono

### 3️⃣ Gestionar tus Solicitudes

**Editar una Solicitud**:
- Haz clic en "Editar" en la lista de solicitudes
- Actualiza la información
- El sistema regenerará el embedding si cambió la descripción

**Activar/Desactivar**:
- Usa el botón "Desactivar" si encontraste lo que buscabas
- Puedes reactivarla más tarde sin perder los datos

**Eliminar**:
- Elimina solicitudes que ya no necesites
- Esta acción es permanente

---

## Para Anunciantes de Propiedades

### 1️⃣ Publicar un Anuncio

1. **Accede al Dashboard**
   - Visita: `/dashboard/property-listings`
   - Haz clic en "Nuevo Anuncio"

2. **Completa el Formulario**
   - Título, descripción, tipo, precio, etc.
   - El sistema generará embeddings automáticamente

3. **Publica el Anuncio**
   - Una vez publicado, el sistema buscará solicitudes compatibles

### 2️⃣ Ver Solicitudes Compatibles

1. **Accede a Matches**
   - Visita: `/dashboard/matches`

2. **Verás Agrupaciones por Anuncio**:
   - Cada uno de tus anuncios muestra sus matches
   - Hasta 5 solicitudes mostradas por anuncio
   - Haz clic en "Ver todos" para ver más

3. **Información de Cada Solicitud**:
   - Título y descripción
   - Presupuesto del solicitante
   - Ubicación deseada
   - Características buscadas
   - Nivel de match y score
   - Info del solicitante:
     - Nombre
     - Agencia (si tiene)
     - Email
     - Teléfono/WhatsApp (si disponible)

### 3️⃣ Contactar Interesados

**Desde la Vista de Matches**:
1. Haz clic en el email del solicitante para enviar un correo
2. Haz clic en WhatsApp para chatear directamente
3. Presenta tu propiedad destacando las coincidencias

**Tips de Contacto**:
- Menciona el nivel de match ("Tu solicitud coincide en un 92%...")
- Destaca las características que pidieron
- Ofrece fotos adicionales o visita virtual
- Sé profesional y claro

---

## 🎯 Cómo Funciona el Matching

### Sistema de 3 Niveles

#### 1. Match Exacto (🟢 85%+)
El sistema verifica:
- ✓ Tipo de propiedad coincide
- ✓ Tipo de operación coincide (venta/alquiler)
- ✓ Precio dentro del presupuesto
- ✓ Ubicación compatible
- ✓ Características cumplen requisitos mínimos

**Ejemplo**:
- Solicitud: "Casa en venta, Córdoba, 3 hab., USD 200.000-250.000"
- Anuncio: "Casa 3 hab., Córdoba, USD 230.000"
- **Match Exacto: 95%** ✓

#### 2. Match Inteligente (🔵 60-84%)
Usa Inteligencia Artificial:
- Compara descripciones usando embeddings de OpenAI
- Encuentra similitud semántica
- Puede detectar sinónimos y conceptos relacionados

**Ejemplo**:
- Solicitud: "Busco departamento moderno cerca del centro"
- Anuncio: "Departamento contemporáneo céntrico"
- **Match Inteligente: 78%** (IA detectó similitud)

#### 3. Match Flexible (🟡 <60%)
Coincidencias parciales:
- Mismo tipo de propiedad pero diferente zona
- Precio fuera de rango pero características coinciden
- Ubicación flexible (misma provincia/país)

**Ejemplo**:
- Solicitud: "Casa en Córdoba Capital"
- Anuncio: "Casa en Villa Carlos Paz" (cerca de Córdoba)
- **Match Flexible: 55%**

### Sistema de Puntuación

Cada match recibe puntos por:

| Criterio | Puntos |
|----------|--------|
| Tipo de propiedad coincide | 25 |
| Tipo de operación coincide | 25 |
| Precio dentro del presupuesto | 20 |
| Ciudad coincide | 15 |
| Provincia coincide | 10 |
| País coincide | 5 |
| Habitaciones suficientes | 5 |
| Baños suficientes | 5 |
| Área suficiente | 5 |

**Total máximo: 100 puntos**

---

## 💡 Tips para Mejores Matches

### Para Solicitudes

1. **Sé Específico en la Descripción**
   - ✓ "Busco casa de 3 habitaciones con jardín amplio, cerca de colegios en zona norte de Córdoba"
   - ✗ "Busco casa"

2. **Define un Presupuesto Realista**
   - Investiga precios del mercado
   - Deja margen de flexibilidad (presupuesto mínimo bajo)

3. **Especifica Ubicación**
   - Ciudad > Provincia > País
   - Más específico = mejores matches

4. **Usa Características Mínimas**
   - Define solo lo realmente necesario
   - No pongas requisitos demasiado estrictos

5. **Actualiza Regularmente**
   - Edita tu solicitud si cambian tus necesidades
   - Reactiva solicitudes antiguas

### Para Anuncios

1. **Descripción Completa**
   - Incluye todas las características
   - Menciona zona, servicios cercanos
   - Destaca puntos fuertes

2. **Precio Competitivo**
   - Investiga el mercado
   - Precio justo = más matches

3. **Fotos de Calidad**
   - Buena iluminación
   - Muestra todos los ambientes
   - Foto principal atractiva

4. **Mantén Actualizado**
   - Si vendes/alquilas, desactiva el anuncio
   - Actualiza precio si cambia

---

## 📊 Estadísticas en el Dashboard

El Dashboard principal muestra:

1. **Total de Anuncios Publicados**
   - Cuántas propiedades tienes activas

2. **Total de Solicitudes Activas**
   - Cuántas búsquedas tienes en curso

3. **Matches Encontrados**
   - Total de coincidencias en tus anuncios
   - Se actualiza en tiempo real

---

## ❓ Preguntas Frecuentes

**¿Cuánto tiempo permanece activa una solicitud?**
- Indefinidamente, a menos que establezcas una fecha de expiración
- Puedes desactivarla manualmente en cualquier momento

**¿Puedo tener múltiples solicitudes?**
- Sí, no hay límite
- Cada solicitud puede buscar diferentes tipos de propiedades

**¿Los matches se actualizan automáticamente?**
- Sí, cada vez que se publica un nuevo anuncio o solicitud
- Los matches se calculan en tiempo real

**¿Qué es un "embedding"?**
- Es una representación numérica de tu texto generada con IA
- Permite encontrar similitudes semánticas
- Se genera automáticamente al crear/editar

**¿Puedo editar una solicitud después de crearla?**
- Sí, en cualquier momento
- Si cambias la descripción, el embedding se regenera

**¿Los anunciantes ven mi información de contacto?**
- Solo si hay un match
- Ven tu nombre, email y teléfono (si lo agregaste)

**¿Cómo mejoro mis matches?**
- Descripción detallada y específica
- Presupuesto realista
- Ubicación precisa
- Características justas (no demasiado estrictas)

---

## 🔐 Privacidad y Seguridad

- Solo usuarios registrados pueden crear solicitudes
- Solo ves solicitudes que coinciden con tus anuncios
- Solo puedes editar/eliminar tus propias solicitudes
- Información de contacto solo visible en matches
- Datos protegidos con autenticación

---

## 📞 Soporte

Si tienes problemas o preguntas:
- Revisa esta guía
- Consulta la documentación técnica: `SISTEMA_SOLICITUDES.md`
- Contacta al administrador del sitio

---

**¡Buena suerte en tu búsqueda o venta de propiedades! 🏡**
