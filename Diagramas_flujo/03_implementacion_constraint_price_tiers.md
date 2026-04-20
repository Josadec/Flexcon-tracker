# Implementación — Cambio de Constraint en `price_tiers`

**Fecha:** 2026-04-19  
**Implementado por:** Mauricio Belmonte  
**Referencia:** `02_analisis_impacto_constraint_price_tiers.md`  
**Regla de desempate acordada:** Opción A — menor `tier_price` (favorable al cliente)

---

## Corrección al Análisis Original

El documento de análisis `02_analisis_impacto_constraint_price_tiers.md` proponía el constraint `UNIQUE(price_id, tier_price)`. Durante la implementación se descubrió que ese constraint es incorrecto porque el propio CSV del cliente (`03_price_tiers_final.csv`) contiene **12 grupos** de `price_id + tier_price` duplicados con rangos distintos:

| price_id | tier_price | Filas en CSV |
|----------|-----------|-------------|
| 26 | 15.59 | 2 (rangos distintos) |
| 112 | 14.45 | 2 (rangos distintos) |
| 187 | 21.14 | 4 |
| 188 | 23.06 | 4 |
| 189 | 21.14 | 4 |
| 200, 201, 202, 203 | 21.14/23.06 | 4 c/u |
| 280 | 18.35 | 4 |
| 282 | 16.01 | 3 |
| 419 | 18.4 | 4 |

**Conclusión:** El mismo precio para distintos rangos es válido según los datos del cliente. El constraint correcto es `UNIQUE(price_id, min_quantity, max_quantity, tier_price)` — las 4 columnas juntas — que previene filas 100% idénticas pero permite:
- Mismo rango con distinto precio ✓
- Distinto rango con mismo precio ✓

---

## Cambios de Código Implementados

### Resumen

| # | Componente | Archivo | Estado |
|---|-----------|---------|--------|
| 1 | Nueva migración de constraint | `database/migrations/2026_04_19_000000_update_price_tiers_unique_constraint.php` | ✅ |
| 2 | `getPriceForQuantity()` — regla de desempate | `app/Models/Price.php` línea 152 | ✅ |
| 3 | `getTiersArrayAttribute()` — consistencia con Opción A | `app/Models/Price.php` línea 230 | ✅ |
| 4 | `PriceSeeder` — criterio `firstOrCreate` actualizado | `database/seeders/PriceSeeder.php` línea 131 | ✅ |
| 5 | `InvoiceFromPackingSlipService` — bug de auditoría de tier | `app/Services/InvoiceFromPackingSlipService.php` línea 134 | ✅ |

---

## Detalle de cada Cambio

### 1. Nueva migración

**Archivo:** `database/migrations/2026_04_19_000000_update_price_tiers_unique_constraint.php`

**Constraint anterior:** `UNIQUE(price_id, min_quantity, max_quantity)`  
**Constraint nuevo:** `UNIQUE(price_id, min_quantity, max_quantity, tier_price)`

- `up()`: valida primero que no existan filas exactamente idénticas en las 4 columnas. Si las hay, lanza `RuntimeException` describiendo exactamente qué `price_id` y rango están duplicados. Si la BD está limpia, aplica el cambio.
- `down()`: rollback al constraint original.

**Query de validación que ejecuta la migración internamente:**
```sql
SELECT price_id, min_quantity, max_quantity, tier_price, COUNT(*) AS total
FROM price_tiers
GROUP BY price_id, min_quantity, max_quantity, tier_price
HAVING COUNT(*) > 1;
```

> **Importante:** Los registros de la BD con `price_id=26, tier_price=15.59` y `price_id=112, tier_price=14.45` NO son errores — coinciden exactamente con el CSV del cliente. No deben borrarse.

---

### 2. `Price::getPriceForQuantity()` — Regla de desempate

**Archivo:** `app/Models/Price.php` — línea 152

Cuando una cantidad coincide con múltiples tiers (por rangos solapados), se aplica el de **menor `tier_price`** (Opción A).

```php
// Antes — orden no determinístico
->first();

// Después — desempate explícito: menor precio gana
->orderBy('tier_price', 'asc')
->first();
```

**Todos los consumidores de este método quedan corregidos automáticamente:**
- `PurchaseOrderService::validatePrice()` → línea 37
- `PurchaseOrderService::getExpectedPrice()` → línea 156
- `POCreate::validatePrice()` → línea 123
- `POEdit::validatePrice()` → línea 132
- `InvoiceFromPackingSlipService::createFromPackingSlip()` → línea 128

---

### 3. `Price::getTiersArrayAttribute()` — Consistencia con Opción A

**Archivo:** `app/Models/Price.php` — línea 230

El formulario de edición de precios usa este método para mostrar los tiers. Se alineó con la misma regla de desempate.

```php
// Antes — primer resultado sin orden garantizado
$tier = $this->tiers->first(fn ($t) => $t->min_quantity == $tierConfig['min']);

// Después — consistente con Opción A
$tier = $this->tiers
    ->filter(fn ($t) => $t->min_quantity == $tierConfig['min'])
    ->sortBy('tier_price')
    ->first();
```

**Limitación conocida:** Cuando hay rangos con el mismo `min_quantity` pero distintos `tier_price`, el formulario solo muestra el de menor precio. Los tiers adicionales no son editables desde la UI actual.

---

### 4. `PriceSeeder` — Nuevo criterio `firstOrCreate`

**Archivo:** `database/seeders/PriceSeeder.php` — línea 131

```php
// Antes — buscaba por (min, max): criterio del constraint anterior
$price->tiers()->firstOrCreate(
    ['min_quantity' => $tier['min'], 'max_quantity' => $tier['max']],
    ['tier_price' => $tierPrice]
);

// Después — busca por tier_price como criterio principal
$price->tiers()->firstOrCreate(
    ['tier_price' => $tierPrice],
    ['min_quantity' => $tier['min'], 'max_quantity' => $tier['max']]
);
```

---

### 5. `InvoiceFromPackingSlipService` — Bug de auditoría de tier (CRÍTICO)

**Archivo:** `app/Services/InvoiceFromPackingSlipService.php` — línea 134

**Bug encontrado durante la revisión:** El servicio calculaba el `unit_cost` usando `getPriceForQuantity()` (con `orderBy tier_price asc`), pero luego ejecutaba una segunda query **sin ese `orderBy`** para registrar el `price_tier_id` de auditoría. Esto causaba que el tier guardado en el invoice no correspondiera al precio realmente cobrado.

```php
// Línea 128 — correcto, usa orderBy tier_price asc → devuelve Tier A
$unitCostFloat = $price->getPriceForQuantity((int) $qty);

// ANTES (líneas 134-140) — SIN orderBy → podía devolver Tier B (distinto al cobrado)
$tier = $price->tiers()
    ->where('min_quantity', '<=', $qty)
    ->where(...)
    ->first(); // orden no determinístico

$priceTierId = $tier->id; // ← grababa el id de Tier B aunque el precio era de Tier A
```

```php
// DESPUÉS — mismo orderBy garantiza que price_tier_id corresponde al precio cobrado
$tier = $price->tiers()
    ->where('min_quantity', '<=', $qty)
    ->where(...)
    ->orderBy('tier_price', 'asc') // ← agregado
    ->first();

$priceTierId = $tier->id; // ← ahora sí corresponde al Tier A cobrado
```

**Impacto si no se corregía:** El `unit_cost` en el invoice era correcto, pero el `price_tier_id` registrado para auditoría apuntaba al tier equivocado. Una auditoría contable habría encontrado inconsistencia entre el precio cobrado y el tier documentado.

---

## Flujo Completo de Validación de Precio en PO

Este es el flujo real que sigue la aplicación cuando se crea o aprueba una PO:

```
POCreate / POEdit
    ↓
POPriceDetectionService::detectPriceForPart(part_id, quantity)
    ↓ busca Standard activo → obtiene assembly_mode → mapea a workstation_type
    ↓ busca Price activo con ese workstation_type
    → devuelve PriceDetectionResult { price, workstationType, found }
    ↓
Price::getPriceForQuantity(quantity)
    ↓ filtra tiers por rango (min ≤ qty ≤ max)
    ↓ orderBy tier_price asc  ← REGLA DE DESEMPATE (Opción A)
    → devuelve el menor tier_price que cubra la cantidad
    ↓
UI muestra "Precio esperado: $X.XX"
El usuario ingresa su unit_price
Si difieren → PO queda en STATUS_PENDING_CORRECTION
Si coinciden → PO pasa a STATUS_APPROVED
```

**Archivos involucrados en este flujo:**

| Archivo | Rol |
|---------|-----|
| `app/Livewire/Admin/PurchaseOrders/POCreate.php` | Formulario de creación, llama validatePrice() en tiempo real |
| `app/Livewire/Admin/PurchaseOrders/POEdit.php` | Formulario de edición, misma lógica |
| `app/Services/POPriceDetectionService.php` | Detecta el precio correcto según Standard y workstation_type |
| `app/Services/PurchaseOrderService.php` | Valida y aprueba la PO comparando precios |
| `app/Models/Price.php` — `getPriceForQuantity()` | Calcula el precio final según cantidad y tiers |

**Flujo de Invoice (desde Packing Slip):**

```
InvoiceFromPackingSlipService::createFromPackingSlip(PackingSlip)
    ↓ por cada PackingSlipItem:
    ↓ Price::getActivePriceForPart(part_id)
    ↓ qty = PO.quantity ?? psItem.quantity_packed
    ↓ Price::getPriceForQuantity(qty)    → unit_cost del InvoiceItem
    ↓ Price.tiers() orderBy tier_price asc → price_tier_id de auditoría (mismo tier)
    ↓ crea InvoiceItem con unit_cost + price_tier_id consistentes
    ↓ actualiza PackingSlipItem.unit_price + price_tier_id + price_source
    → calcula totales y persiste Invoice
```

---

## Advertencia sobre `POPriceDetectionService::validatePOPrice()`

**Archivo:** `app/Services/POPriceDetectionService.php` — línea 173

Este método compara el precio del PO contra `sample_price` en lugar de `getPriceForQuantity()`. **Actualmente no está siendo llamado por ningún flujo activo** — el flujo real usa `PurchaseOrderService::validatePrice()` que es correcto. Sin embargo, si en el futuro se llama este método, dará resultados incorrectos para POs con precios de tier.

---

## Archivos Modificados (resumen final)

### Archivo nuevo
- `database/migrations/2026_04_19_000000_update_price_tiers_unique_constraint.php`

### Archivos editados
- `app/Models/Price.php` (líneas 152 y 230)
- `database/seeders/PriceSeeder.php` (línea 131)
- `app/Services/InvoiceFromPackingSlipService.php` (línea 134)

---

## Pasos Operacionales Pendientes

```
[ ] 1. git pull origin main_mau  (en el servidor de Josadec)

[ ] 2. Auditoría previa en phpMyAdmin:
        SELECT price_id, min_quantity, max_quantity, tier_price, COUNT(*) AS total
        FROM price_tiers
        GROUP BY price_id, min_quantity, max_quantity, tier_price
        HAVING COUNT(*) > 1;
        → Si devuelve filas: son filas 100% idénticas, resolver antes de migrar.
        → price_id=26 y price_id=112 con mismo tier_price son VÁLIDOS (no borrar).

[ ] 3. php artisan migrate

[ ] 4. Importar 03_price_tiers_final.csv desde phpMyAdmin

[ ] 5. Validación funcional:
        → Crear PO con parte que tenga rangos solapados
        → Confirmar que el precio mostrado es el menor tier_price del rango
        → Generar Invoice desde un Packing Slip
        → Confirmar que price_tier_id del InvoiceItem corresponde al precio cobrado

[ ] 6. Aplicar en producción (repetir pasos 2–5)
```

---

*Actualizado el 2026-04-19. Incluye correcciones al constraint, bug de auditoría en Invoice, y documentación completa del flujo PO → Invoice.*
