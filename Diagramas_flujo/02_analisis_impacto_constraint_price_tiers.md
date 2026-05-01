# Análisis de Impacto — Cambio de Constraint en `price_tiers`

**Fecha:** 2026-04-18  
**Solicitado por:** Cliente (confirmación verbal)  
**Analizado por:** Josadec Pedraza  
**Archivo origen:** `03_price_tiers_final.csv`

---

## Contexto del Cambio

El cliente confirmó que la nueva regla de negocio para la tabla `price_tiers` es:

> **`min_quantity` y `max_quantity` SÍ pueden repetirse dentro del mismo `price_id`.**  
> **`tier_price` NO puede repetirse dentro del mismo `price_id`.**

Esto implica que el constraint único actual debe modificarse.

---

## 1. Constraint Actual vs Constraint Propuesto

### Estado actual (migración `2025_12_10_070000_create_prices_table.php`, línea 43)

```php
$table->unique(['price_id', 'min_quantity', 'max_quantity'], 'price_tier_unique');
```

**Significado:** Para un mismo precio, no pueden existir dos filas con el mismo rango (`min` + `max`). Un mismo rango → un único precio de tier.

### Constraint propuesto

```php
$table->unique(['price_id', 'tier_price'], 'price_tier_unique');
```

**Significado:** Para un mismo precio, no pueden existir dos filas con el mismo `tier_price`. El mismo rango puede aparecer varias veces con precios distintos.

---

## 2. Impacto en Base de Datos

### 2.1 Nueva migración requerida

Se necesita una migración nueva que:
1. Elimine el índice único actual `price_tier_unique`.
2. Cree el nuevo índice único en `(price_id, tier_price)`.

```php
// Nueva migración: drop old unique, add new unique
Schema::table('price_tiers', function (Blueprint $table) {
    $table->dropUnique('price_tier_unique');
    $table->unique(['price_id', 'tier_price'], 'price_tier_unique');
});
```

> **Riesgo:** Si la base de datos en producción ya tiene filas con el mismo `tier_price` para el mismo `price_id`, la migración fallará al intentar crear el nuevo índice único. Se debe hacer un análisis de datos antes del `migrate`.

### 2.2 Consistencia de datos existentes

Antes de migrar, ejecutar esta consulta de validación:

```sql
SELECT price_id, tier_price, COUNT(*) AS duplicados
FROM price_tiers
GROUP BY price_id, tier_price
HAVING COUNT(*) > 1;
```

Si devuelve filas, deben resolverse manualmente antes de aplicar la migración.

---

## 3. Impacto en el Código PHP / Laravel

### 3.1 `Price::getPriceForQuantity()` — **CRÍTICO**

**Archivo:** `app/Models/Price.php` línea 145  

```php
$tier = $this->tiers()
    ->where('min_quantity', '<=', $quantity)
    ->where(function ($query) use ($quantity) {
        $query->whereNull('max_quantity')
              ->orWhere('max_quantity', '>=', $quantity);
    })
    ->first();
```

**Problema:** Con el nuevo constraint, una cantidad puede coincidir con **múltiples tiers que tienen el mismo rango pero diferente `tier_price`**. El método `.first()` devuelve el primero según el orden del relation (`orderBy('min_quantity')`), pero si hay dos filas con el mismo `min_quantity`, el orden entre ellas es **no determinístico** (depende del `id` de inserción).

**Consecuencia directa:**
- Una misma PO puede calcular precios diferentes dependiendo de en qué orden se insertaron los tiers.
- No hay forma de que el sistema sepa cuál de los dos precios del mismo rango es el "correcto".

**Corrección necesaria:** El sistema necesita una regla de desempate explícita. Opciones:
- Agregar un campo `priority` o `is_default` al tier para indicar cuál aplica cuando hay solapamiento.
- Usar siempre el `tier_price` más bajo (ventajoso para el cliente).
- Usar siempre el `tier_price` más alto (ventajoso para la empresa).

### 3.2 `Price::syncTiers()` — **AFECTADO**

**Archivo:** `app/Models/Price.php` línea 204  

```php
$this->tiers()->create([
    'min_quantity' => $tierConfig['min'],
    'max_quantity' => $tierConfig['max'],
    'tier_price' => $price,
]);
```

El método elimina todos los tiers existentes y los recrea desde una configuración fija (`TIER_CONFIG`). Esto **es compatible** con el nuevo constraint ya que no intenta buscar por `(min, max)`. Sin embargo, `TIER_CONFIG` define rangos fijos por tipo de workstation — si el cliente ahora permite rangos duplicados, esta configuración fija puede quedar obsoleta para casos especiales.

### 3.3 `Price::getTiersArrayAttribute()` — **AFECTADO MENOR**

**Archivo:** `app/Models/Price.php` línea 228  

```php
$tier = $this->tiers
    ->first(fn ($t) => $t->min_quantity == $tierConfig['min']);
```

Busca el primer tier cuyo `min_quantity` coincida con la configuración. Si hay dos tiers con el mismo `min_quantity` (rangos duplicados), solo mostrará el primero en la colección. Los demás tiers duplicados **no se mostrarán en el formulario de edición**.

### 3.4 `PriceSeeder::createTiersForPrice()` — **AFECTADO MENOR**

**Archivo:** `database/seeders/PriceSeeder.php` línea 131  

```php
$price->tiers()->firstOrCreate(
    ['min_quantity' => $tier['min'], 'max_quantity' => $tier['max']],
    ['tier_price' => $tierPrice]
);
```

`firstOrCreate` busca por `(min, max)`. Con el nuevo constraint esto sigue funcionando para el seeder (no genera duplicados), pero ya no es la lógica correcta de unicidad según la nueva regla.

---

## 4. Impacto en Módulos de Negocio

### 4.1 Órdenes de Compra — **CRÍTICO**

**Archivos afectados:**
- `app/Services/PurchaseOrderService.php` líneas 37 y 156
- `app/Livewire/Admin/PurchaseOrders/POCreate.php` línea 123
- `app/Livewire/Admin/PurchaseOrders/POEdit.php` línea 132

Todos llaman a `getPriceForQuantity()`. Si el método devuelve un precio incorrecto por ambigüedad de rangos duplicados, **el precio mostrado y validado en la PO será incorrecto**. Esto es un riesgo financiero directo.

### 4.2 Facturación — **CRÍTICO**

**Archivo:** `app/Services/InvoiceFromPackingSlipService.php` línea 128

```php
$unitCostFloat = $price->getPriceForQuantity((int) $qty);
```

El costo unitario de la factura se calcula directamente desde `getPriceForQuantity()`. Un precio ambiguo se trasladaría a la **factura generada al cliente**, con impacto económico real.

### 4.3 Envíos / Shipping Queue — **AFECTADO MENOR**

**Archivo:** `app/Livewire/Admin/Shipping/ShippingQueue.php`  
**Archivo:** `app/Models/PackingSlipItem.php`

Usan `PriceTier` para cálculos de costo en `packing slips`. Mismo riesgo que facturación pero en etapa previa.

---

## 5. Impacto en la Importación del CSV

### 5.1 Problema 1 — Duplicados exactos (ids 130, 135, 145)

Con el nuevo constraint `UNIQUE(price_id, tier_price)`:

| price_id | Rango | Precio 1 | Precio 2 | ¿Importa? |
|----------|-------|----------|----------|-----------|
| 130 | 50000–99999 | 20.33 | 18.3 | ✅ Ambos importan (precios distintos) |
| 130 | 100000–200000 | 18.72 | 16.86 | ✅ Ambos importan |
| 135 | 50000–99999 | 17.26 | 15.54 | ✅ Ambos importan |
| 135 | 100000–200000 | 16.49 | 14.84 | ✅ Ambos importan |
| 145 | 50000–99999 | 16.84 | 23.3 | ✅ Ambos importan |
| 145 | 100000–200000 | 14.5 | 23.17 | ✅ Ambos importan |

**Conclusión:** El Problema 1 ya no bloquea la importación. Sin embargo, introduce ambigüedad en `getPriceForQuantity()` para esos rangos.

### 5.2 Problema 2 — Rangos solapados (18 ids)

Los 18 ids con rangos solapados (`8, 21, 24, 25, 26, 27, 29, 40, 42, 44, 53, 81, 160, 164, 170, 173, 175, 217`) **ya importaban sin error** con el constraint anterior (rangos técnicamente distintos). El nuevo constraint no cambia su comportamiento de importación, pero la ambigüedad en `getPriceForQuantity()` **persiste igual**.

---

## 6. Resumen de Cambios Requeridos

| # | Componente | Cambio Requerido | Prioridad |
|---|-----------|-----------------|-----------|
| 1 | `database/migrations/` | Nueva migración: drop `UNIQUE(price_id, min, max)`, add `UNIQUE(price_id, tier_price)` | 🔴 Alta |
| 2 | `app/Models/Price.php` — `getPriceForQuantity()` | Definir regla de desempate cuando múltiples tiers coincidan con la misma cantidad | 🔴 Alta |
| 3 | Datos en producción | Auditoría previa de `tier_price` duplicados antes de migrar | 🔴 Alta |
| 4 | `app/Models/Price.php` — `getTiersArrayAttribute()` | Adaptar si se necesita mostrar rangos duplicados en formularios | 🟡 Media |
| 5 | `database/seeders/PriceSeeder.php` | Actualizar `firstOrCreate` para que use el nuevo criterio de unicidad | 🟡 Media |
| 6 | CSV `03_price_tiers_final.csv` | Decidir cuál de los dos precios es el "activo" para los 6 rangos duplicados (ids 130, 135, 145) | 🟡 Media |

---

## 7. Pregunta Pendiente para el Cliente

Antes de implementar el cambio, se necesita respuesta a:

> **¿Cuando dos tiers del mismo `price_id` tienen el mismo rango (`min_quantity`–`max_quantity`) pero diferente `tier_price`, cuál de los dos aplica al calcular el precio de una Orden de Compra o Factura?**

Opciones posibles:
- A) El de menor precio (`tier_price` más bajo)
- B) El de mayor precio (`tier_price` más alto)
- C) El insertado más recientemente (`id` más alto)
- D) Se necesita un campo adicional (`is_default`, `priority`, etc.)

La respuesta a esta pregunta determina la corrección necesaria en `getPriceForQuantity()` y es **requisito previo** para implementar el cambio sin riesgo financiero.

---

## 8. Flujo de Implementación Recomendado

```
1. Confirmar regla de desempate con el cliente  (ver sección 7)
2. Corregir getPriceForQuantity() según la regla acordada
3. Auditar datos en producción (buscar tier_price duplicados por price_id)
4. Resolver duplicados en datos existentes si los hay
5. Crear nueva migración con el constraint actualizado
6. Aplicar migración en staging → validar
7. Importar 03_price_tiers_final.csv (los 6 rangos duplicados ya pasarán)
8. Verificar manualmente los ids 130, 135, 145 en el sistema
9. Aplicar en producción
```

---

*Generado el 2026-04-18 como parte del análisis previo a la importación de `03_price_tiers_final.csv`.*
