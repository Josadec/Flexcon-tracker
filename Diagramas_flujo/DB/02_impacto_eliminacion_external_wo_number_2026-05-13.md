# Análisis de Impacto: Eliminación del campo `external_wo_number`

**Fecha:** 2026-05-13  
**Autor:** Análisis generado por Architect Agent  
**Estado:** Pendiente de revisión por el equipo  

---

## Contexto

El campo `work_orders.external_wo_number` fue creado como una "segunda fuente" del número de WO que ya existe en `purchase_orders.wo`. En la práctica son el mismo dato: el número de 7 dígitos que el cliente incluye en su Purchase Order (ej: `1980231`).

**Problema de fondo:** Los Work Orders nunca se crean con `external_wo_number` poblado automáticamente. El usuario debe editarlo manualmente en cada WO para que sus lotes puedan incluirse en un Packing Slip (FPL-10). Esto genera fricción innecesaria y confusión en el flujo.

**El modelo ya tiene implementado el fallback:**
```php
// WorkOrder.php
public function getEffectiveWoNumber(): ?string
{
    return $this->external_wo_number ?? $this->purchaseOrder?->wo ?? null;
}
```

La propuesta es eliminar `external_wo_number` y que todo el sistema use directamente `purchaseOrder->wo` como fuente de verdad.

---

## Archivos Afectados

### Capa 1 — Base de datos (Migraciones)

| Archivo | Línea | Descripción | Riesgo |
|---|---|---|---|
| `database/migrations/2025_12_10_090000_create_work_orders_table.php` | 17 | Define columna `external_wo_number` VARCHAR(20) nullable | **ALTO** |
| `database/migrations/2025_12_10_090000_create_work_orders_table.php` | 37 | Índice `idx_work_orders_external_wo` sobre el campo | **ALTO** |
| `database/migrations/2026_03_08_100003_create_packing_slip_items_table.php` | 50-55 | Comentario documenta que `wo_number_ps` se deriva de `external_wo_number` | BAJO |
| `database/migrations/2026_03_18_100002_create_invoice_items_table.php` | 120-124 | Comentario documenta que `invoice_items.wo_number` usa el campo | BAJO |

> Los campos `wo_number_ps` (packing_slip_items) e `invoice_items.wo_number` son **snapshots inmutables** guardados al momento de crear el documento. Los registros históricos no se ven afectados.

---

### Capa 2 — Modelo Eloquent

| Archivo | Líneas | Qué hace | Riesgo |
|---|---|---|---|
| `app/Models/WorkOrder.php` | 19 | `external_wo_number` en `$fillable` | **ALTO** |
| `app/Models/WorkOrder.php` | 94-98 | `getEffectiveWoNumber()` — fuente primaria del número WO para el PS | **ALTO** |
| `app/Models/WorkOrder.php` | 107-123 | `buildWoCode(int $lotSeq)` — construye `"W0" + wo_number + lot_seq` | **ALTO** |
| `app/Models/WorkOrder.php` | 128-133 | `hasExternalWoNumber()` — puerta de control para permitir lotes en el PS | **ALTO** |
| `app/Models/PackingSlipItem.php` | 17 | Campo derivado `wo_number_ps` en `$fillable` (snapshot, no referencia directa) | BAJO |

---

### Capa 3 — Componentes Livewire y Servicios

| Archivo | Líneas | Qué hace | Riesgo |
|---|---|---|---|
| `app/Livewire/Admin/WorkOrders/WOEdit.php` | 15 | Propiedad pública `$external_wo_number` | **ALTO** |
| `app/Livewire/Admin/WorkOrders/WOEdit.php` | 29 | Regla de validación `nullable\|string\|max:20` | **ALTO** |
| `app/Livewire/Admin/WorkOrders/WOEdit.php` | 47 | `mount()` carga el valor del campo | **ALTO** |
| `app/Livewire/Admin/WorkOrders/WOEdit.php` | 63 | `update()` escribe el campo en BD → **Error SQL si la columna no existe** | **ALTO** |
| `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php` | 69-77 | Guard: bloquea creación de PS si el campo está vacío | **ALTO** |
| `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php` | 99 | Persiste snapshot `wo_number_ps` en `packing_slip_items` | **ALTO** |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | 275-283 | Guard: bloquea agregar lotes a PS existente si campo vacío | **ALTO** |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | 313 | Genera snapshot al agregar lote | **ALTO** |
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | 91 | `toggleLot()` — bloquea selección de lotes sin WO externo | **ALTO** |
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | 191 | `createPackingSlip()` — valida todos los lotes antes de crear PS | **ALTO** |
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | 214, 225 | Genera y persiste `wo_number_ps` | **ALTO** |
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | 297 | `openReturnModal()` — controla si un lote puede devolverse al empaque | **ALTO** |
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | **461** | `->where('external_wo_number', 'like', ...)` en buscador → **Error SQL fatal** | **ALTO** |
| `app/Services/InvoiceFromPackingSlipService.php` | 165-166 | Deriva `wo_number` de `external_wo_number` con fallback a `purchaseOrder->wo` | **ALTO** |
| `app/Services/InvoiceFromPackingSlipService.php` | 180 | Persiste `wo_number` en `invoice_items` | **ALTO** |

---

### Capa 4 — Vistas Blade

| Archivo | Líneas | Qué hace | Riesgo |
|---|---|---|---|
| `resources/views/livewire/admin/work-orders/wo-edit.blade.php` | 82-97 | Input del campo en formulario de edición | **ALTO** |
| `resources/views/livewire/admin/work-orders/wo-show.blade.php` | 91-96 | Muestra el valor en la ficha del WO | **ALTO** |
| `resources/views/livewire/admin/packing-slips/packing-slip-create.blade.php` | 114-121 | Preview del código WO en tabla de selección | **ALTO** |
| `resources/views/livewire/admin/packing-slips/packing-slip-create-v2.blade.php` | 118-132, 181 | Preview del código WO + badge "Sin WO externo" | **ALTO** |
| `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php` | 380-387 | Preview del WO en edición de lotes del PS | **ALTO** |
| `resources/views/livewire/admin/shipping/shipping-queue.blade.php` | 74, 136, 165-187 | Buscador + lógica de badges de WO externo | **ALTO** |

### Capa 5 — PDFs (sin impacto en datos históricos)

| Archivo | Línea | Descripción | Riesgo |
|---|---|---|---|
| `resources/views/pdf/packing-slip.blade.php` | 463 | Usa snapshot `wo_number_ps` — no referencia directa | **BAJO** |
| `resources/views/pdf/invoice.blade.php` | 532 | Usa snapshot `invoice_items.wo_number` — no referencia directa | **BAJO** |

---

## Bloqueos Críticos Identificados

> Estos dos puntos romperían producción de inmediato si se elimina la columna sin actualizar el código PHP antes.

### Bloqueo 1 — Error SQL en el buscador de Shipping Queue
**Archivo:** `app/Livewire/Admin/Shipping/ShippingQueue.php` línea 461  
**Código actual:**
```php
$wq->where('external_wo_number', 'like', '%' . $this->search . '%')
```
Si la columna no existe en la tabla → `SQLSTATE[42S22]: Column not found` cada vez que el usuario escriba en el buscador de la cola de shipping.

### Bloqueo 2 — Error SQL al guardar un Work Order
**Archivo:** `app/Livewire/Admin/WorkOrders/WOEdit.php` línea 63  
**Código actual:**
```php
$this->workOrder->update(['external_wo_number' => $this->external_wo_number ?: null])
```
Si la columna no existe → `SQLSTATE[42S22]: Column not found` al guardar cualquier Work Order editado.

---

## Cambio de Comportamiento Silencioso (Requiere Decisión de Negocio)

Al eliminar el campo, `getEffectiveWoNumber()` activaría el fallback a `purchaseOrder->wo` para **todos los WOs cuya PO tenga ese campo poblado**. Esto cambiaría automáticamente quién puede entrar a un Packing Slip.

**Pregunta para el equipo:** ¿El número de WO del PO (`purchase_orders.wo`) debe ser suficiente para incluir un lote en un PS, o se requiere validación adicional?

- **Si SÍ:** El fallback funciona perfecto. Solo hay que actualizar el código para usarlo correctamente y eliminar el campo.
- **Si NO:** Se debe eliminar el fallback del modelo al mismo tiempo que el campo, y `purchaseOrder->wo` vacío bloquearía el PS igual que hoy.

---

## Plan de Implementación (cuando se apruebe el cambio)

El orden es importante: los cambios PHP deben hacerse **antes** de correr la migración que elimina la columna.

| # | Acción | Archivo | Prioridad |
|---|---|---|---|
| 0 | Verificar en BD cuántos WOs tienen `external_wo_number` poblado | SQL directo | **Antes de todo** |
| 1 | Corregir guard en `PackingSlipCreate` a usar `hasExternalWoNumber()` | `PackingSlipCreate.php` línea 73 | Alto |
| 2 | Corregir guard en `PackingSlipShow` a usar `hasExternalWoNumber()` | `PackingSlipShow.php` línea 279 | Alto |
| 3 | Eliminar búsqueda por `external_wo_number` en `ShippingQueue` | `ShippingQueue.php` línea 461 | **Crítico** |
| 4 | Actualizar vistas Blade de Packing Slips a usar `buildWoCode()` | 3 vistas blade | Alto |
| 5 | Simplificar `getEffectiveWoNumber()` en el modelo | `WorkOrder.php` línea 98 | Alto |
| 6 | Eliminar propiedad, regla y lógica del campo en `WOEdit` | `WOEdit.php` líneas 15, 29, 47, 63 | **Crítico** |
| 7 | Eliminar campo del formulario y vista detalle de WO | `wo-edit.blade.php`, `wo-show.blade.php` | Alto |
| 8 | Simplificar `InvoiceFromPackingSlipService` | Línea 165-166 | Medio |
| 9 | Crear migración `drop_external_wo_number_from_work_orders` | Nueva migración | **Último paso** |

### SQL de verificación previa (ejecutar en producción)
```sql
SELECT id, wo_number, external_wo_number
FROM work_orders
WHERE external_wo_number IS NOT NULL
  AND deleted_at IS NULL;
```

### Migración de eliminación
```php
Schema::table('work_orders', function (Blueprint $table) {
    $table->dropIndex('idx_work_orders_external_wo');
    $table->dropColumn('external_wo_number');
});
```

---

## Archivos sin Impacto Confirmado

Los siguientes componentes fueron auditados y **no tienen referencias** al campo:

- Tests (Feature, Unit, Browser)
- Seeders y Factories
- Jobs, Events, Observers, Policies
- Controladores HTTP
- Rutas (`routes/`)
- Configuración (`config/`)
- Exports Excel
- Reportes adicionales

---

## Resumen Ejecutivo

| Métrica | Valor |
|---|---|
| Archivos PHP afectados | 10 |
| Archivos Blade afectados | 6 |
| Migraciones afectadas | 1 nueva (drop column) |
| Bloqueos críticos | 2 (error SQL en buscador y en WOEdit) |
| Riesgo en datos históricos | NINGUNO (PDFs usan snapshots inmutables) |
| Tests que fallarían | 0 (no hay tests sobre este campo) |

El cambio es **técnicamente factible** y elimina fricción innecesaria en el flujo de Packing Slips. Requiere modificar 16 archivos de forma coordinada. La migración de base de datos debe ejecutarse **al final**, después de que todos los cambios PHP estén desplegados.
