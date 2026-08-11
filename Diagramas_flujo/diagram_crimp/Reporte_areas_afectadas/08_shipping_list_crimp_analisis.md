# 08 — Análisis: Shipping List / Packing Slip (FPL-10) y los cambios para CRIMP

> **Dueño:** Josadec · **Módulo:** Shipping List / Packing Slip (FPL-10).
> **Tipo:** Análisis técnico de impacto (no contiene código aplicado).
> **Fecha:** 2026-06-21.
> **Regla de oro:** la lógica nueva aplica **solo a partes con CRIMP** (`parts.is_crimp = true`);
> las partes sin CRIMP conservan **exactamente** el flujo actual del Packing Slip.
> **Leyenda:** ✅ = verificado en código · 🟡 = suposición / decisión abierta.

---

## 0. Resumen

El módulo **Packing Slip (FPL-10)** hoy **no conoce el concepto de CRIMP**: imprime una fila por lote sin
desglose. La nueva forma, **solo para partes crimp**, debe reflejar el desglose **Viajero → Lotes de CRIMP**
(con `lote_fabricante`) en la vista de detalle y, sobre todo, en el **PDF del packing slip**, replicando el
formato que **ya existe** en el PDF de Lista de Envío **FPL-02** (precedente directo).

> ✅ **Buena noticia para Josadec:** no hay dependencia de **código** que bloquee el arranque — el módulo está
> totalmente desacoplado de CRIMP hoy. La única dependencia real es de **datos** (cantidades empacadas de CRIMP
> que produce Empaque / M6 de Mauricio) y de **decisiones de negocio** con el cliente. Ver §6.

> 🔓 **Actualización 2026-06-24 — candado M7 resuelto.** El gate `Lot::canBeInspected()` ya no exige `Kit
> released` (commit `b1a3083`, ya en `main_jos`); el Capacity Wizard CRIMP quedó desbloqueado para merge. No
> afecta a FPL-10 (que ya estaba desacoplado), pero elimina el riesgo R2 que arrastraban los docs 07/08.

---

## 1. Mapeo de conceptos

| Concepto del cliente | Entidad / tabla real | Nota |
| -------------------- | -------------------- | ---- |
| **Viajero** | `Lot` (`lots`) | mismo "lote", llamado "viajero" solo en crimp. Es lo que el Packing Slip ya consume. |
| **Lote de CRIMP** | `crimp_lots` (hija del viajero) | `lot_id`, `crimp_lot_number`, `lote_fabricante`, `quantity`, `comments`. Relación `Lot::crimpLots()`. |
| **Lote de fabricante** | `crimp_lots.lote_fabricante` | texto manual capturado en Capacidad/Materiales; debe poder mostrarse en el packing slip. |

Relación: **Viajero (`Lot`) 1 → N Lotes de CRIMP (`crimp_lots`)**. El Packing Slip toma `Lot`s "listos para
envío"; para crimp debe poder expandir cada `Lot` en sus `crimpLots`.

---

## 2. (a) Vieja forma / estado actual del Packing Slip (FPL-10)

✅ **Cero referencias a CRIMP en todo el módulo.** Verificado: `PackingSlip.php`, `PackingSlipItem.php`, los
componentes `PackingSlips\{List,Create,Show}`, `Shipping\ShippingQueue.php` y el PDF
`resources/views/pdf/packing-slip.blade.php` **no** mencionan `crimp` / `CrimpLot` / `is_crimp` / `viajero`.

### 2.1 Cómo llega un lote al Packing Slip

- ✅ `Lot::scopeReadyForShipping()` ([app/Models/Lot.php](../../../app/Models/Lot.php) ~L218-222):
  `ready_for_shipping = true` **AND** `whereDoesntHave('packingSlipItem')` (un lote sin packing slip aún).
- ✅ La cantidad mostrada se toma de `Lot::getTotalCompletedPieces()` (~L788) o de `quantity_packed_final`.
- ✅ El Packing Slip agrupa sus items por PO en `PackingSlipShow::render()` (~L305-307).

### 2.2 PDF actual

- ✅ `resources/views/pdf/packing-slip.blade.php`: imprime **una fila por item/lote** (~L457-471),
  **sin desglose** de sub-lotes ni `lote_fabricante`.

### 2.3 Componentes y modelos

| Capa | Archivo | Rol actual |
| ---- | ------- | ---------- |
| Livewire | `app/Livewire/Admin/PackingSlips/PackingSlipList.php` | Listado de packing slips. |
| Livewire | `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php` | Alta; selecciona lotes `readyForShipping`. |
| Livewire | `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | Detalle; agrupa items por PO (~L305-307); eager-load de items (~L324). |
| Livewire | `app/Livewire/Admin/Shipping/ShippingQueue.php` | Cola de lotes listos para envío. |
| Modelo | [app/Models/PackingSlip.php](../../../app/Models/PackingSlip.php) | Cabecera del packing slip. |
| Modelo | `app/Models/PackingSlipItem.php` | Línea → `Lot` / WO / Part. |
| PDF | `resources/views/pdf/packing-slip.blade.php` | Plantilla del PDF FPL-10. |
| Rutas | `admin.shipping-list.*`, `admin.shipping.queue` (routes/admin.php) | Acceso. |

---

## 3. ⭐ Precedente ya existente: el PDF FPL-02 (Lista de Envío)

✅ **El formato CRIMP que necesita FPL-10 ya está resuelto en FPL-02.** El export de "Lista de Envío"
**ya implementa el breakdown viajero → lotes de CRIMP**:

- `SentListController::exportPdf()` (~L39-95) → vista `resources/views/sent-lists/pdf/shipping-list.blade.php`.
- Condiciona por `$isCrimp` (~L103); rotula **`'Viajero'`** vs **`'Lote'`** (~L108).
- Por cada `lot->crimpLots` imprime `crimp_lot_number`, `lote_fabricante` y `quantity` (~L129-171).

> **Este es el patrón a replicar en el packing slip FPL-10.** Reduce mucho el riesgo: el desglose, los rótulos
> condicionados y el recorrido de `crimpLots` ya están escritos y probados en otra plantilla del mismo proyecto.

---

## 4. (b) Nueva forma (solo CRIMP) — qué debe cambiar en FPL-10

Todo condicionado por `part->is_crimp`. NO-CRIMP: sin cambios.

| Zona | Archivo / línea | Cambio propuesto |
| ---- | --------------- | ---------------- |
| PDF — fila por item | `resources/views/pdf/packing-slip.blade.php` (~L457-471) | Si la parte es crimp, añadir **sub-filas Viajero → Lotes de CRIMP** con `crimp_lot_number` + `lote_fabricante` + `quantity`, calcando FPL-02. 🟡 Confirmar nivel de detalle con cliente. |
| Eager-load | `PackingSlipShow.php` (~L324) y el controlador/servicio del PDF | Añadir `items.lot.crimpLots` al eager-load para crimp. |
| Rótulo detalle | vista de detalle del packing slip | "Lote" → "Viajero" **solo** cuando la parte es crimp. |
| Datos de cantidad | (depende de M6) | 🟡 La **cantidad empacada real por lote de CRIMP** la produce Empaque (Mauricio). Mientras no exista, el PS puede mostrar el desglose **capturado en Capacidad** (`crimp_lots.quantity`). |

---

## 5. Inventario: tablas, modelos, rutas y vistas afectadas

| Capa | Elemento | Cambio |
| ---- | -------- | ------ |
| **Tablas** | `crimp_lots` (existente) | Solo lectura desde el PS. **Sin** cambios de esquema. |
| **Tablas** | `packaging_*_weighings` (de M6, aún no existen) | 🟡 Futura fuente de la cantidad empacada de CRIMP por lote. |
| **Modelos** | `PackingSlipItem` → `lot` → `crimpLots` | Asegurar que la cadena de relaciones está disponible (vía `Lot::crimpLots()`). |
| **Componentes** | `PackingSlipShow` | Eager-load `items.lot.crimpLots`; rótulos condicionados. |
| **PDF** | `pdf/packing-slip.blade.php` | Sub-filas de lotes de CRIMP (patrón FPL-02). |
| **Rutas** | `admin.shipping-list.*` | Sin rutas nuevas. |

---

## 6. Riesgos y decisiones abiertas

| # | Riesgo / decisión | Detalle | Mitigación |
| - | ----------------- | ------- | ---------- |
| R1 | **No romper NO-CRIMP** | El PDF y el detalle son compartidos. | Todo el desglose dentro de `@if ($isCrimp)`; regresión con una parte `is_crimp = false`. |
| R2 | **Dependencia de datos con Empaque (M6 / Mauricio)** | La cantidad **empacada real** por lote de CRIMP la produce Empaque; aún no existen `packaging_*_weighings`. | Arrancar mostrando el desglose **capturado en Capacidad** (`crimp_lots.quantity`); integrar la cantidad empacada cuando M6 esté listo. **No bloquea el arranque.** |
| R3 | 🟡 **Decisión de cliente: ¿qué info CRIMP va en el packing slip?** | ¿`lote_fabricante` visible? ¿desglose/agrupación por viajero como FPL-02? ¿qué cantidad (capturada vs empacada)? | Confirmar antes de cerrar el PDF. |

### Decisiones abiertas (para el cliente)
- 🟡 ¿El packing slip FPL-10 debe mostrar el desglose viajero → lotes de CRIMP, igual que el FPL-02?
- 🟡 ¿`lote_fabricante` visible en el PDF del packing slip?
- 🟡 ¿La cantidad por lote de CRIMP en el PS es la **capturada** (Capacidad) o la **empacada** (Empaque/M6)?

---

## 7. Checklist de archivos a tocar y orden sugerido

- [x] **Patrón de referencia LISTO** en `resources/views/sent-lists/pdf/shipping-list.blade.php` (FPL-02) — ya
  implementado y probado (L128-178: `@if ($isCrimp)` → `@foreach ($lots)` → `@forelse ($crimps)`). Es la plantilla a calcar.
- [x] `app/Http/Controllers/PackingSlipPdfController.php` (`buildData()` ~L17) — eager-load `items.lot.crimpLots`. **Este alimenta el PDF.**
- [x] `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` — mismo eager-load `items.lot.crimpLots` (`loadMissing`); rótulos "Lote"→"Viajero".
- [x] `resources/views/pdf/packing-slip.blade.php` (~L457-471) — sub-filas viajero → lotes de CRIMP dentro de `@if ($isCrimp)`.
- [x] Regresión NO-CRIMP (parte `is_crimp = false`): packing slip y PDF idénticos. Cubierta por tests dedicados (ver abajo).

> ✅ **Implementado y probado (2026-06-24):** desglose viajero→lotes de CRIMP en pantalla y PDF del Packing Slip.
> Tests: `PackingSlipCrimpScreenTest`, `PackingSlipCrimpPdfTest`, `CrimpLifecycleOrderTest` — **9 passed / 36 assertions**.

> **Defaults de negocio adoptados (2026-06-24, a confirmar con cliente):** sí al desglose viajero→lotes de CRIMP,
> `lote_fabricante` visible, cantidad **capturada** (`crimp_lots.quantity`); integrar la empacada cuando M6 entregue.

**Orden sugerido:**
1. Confirmar decisiones §6 con el cliente.
2. Leer/replicar el patrón FPL-02.
3. Vista de detalle (rótulos + eager-load) → PDF.
4. Integrar cantidad empacada real cuando M6 (Mauricio) entregue `packaging_*_weighings`.
5. Regresión NO-CRIMP.

---

## 8. Referencias

- [`05_reparto_por_areas_2_devs.md`](05_reparto_por_areas_2_devs.md) — §4.2 (Shipping List / FPL-10), §5 (costura Empaque→Shipping).
- [`07_revision_capacity_y_arranque_josadec.md`](07_revision_capacity_y_arranque_josadec.md) — veredicto de arranque.
- Análisis de shipping: `Diagramas_flujo/Estructura/docs/ef/Shipping_list_analysis/` (docs 01, 03-08, 10, 11).
- Código: [`app/Models/Lot.php`](../../../app/Models/Lot.php) (`scopeReadyForShipping()` ~L218, `getTotalCompletedPieces()` ~L788, `crimpLots()`),
  [`app/Models/PackingSlip.php`](../../../app/Models/PackingSlip.php), `app/Models/PackingSlipItem.php`,
  `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` (~L305-307, ~L324),
  `app/Livewire/Admin/Shipping/ShippingQueue.php`,
  `resources/views/pdf/packing-slip.blade.php` (~L457-471),
  **precedente CRIMP:** `app/Http/Controllers/SentListController.php` (`exportPdf()` ~L39-95),
  `resources/views/sent-lists/pdf/shipping-list.blade.php` (~L103, L108, L129-171).
