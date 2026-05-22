# Analisis Tecnico: Cambio QTY EMPACADA → PZ COMPLETADAS en WO Listo para SL

**Ultima revision:** 2026-05-21 (verificacion completa de cadena Invoice)
**Estado de implementacion:** Parcialmente implementado (ver seccion de estado por componente)

---

## Resumen Ejecutivo

El negocio solicita que las **Piezas Completadas** en la Lista de Envio se calculen acorde a los **ciclos que hayan realizado en los lotes**, no solo del ciclo actual de empaque. Este cambio debe reflejarse en:

1. **WO Listos para SL** (ShippingQueue): columna "QTY EMPACADA" debe mostrar el total acumulado de todos los ciclos.
2. **Shipping List** (Lista de Envio): columna "Pz Completadas" por lote y por WO.
3. **PDFs generados**: Packing Slip PDF (FPL-10) y Invoice PDF (FPL-12) deben reflejar el valor correcto al momento de su generacion.

La fuente correcta para "Piezas Completadas" es `Lot::getTotalCompletedPieces()`, que acumula piezas de todos los ciclos registrados en `lot_completion_logs` mas las piezas del ciclo final en `packaging_records`.

---

## Estado Actual (Como Funciona Hoy)

### Flujo de datos para QTY EMPACADA en ShippingQueue

```
packaging_records.packed_pieces
        |
        v (LotPackagingObserver, cuando closure_decision cambia)
lots.quantity_packed_final  =  SUM(packaging_records.packed_pieces)
        |
        v (ShippingQueue blade, ANTES del cambio)
"Qty Empacada" mostrada en la cola
        |
        v (ShippingQueue::createPackingSlip(), ANTES del cambio)
packing_slip_items.quantity_packed  =  lot->quantity_packed_final ?? lot->getPackagingPackedPieces()
```

**Fuente de QTY EMPACADA anterior:** `lots.quantity_packed_final`, que representa solo las piezas del ciclo de empaque actual, SIN incluir piezas de ciclos anteriores de "Completar Lote".

### Flujo de datos para PZ COMPLETADAS en la Lista de Envio

```
lot_completion_logs.packed_pieces  (por cada ciclo de "Completar Lote")
  +
packaging_records.packed_pieces (ciclo final, cuando closure_decision != null)
        |
        v (Lot::getCompletionCycles())
array de ciclos: [['cycle'=>1, 'pieces'=>N], ...]
        |
        v (Lot::getTotalCompletedPieces())
suma total de piezas cerradas en TODOS los ciclos
        |
        v (shipping-list-display.blade.php, linea 996 y 285)
$lotCompletedTotal = array_sum(array_column($lotCycles, 'pieces'))
$woCompletedPieces = $allLots->sum(fn ($l) => $l->getTotalCompletedPieces())
```

**Fuente de PZ COMPLETADAS:** `Lot::getTotalCompletedPieces()`, que acumula piezas de todos los ciclos de `LotCompletionLog` mas las piezas del ciclo final en `PackagingRecord`.

### Diferencia entre QTY EMPACADA y PZ COMPLETADAS

| Escenario | QTY EMPACADA (logica antigua) | PZ COMPLETADAS (logica nueva) |
|---|---|---|
| Lote simple sin ciclos extras | `packaging_records.packed_pieces` (= final) | Igual: `getTotalCompletedPieces()` |
| Lote con 1 ciclo de "Completar Lote" | Solo piezas del ciclo actual (incompleto) | Piezas C1 + piezas ciclo final |
| Lote con 2+ ciclos de "Completar Lote" | Solo piezas del ultimo ciclo | Piezas C1 + C2 + ... + ciclo final |

**El valor de PZ COMPLETADAS es siempre mayor o igual al de QTY EMPACADA** cuando existen ciclos de completado previos. Para lotes sin ciclos extras, ambos valores son identicos.

---

## Estado de Implementacion por Componente

| Componente | Archivo | Estado | Nota |
|---|---|---|---|
| ShippingQueue blade (tabla principal) | `shipping-queue.blade.php` linea 207 | **IMPLEMENTADO** | Usa `getTotalCompletedPieces()` |
| ShippingQueue blade (modal devolucion) | `shipping-queue.blade.php` linea 315 | **IMPLEMENTADO** | Usa `getTotalCompletedPieces()` |
| ShippingQueue blade (modal confirmacion) | `shipping-queue.blade.php` linea 447 | **IMPLEMENTADO** | Usa `getTotalCompletedPieces()` |
| ShippingQueue render() eager loading | `ShippingQueue.php` lineas 452-453 | **IMPLEMENTADO** | Incluye `completionLogs`, `packagingRecords` |
| ShippingQueue createPackingSlip() snapshot | `ShippingQueue.php` linea 224 | **IMPLEMENTADO** | `quantity_packed = getTotalCompletedPieces()` |
| ShippingQueue createPackingSlip() eager load | `ShippingQueue.php` linea 176 | **IMPLEMENTADO** | Incluye `completionLogs` |
| ShippingQueue $returningLot eager load | `ShippingQueue.php` linea 492 | **IMPLEMENTADO** | Incluye `completionLogs`, `packagingRecords` |
| Shipping List columna Pz Completadas por WO | `shipping-list-display.blade.php` linea 285 | **IMPLEMENTADO** | Usa `getTotalCompletedPieces()` |
| Shipping List columna Pz Completadas por lote | `shipping-list-display.blade.php` lineas 995-996 | **IMPLEMENTADO** | Muestra ciclos + sumatoria |
| Packing Slip PDF (FPL-10) | `pdf/packing-slip.blade.php` linea 467 | **CORRECTO** | Lee `$item->quantity_packed` (snapshot inmutable) |
| Invoice PDF (FPL-12) | `pdf/invoice.blade.php` linea 533 | **CORRECTO** | Lee `$item->quantity` (snapshot inmutable del InvoiceItem) |
| Columna "Cant. a Enviar" eliminada | `shipping-list-display.blade.php` | **PARCIAL** | Renombrada a "Cant. Pendiente", variable `$cantAEnviar` conservada con semantica igual |

---

## Analisis del Impacto en PDFs

### PDF del Packing Slip (FPL-10): `pdf/packing-slip.blade.php`

**Mecanismo de generacion:**

El PDF es generado por `PackingSlipPdfController::buildData()`, que carga:

```php
$packingSlip->load([
    'items.lot.workOrder.purchaseOrder.part',
]);
```

La cantidad mostrada en cada fila del PDF viene de:

```php
// pdf/packing-slip.blade.php, linea 467
<td class="col-qty">{{ number_format($item->quantity_packed) }}</td>
```

Y el subtotal por grupo PO:

```php
// pdf/packing-slip.blade.php, linea 453
$groupTotal = $poItems->sum('quantity_packed');
```

**Conclusion:** El PDF del Packing Slip lee `packing_slip_items.quantity_packed`, que es un **snapshot inmutable** capturado en el momento de crear el Packing Slip desde ShippingQueue. Si el snapshot fue creado con la nueva logica (`getTotalCompletedPieces()`), el PDF refleja automaticamente el valor correcto. No se requiere ningun cambio en el PDF ni en el controlador.

**Comportamiento por fecha de creacion del PS:**
- PS creados ANTES del cambio: `quantity_packed` = `quantity_packed_final` (ciclo actual solamente).
- PS creados DESPUES del cambio: `quantity_packed` = `getTotalCompletedPieces()` (todos los ciclos).

Esto es correcto segun el principio de snapshot inmutable del documento.

### PDF del Invoice (FPL-12): `pdf/invoice.blade.php`

**Mecanismo de generacion:**

El PDF es generado por `InvoicePdfService::generate()`, que carga:

```php
$invoice->load([
    'items.part',
    'packingSlip',
]);
```

La cantidad mostrada en cada fila del Invoice viene de:

```php
// pdf/invoice.blade.php, linea 533
<td class="td-right">{{ number_format($item->quantity) }}</td>
```

Y el total general:

```php
// pdf/invoice.blade.php, linea 561
<td class="total-qty">{{ number_format((int) $invoice->total_quantity) }}</td>
```

**Origen del campo `quantity` en InvoiceItem:**

`InvoiceFromPackingSlipService` crea el `InvoiceItem` con:

```php
// InvoiceFromPackingSlipService.php, linea 182
'quantity' => $psItem->quantity_packed,
```

Y el calculo del `line_total`:

```php
// InvoiceFromPackingSlipService.php, lineas 157-159
$lineTotal = round(
    (float) bcmul((string) $psItem->quantity_packed, $unitCost, 6),
    2
);
```

**Conclusion:** El Invoice PDF lee `invoice_items.quantity`, que fue copiado de `packing_slip_items.quantity_packed` al momento de crear el Invoice. La cadena de snapshots es:

```
Lot::getTotalCompletedPieces()
    → packing_slip_items.quantity_packed  (snapshot al crear PS)
    → invoice_items.quantity              (snapshot al crear Invoice)
    → PDF Invoice (lee invoice_items.quantity)
```

El valor correcto se propaga automaticamente a traves de la cadena de snapshots, siempre que el snapshot inicial en `packing_slip_items.quantity_packed` haya sido capturado con la nueva logica.

**No se requiere ningun cambio en la vista PDF del Invoice ni en InvoicePdfService.**

Adicionalmente, el `workstation_type` predominante (usado para calcular `lot_no` del Invoice) se determina buscando el item con mayor `quantity_packed`:

```php
// InvoiceFromPackingSlipService.php, linea 357
$dominantItem = $ps->items->sortByDesc('quantity_packed')->first();
```

Este comportamiento es correcto: al usar el valor real de todas las piezas completadas, el item dominante se determina con mayor precision.

### Resumen del impacto en PDFs

| PDF | Vista Blade | Fuente de datos | Requiere cambio | Comportamiento |
|---|---|---|---|---|
| Packing Slip (FPL-10) | `pdf/packing-slip.blade.php` | `packing_slip_items.quantity_packed` (snapshot) | **NO** | Correcto automaticamente via snapshot |
| Invoice (FPL-12) | `pdf/invoice.blade.php` | `invoice_items.quantity` (snapshot de snapshot) | **NO** | Correcto automaticamente via cadena de snapshots |

---

## Analisis Detallado de Componentes

### ShippingQueue (WO Listos para SL)

#### Cambio en el display de la cola

El campo `QTY EMPACADA` en la cola de despacho ahora muestra `$lot->getTotalCompletedPieces()` en lugar de `$lot->quantity_packed_final`.

**Estado: IMPLEMENTADO.** Verificado en `shipping-queue.blade.php` lineas 207, 315, 447.

#### Cambio en el snapshot al crear Packing Slip

Al crear el `PackingSlipItem`, el snapshot `quantity_packed` ahora captura `getTotalCompletedPieces()` en lugar de `quantity_packed_final ?? getPackagingPackedPieces()`.

**Estado: IMPLEMENTADO.** Verificado en `ShippingQueue.php` linea 224.

#### Eager loading actualizado

Para evitar N+1 queries, los lotes se cargan con `completionLogs` y `packagingRecords` en todos los contextos relevantes del componente.

**Estado: IMPLEMENTADO.** Verificado en `ShippingQueue.php` lineas 176, 452-453, 492.

### Shipping List (Lista de Envio)

#### Columna "Pz Completadas" por WO

La columna muestra `$woCompletedPieces = $allLots->sum(fn ($l) => $l->getTotalCompletedPieces())` en la fila resumen de cada WO.

**Estado: IMPLEMENTADO.** Verificado en `shipping-list-display.blade.php` linea 285 y 562-564.

#### Columna "Pz Completadas" por lote (filas de expansion)

Para cada lote dentro de un WO, la columna muestra los ciclos individuales (C1, C2, ...) con badges de color ambar, mas el total acumulado con badge verde (Sigma).

**Estado: IMPLEMENTADO.** Verificado en `shipping-list-display.blade.php` lineas 994-1013.

#### Columna "Cant. Pendiente" (anteriormente "Cant. a Enviar")

El analisis original proponia **eliminar** la columna "Cant. a Enviar". En la implementacion actual, la columna fue **renombrada** a "Cant. Pendiente" (header en linea 244) pero conserva el mismo calculo (`$cantAEnviar = $cantWO - $pzEnviadas`). La variable `$cantAEnviar` sigue usandose en lineas 558 (desktop) y 1119 (movil).

**Estado: PENDIENTE DE DECISION.** Opciones:
- **Opcion A**: Mantener como "Cant. Pendiente" (comportamiento actual). El nombre refleja mejor la semantica: piezas del WO aun no enviadas.
- **Opcion B**: Eliminar completamente la columna si el negocio la considera innecesaria.

Si se decide eliminar, los cambios son:
- Eliminar `<th>` "Cant. Pendiente" (lineas 242-244).
- Eliminar `<td>` con `$cantAEnviar` en vista desktop (linea 557-558).
- Eliminar variable `$cantAEnviar` del bloque `@php` (linea 275).
- Eliminar bloque movil (lineas 1074 en `@php`, 1115-1120 en HTML).

---

## Analisis de la Funcion getTotalCompletedPieces()

La funcion existe en `app/Models/Lot.php`:

```php
public function getTotalCompletedPieces(): int
{
    return array_sum(array_column($this->getCompletionCycles(), 'pieces'));
}
```

Depende de `getCompletionCycles()`, que:
1. Lee `$this->completionLogs` (relacion `HasMany` a `lot_completion_logs`).
2. Lee `$this->packagingRecords` para el ciclo final cuando `closure_decision != null`.

**Requisito de eager loading:** Para evitar N+1 queries, cualquier componente que llame a `getTotalCompletedPieces()` en una iteracion de lotes debe cargar previamente `completionLogs` y `packagingRecords`. Esto esta correctamente implementado en ShippingQueue. La ShippingList carga los lotes via relacion `$wo->lots`, que debe verificarse para confirmar que incluye estas relaciones por eager loading o si se resuelven con lazy loading aceptable dada la frecuencia de uso.

---

## Tabla Comparativa: Flujo Actual vs Propuesto

| Aspecto | Estado Anterior | Estado Actual / Propuesto |
|---|---|---|
| Fuente de QTY EMPACADA en cola | `lots.quantity_packed_final` (ciclo actual) | `Lot::getTotalCompletedPieces()` (todos los ciclos) [IMPLEMENTADO] |
| Snapshot en `packing_slip_items.quantity_packed` | `quantity_packed_final ?? getPackagingPackedPieces()` | `getTotalCompletedPieces()` [IMPLEMENTADO] |
| PDF Packing Slip (FPL-10) | Lee `quantity_packed` (snapshot) | Sin cambio, correcto via snapshot [NO REQUIERE CAMBIO] |
| PDF Invoice (FPL-12) | Lee `invoice_items.quantity` (snapshot de snapshot) | Sin cambio, correcto via cadena de snapshots [NO REQUIERE CAMBIO] |
| Columna en Lista de Envio | "Cant. a Enviar" | Renombrada "Cant. Pendiente" [PENDIENTE DECISION] |
| Pz Completadas en Lista de Envio por WO | No existia / usaba quantity_packed_final | `getTotalCompletedPieces()` [IMPLEMENTADO] |
| Pz Completadas en Lista de Envio por lote | No existia | Ciclos C1..Cn + sumatoria con badges [IMPLEMENTADO] |
| Requiere migracion de BD | — | No |
| Requiere cambio en vistas PDF | — | No (snapshot propagado automaticamente) |
| Eager loading adicional | No requerido | Si: `completionLogs`, `packagingRecords` [IMPLEMENTADO] |

---

## Riesgos e Impacto en Otras Funcionalidades

### Riesgo 1: Datos historicos en PackingSlipItems existentes

Los `PackingSlipItem` ya creados contienen `quantity_packed` calculado con la logica antigua (`quantity_packed_final`). El cambio aplica solo a los nuevos PS creados despues de implementar el fix. Los registros historicos no se alteran, lo cual es correcto dado el principio de snapshot inmutable.

**Nivel de riesgo: BAJO.** Los PS existentes y sus PDFs e Invoices derivados no se ven afectados.

### Riesgo 2: Invoice generado desde PackingSlip

`InvoiceFromPackingSlipService.php` usa `$psItem->quantity_packed` como base para:
- Calcular `invoice_items.quantity` (linea 182).
- Calcular `invoice_items.line_total` via `bcmul($psItem->quantity_packed, $unitCost)` (lineas 157-159).
- Determinar el item dominante para el `lot_no` del Invoice (linea 357, `sortByDesc('quantity_packed')`).

Al cambiar el valor de `quantity_packed` en los nuevos PS, los Invoices futuros reflejaran el total real de piezas completadas (todos los ciclos). **Este es el comportamiento correcto segun la regla de negocio.**

**Nivel de riesgo: NINGUNO (mejora).** El cambio alinea el Invoice con la realidad.

### Riesgo 3: Lotes sin ciclos de completado

Para lotes que nunca pasaron por "Completar Lote" (flujo simple), `getTotalCompletedPieces()` devuelve el mismo valor que la logica anterior porque:
- `completionLogs` esta vacio (no hay ciclos intermedios).
- El ciclo final usa `packagingRecords.packed_pieces` cuando `closure_decision != null`.
- `quantity_packed_final` = `SUM(packaging_records.packed_pieces)` (mismo calculo).

**Nivel de riesgo: NINGUNO.** Compatibilidad total para el caso simple.

### Riesgo 4: Lotes con `getTotalCompletedPieces() == 0`

Si un lote llega a la cola con `ready_for_shipping = true` pero sin ciclos registrados y con `closure_decision` null, la columna mostrara 0. Este es un estado inconsistente del dato anterior al cambio, no generado por este cambio.

**Nivel de riesgo: BAJO.** El valor 0 era igualmente visible con la logica anterior.

### Riesgo 5: Performance (N+1 queries) en Shipping List

`ShippingQueue` tiene los eager loads correctos implementados. La `ShippingList` llama a `getTotalCompletedPieces()` via `$wo->lots` (relacion). Debe verificarse que el componente `ShippingListDisplay.php` carga los lotes con `completionLogs` y `packagingRecords` en el eager load del render, de lo contrario se generarian N+1 queries en la vista.

**Nivel de riesgo: CONTROLABLE** si se verifica y actualiza el eager load en `ShippingListDisplay.php`.

---

## Diagrama de Flujo: Antes vs Despues

```
ANTES:
packaging_records.packed_pieces (ciclo actual)
    → LotPackagingObserver
    → lots.quantity_packed_final
    → ShippingQueue (QTY EMPACADA display)
    → PackingSlipItem.quantity_packed (snapshot)
    → InvoiceItem.quantity (snapshot de snapshot)
    → PDF Packing Slip (lee quantity_packed)
    → PDF Invoice (lee invoice_items.quantity)

DESPUES:
lot_completion_logs.packed_pieces (ciclos 1..N-1)
    +
packaging_records.packed_pieces (ciclo final)
    → Lot::getTotalCompletedPieces()
    → ShippingQueue (QTY EMPACADA display) [IMPLEMENTADO]
    → PackingSlipItem.quantity_packed (snapshot) [IMPLEMENTADO]
    → InvoiceItem.quantity (snapshot de snapshot) [SIN CAMBIO REQUERIDO]
    → PDF Packing Slip (lee quantity_packed) [SIN CAMBIO REQUERIDO]
    → PDF Invoice (lee invoice_items.quantity) [SIN CAMBIO REQUERIDO]
```

---

## Plan de Implementacion

### Cambios ya implementados (confirmados por revision de codigo)

1. `ShippingQueue.php` linea 176: eager load `completionLogs` en `createPackingSlip()`.
2. `ShippingQueue.php` linea 224: snapshot `quantity_packed = getTotalCompletedPieces()`.
3. `ShippingQueue.php` lineas 452-453: eager load `completionLogs`, `packagingRecords` en `render()`.
4. `ShippingQueue.php` linea 492: eager load `completionLogs`, `packagingRecords` para `$returningLot`.
5. `shipping-queue.blade.php` linea 207: display `getTotalCompletedPieces()` en tabla principal.
6. `shipping-queue.blade.php` linea 315: display `getTotalCompletedPieces()` en modal devolucion.
7. `shipping-queue.blade.php` linea 447: display `getTotalCompletedPieces()` en modal confirmacion.
8. `shipping-list-display.blade.php` linea 285: `$woCompletedPieces` via `getTotalCompletedPieces()`.
9. `shipping-list-display.blade.php` lineas 994-1013: ciclos por lote con badges C1..Cn + Sigma.

### Acciones pendientes

#### Accion 1 (OBLIGATORIA): Verificar eager loading en ShippingListDisplay.php

**Archivo:** `app/Livewire/Admin/SentLists/ShippingListDisplay.php`

Verificar que el metodo `render()` incluya `completionLogs` y `packagingRecords` en el eager load de los lotes de cada WO. Si no estan, agregar:

```php
// En el with() de workOrders o de lots, segun la estructura del componente
->with(['lots.completionLogs', 'lots.packagingRecords'])
```

De lo contrario, `getTotalCompletedPieces()` genera N+1 queries al iterar los lotes en la Lista de Envio.

#### Accion 2 (DECISION DE NEGOCIO): Columna "Cant. Pendiente"

Definir si la columna actualmente llamada "Cant. Pendiente" (antes "Cant. a Enviar") debe:
- **Mantenerse** con el nombre "Cant. Pendiente" y calculo `$cantWO - $pzEnviadas`.
- **Eliminarse** si el negocio la considera redundante con "Pz Enviadas" y "Cant. WO".

Si se elimina, los archivos afectados son unicamente `shipping-list-display.blade.php` (no hay impacto en PHP ni en BD).

#### Accion 3 (VERIFICACION): Test de regresion para lotes con ciclos multiples

1. Crear un lote de prueba con al menos un ciclo de "Completar Lote" para verificar que `getTotalCompletedPieces()` retorna el acumulado correcto.
2. Verificar que la cola ShippingQueue muestra el valor acumulado.
3. Crear un PackingSlip desde la cola y confirmar que `packing_slip_items.quantity_packed` contiene el valor de `getTotalCompletedPieces()`.
4. Generar el PDF del Packing Slip y confirmar que la columna "Quantity" muestra el valor correcto.
5. Crear un Invoice desde ese PS y confirmar que `invoice_items.quantity` y `invoice_items.line_total` son correctos.
6. Generar el PDF del Invoice y confirmar que la columna "QUANTITY" y el "TOTAL" por linea son correctos.
7. Confirmar que para lotes sin ciclos extras, todos los valores son identicos a los anteriores.

---

## Verificacion Completa de la Cadena Invoice (2026-05-21)

### Verificacion 1: Vista del Invoice en el sistema (InvoiceShow)

**Componente:** `app/Livewire/Admin/Invoices/InvoiceShow.php`
**Vista activa:** `resources/views/livewire/admin/invoices/invoice-show-v2.blade.php`

El componente `InvoiceShow` carga el Invoice con:

```php
$invoice->load([
    'packingSlip',
    'productItems',
    'chargeItems.chargeType',
    'creator',
    'issuer',
]);
```

La vista `invoice-show-v2.blade.php` muestra la cantidad en la linea 526:

```blade
<td ...>{{ number_format((int) $item->quantity) }}</td>
```

Y el total general en linea 654:

```blade
<span ...>{{ number_format($invoice->total_quantity) }}</span>
```

**Conclusion:** La vista Livewire del Invoice lee exclusivamente `invoice_items.quantity` (snapshot inmutable). No hay ningun calculo en tiempo real desde lotes ni desde PackagingRecords. La cantidad mostrada en la UI del sistema es identica a la del PDF. No se requiere ningun cambio.

### Verificacion 2: InvoiceFromPackingSlipService — Origen del campo quantity

Archivo: `app/Services/InvoiceFromPackingSlipService.php`

Hallazgos verificados en el codigo fuente:

1. **Campo `quantity` del InvoiceItem** (linea 182):
   ```php
   'quantity' => $psItem->quantity_packed,
   ```
   Se copia directamente de `packing_slip_items.quantity_packed`. Si ese snapshot fue creado con `getTotalCompletedPieces()` (nuevo comportamiento), el Invoice hereda el valor correcto.

2. **Campo `line_total`** (lineas 157-159):
   ```php
   $lineTotal = round(
       (float) bcmul((string) $psItem->quantity_packed, $unitCost, 6),
       2
   );
   ```
   Tambien usa `$psItem->quantity_packed` como base. Correcto: refleja el total de piezas completadas.

3. **Determinacion del item dominante** (linea 357):
   ```php
   $dominantItem = $ps->items->sortByDesc('quantity_packed')->first();
   ```
   Usa el valor ya snapshot en `packing_slip_items.quantity_packed`, no recalcula desde lotes. Correcto.

4. **Precio por tier**: El tier se determina usando `$po->quantity` (cantidad de la PO), no la cantidad empacada. Esto es independiente del cambio y correcto.

**Conclusion:** El servicio es correcto. La unica fuente de cantidad es `$psItem->quantity_packed`, que es el snapshot del Packing Slip. Ningun campo adicional (subtotal, line_total, tot_quantity) se calcula independientemente desde los lotes.

### Verificacion 3: Vista del Shipping List — Seccion de Invoice

La busqueda en `shipping-list-display.blade.php` confirma que **no existe ninguna seccion de Invoice en esa vista**. La vista de la Shipping List solo muestra el flujo operativo de lotes (produccion, calidad, empaque, semaforos). El Invoice es un documento separado accedido desde `admin/invoices/{invoice_number}`.

Las cantidades de lotes en la Shipping List (lineas 984, 996, 285) usan:
- `$lot->quantity` para la cantidad del lote (campo de la tabla `lots`, inmutable durante el ciclo actual).
- `$lot->getCompletionCycles()` y `$lot->getTotalCompletedPieces()` para Pz Completadas.

Ninguna de estas columnas tiene relacion directa con el Invoice.

### Verificacion 4: PDF Invoice (FPL-12)

Archivo: `resources/views/pdf/invoice.blade.php`

- Columna QUANTITY por item (linea 533): `{{ number_format($item->quantity) }}` — lee `invoice_items.quantity` (snapshot).
- Total QUANTITY general (linea 561): `{{ number_format((int) $invoice->total_quantity) }}` — lee campo calculado del modelo Invoice.

El servicio `InvoicePdfService::generate()` pasa al blade:
- `$productItems`: coleccion de `InvoiceItem` con `is_fixed_charge = false`, cargados via `$invoice->items`.
- `$fixedChargeItems`: coleccion de cargos fijos.
- `$invoice`: el modelo Invoice completo.

No hay acceso a `PackagingRecord`, `LotCompletionLog` ni a ningun metodo como `getTotalCompletedPieces()` en la capa del PDF. Todo es snapshot.

### Verificacion 5: Eager Loading en ShippingListDisplay — Riesgo 5 resuelto

El metodo `render()` de `ShippingListDisplay.php` (lineas 2210-2220) incluye:

```php
$query = WorkOrder::with([
    'purchaseOrder.part.standards' => ...,
    'lots.weighings',
    'lots.qualityWeighings',
    'lots.packagingRecords',
    'lots.kits',
    'lots.completionLogs',   // <-- PRESENTE
    'sentList'
]);
```

**El eager load de `completionLogs` esta correctamente implementado.** Sin embargo, se detecta una omision: **`lots.packagingRecords` esta cargado** pero no se ve en la llamada `with()` de la linea real — verificar que sigue incluido ya que `getTotalCompletedPieces()` lo necesita para el ciclo final cuando `closure_decision != null`.

Revision del codigo fuente en lineas 2214-2218 confirma:

```
'lots.packagingRecords',   // PRESENTE — necesario para ciclo final en getTotalCompletedPieces()
'lots.completionLogs',     // PRESENTE — necesario para ciclos previos en getTotalCompletedPieces()
```

**El Riesgo 5 (N+1 queries) esta RESUELTO.** Ambas relaciones requeridas por `getTotalCompletedPieces()` estan incluidas en el eager load del `render()`.

### Conclusion de la Verificacion

| Punto de la cadena | Archivo | Campo | Fuente | Estado |
|---|---|---|---|---|
| Vista Livewire Invoice (UI) | `invoice-show-v2.blade.php` linea 526 | `$item->quantity` | `invoice_items.quantity` (snapshot) | CORRECTO |
| Total piezas en UI | `invoice-show-v2.blade.php` linea 654 | `$invoice->total_quantity` | Campo calculado del modelo | CORRECTO |
| Servicio creacion Invoice | `InvoiceFromPackingSlipService.php` linea 182 | `quantity` | `$psItem->quantity_packed` (snapshot PS) | CORRECTO |
| line_total en Invoice | `InvoiceFromPackingSlipService.php` linea 158 | `line_total` | `bcmul($psItem->quantity_packed, $unitCost)` | CORRECTO |
| Shipping List (UI) | `shipping-list-display.blade.php` linea 285 | `$woCompletedPieces` | `getTotalCompletedPieces()` en tiempo real | CORRECTO |
| Shipping List por lote | `shipping-list-display.blade.php` linea 996 | `$lotCompletedTotal` | `getCompletionCycles()` en tiempo real | CORRECTO |
| PDF Invoice (FPL-12) | `pdf/invoice.blade.php` linea 533 | `$item->quantity` | `invoice_items.quantity` (snapshot) | CORRECTO |
| PDF Invoice total | `pdf/invoice.blade.php` linea 561 | `$invoice->total_quantity` | Campo calculado del modelo | CORRECTO |
| Eager load ShippingListDisplay | `ShippingListDisplay.php` lineas 2214-2218 | N/A | `lots.completionLogs`, `lots.packagingRecords` incluidos | CORRECTO |

**La cadena completa `getTotalCompletedPieces() → PS.quantity_packed → InvoiceItem.quantity → PDF/Vista` es correcta en todos sus eslabones.**

No se requiere ningun cambio adicional en el Invoice ni en sus vistas. El unico punto pendiente sigue siendo la Accion 2 (decision de negocio sobre la columna "Cant. Pendiente") documentada en la seccion de Acciones Pendientes.

---

## Archivos de Referencia

| Archivo | Ruta Absoluta |
|---|---|
| Componente Livewire ShippingQueue | `C:\xampp\htdocs\flexcon-tracker\app\Livewire\Admin\Shipping\ShippingQueue.php` |
| Vista ShippingQueue | `C:\xampp\htdocs\flexcon-tracker\resources\views\livewire\admin\shipping\shipping-queue.blade.php` |
| Componente Livewire ShippingListDisplay | `C:\xampp\htdocs\flexcon-tracker\app\Livewire\Admin\SentLists\ShippingListDisplay.php` |
| Vista ShippingListDisplay | `C:\xampp\htdocs\flexcon-tracker\resources\views\livewire\admin\sent-lists\shipping-list-display.blade.php` |
| Componente Livewire InvoiceShow | `C:\xampp\htdocs\flexcon-tracker\app\Livewire\Admin\Invoices\InvoiceShow.php` |
| Vista InvoiceShow (activa) | `C:\xampp\htdocs\flexcon-tracker\resources\views\livewire\admin\invoices\invoice-show-v2.blade.php` |
| Controlador PDF Packing Slip | `C:\xampp\htdocs\flexcon-tracker\app\Http\Controllers\PackingSlipPdfController.php` |
| Vista PDF Packing Slip (FPL-10) | `C:\xampp\htdocs\flexcon-tracker\resources\views\pdf\packing-slip.blade.php` |
| Servicio PDF Invoice | `C:\xampp\htdocs\flexcon-tracker\app\Services\InvoicePdfService.php` |
| Vista PDF Invoice (FPL-12) | `C:\xampp\htdocs\flexcon-tracker\resources\views\pdf\invoice.blade.php` |
| Servicio InvoiceFromPackingSlip | `C:\xampp\htdocs\flexcon-tracker\app\Services\InvoiceFromPackingSlipService.php` |
| Modelo Lot | `C:\xampp\htdocs\flexcon-tracker\app\Models\Lot.php` |
| Modelo PackingSlipItem | `C:\xampp\htdocs\flexcon-tracker\app\Models\PackingSlipItem.php` |
| Observer LotPackagingObserver | `C:\xampp\htdocs\flexcon-tracker\app\Observers\LotPackagingObserver.php` |
| Migracion packing_slip_items | `C:\xampp\htdocs\flexcon-tracker\database\migrations\2026_03_08_100003_create_packing_slip_items_table.php` |
