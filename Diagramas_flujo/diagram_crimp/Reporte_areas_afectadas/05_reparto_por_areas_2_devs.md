# 05 — Reparto de trabajo por ÁREA (2 desarrolladores)

> **Alcance:** únicamente el **reajuste del proceso de CRIMP**. No incluye otras features.
> **División vigente** (reemplaza al `04`, que dividía por flujo).
>
> - **Mauricio Belmonte → TODO el Sent List** (todo el reajuste de CRIMP, de principio a fin).
> - **Josadec → Capacidad + Shipping List (FPL-10) + Invoice (FPL-12).**
>
> Regla simple: **si vive dentro del Sent List, es de Mauricio.** Capacidad, Shipping List e Invoice
> son módulos aparte → Josadec.

> **✅ Base lista — Josadec puede arrancar Capacidad.**
> - Migración `crimp_lots` + modelo `CrimpLot` + relación `Lot::crimpLots()` + helper `Lot::isViajero()` → **ya creados** (M1 base). Falta solo `php artisan migrate` (con MySQL arriba).
> - **Decisión B.1 cerrada → Opción A:** cada lote de CRIMP cuelga **de su viajero** (`crimp_lots.lot_id` → `Lot`). La UI del Step 3 arranca con **1 viajero por defecto**, así el caso simple queda igual de simple.

---

## 1. Resumen de la división

| | **MAURICIO** | **JOSADEC** |
| --- | --- | --- |
| **Área** | **Todo el Sent List (reajuste CRIMP)** | **Capacidad · Shipping List · Invoice** |
| Módulos | M1, M2, M4, M5, M6, M7, M8, M9, M10 | Capacity Wizard · Packing Slips (FPL-10) · Invoices (FPL-12) |

**Modelo del flujo CRIMP** (aplica a todo el documento): **Viajero (`Lot`) → N Lotes de CRIMP (`crimp_lots`)**,
cada lote de CRIMP con su **lote de fabricante**. Toda la lógica nueva aplica **solo a partes con CRIMP**
(`parts.is_crimp = true`); las partes sin CRIMP conservan su flujo actual.

> Nota de nombres: el componente
> [`SentLists\ShippingListDisplay`](../../../app/Livewire/Admin/SentLists/ShippingListDisplay.php)
> (ruta `admin.sent-lists.display`) se llama "shipping" pero es el **tablero de semáforos del Sent List**
> → es de **Mauricio**. El "Shipping List" de Josadec es el módulo **FPL-10 / Packing Slip**
> (`admin.shipping-list.*`).

---

## 2. Cómo usar este documento

Cada dev arranca **aquí**, ubica su área, y salta a los documentos técnicos ya escritos:

- [`01_modulos_afectados.md`](01_modulos_afectados.md) — qué hace hoy cada módulo, qué cambia, archivo:línea.
- [`02_tablas_modelos_rutas.md`](02_tablas_modelos_rutas.md) — tablas, modelos, rutas y vistas a tocar.
- [`03_riesgos_y_decisiones.md`](03_riesgos_y_decisiones.md) — riesgos, decisiones abiertas y checklist.

---

## 3. MAURICIO — Todo el Sent List (reajuste CRIMP)

| Módulo | Lógica CRIMP a implementar | Archivos núcleo |
| ------ | -------------------------- | --------------- |
| **M1 🔴** Materiales | Crear **viajero** + **lotes de CRIMP** (+ lote de fabricante); liberar material a nivel viajero. **Aquí nace el modelo base `CrimpLot`** (migración `crimp_lots` + relación `Lot::crimpLots()`). | [`SentListMaterialsView.php`](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php), `materials-view.blade.php`, nueva migración/modelo `CrimpLot` |
| **M2 🟠** Gestión de materiales | Gestionar los **lotes de CRIMP** del viajero (alta/edición). | [`KitManagement.php`](../../../app/Livewire/Admin/Materials/KitManagement.php) |
| **M4 🟠** Pesada Producción | Pesar **a nivel viajero**. | [`WeighingManagement.php`](../../../app/Livewire/Admin/Production/WeighingManagement.php) |
| **M5 🟠** Pesada Calidad | Pesar **a nivel viajero**. | [`QualityWeighings.php`](../../../app/Livewire/Admin/Quality/QualityWeighings.php) |
| **M6 🔴** Empaque | **2 pesadas** (piezas + CRIMP) con sobrantes; confirmación; decisiones **D1 / D2a-c / D3** sobre `crimp_lots`. | [`SentListPackagingView.php`](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php), `packaging-view.blade.php` |
| **M7 🟠** Inspección | Gate de inspección **a nivel viajero**. | [`Lot.php`](../../../app/Models/Lot.php), [`InspectionList.php`](../../../app/Livewire/Admin/Inspection/InspectionList.php) |
| **M8 🟠** Semáforos / TV / Dashboards | Columna de Materiales/Viajero por **estado de liberación del viajero**. | [`TvMonitor.php`](../../../app/Livewire/Public/TvMonitor.php), [`ComputesAreaStats.php`](../../../app/Traits/ComputesAreaStats.php), dashboards de área |
| **M9 🔴** Correo | Mailable + template **"Empaque terminado CRIMP Viajero"**. | nuevo `app/Mail/EmpaqueTerminadoCrimpViajero.php` |
| **M10 🟢-🟠** Reportes / Trazabilidad | Trazabilidad **viajero → lotes de CRIMP**; reetiquetar "Lote" → "Viajero". | `Lot::getTraceabilityData()`, `LotShow`, reportes |

**Documentos a leer:** `01` (M1–M10), `02` (tablas/modelos), `03` (riesgos y decisiones).

---

## 4. JOSADEC — Capacidad · Shipping List · Invoice

### 4.1 Capacidad — Capacity Wizard 🔴

**Archivos:** [`CapacityWizard.php`](../../../app/Livewire/Admin/CapacityWizard.php),
`resources/views/livewire/admin/capacity-wizard/step3.blade.php`
**Ruta:** `admin.capacity.wizard`
**Documento base a leer:** [`01_modulos_afectados.md` → sección **M3 · Capacity Wizard**](01_modulos_afectados.md)
+ [`02` (tabla `crimp_lots`)](02_tablas_modelos_rutas.md) + [`03 §B.1` (decisiones del viajero / lotes de CRIMP)](03_riesgos_y_decisiones.md).

CRIMP cruza el wizard en **3 puntos** (no uno). Hoy el wizard trabaja con **kits**; debe pasar a **viajero + lotes de CRIMP**:

| Paso | Qué hace hoy (kits) | Qué debe hacer (CRIMP) | Referencia |
| ---- | ------------------- | ---------------------- | ---------- |
| **Step 2** — Cálculo de horas / agregar POs | Cada item de la lista preliminar ya carga el flag `is_crimp`. | **Sin cambio funcional**: el flag `is_crimp` sigue siendo el discriminador que habilita la captura del paso 3. | [`CapacityWizard.php:418-438`](../../../app/Livewire/Admin/CapacityWizard.php#L418) (`is_crimp` en `:422`) |
| **Step 3** — Lista Preliminar | Captura **kits** por PO crimp (`kitNumbers`, modal de kit con número + cantidad) junto a los lotes. | **Opción A:** capturar lotes de CRIMP **anidados por viajero** — por cada lote/viajero, sus N lotes de CRIMP (número + **lote de fabricante** + cantidad + comentarios). | modal de kit [`:618-690`](../../../app/Livewire/Admin/CapacityWizard.php#L618); validación CAP-1 [`validateLotKitQuantities():697`](../../../app/Livewire/Admin/CapacityWizard.php#L697); vista `step3.blade.php` |
| **Creación final** — `createSentList()` | Para crimp, **crea registros `Kit`** y los asocia a **todos** los lotes (`syncWithoutDetaching`). | **Opción A:** tras crear cada `Lot` (viajero), crear **sus** lotes de CRIMP → `CrimpLot::create(['lot_id' => $newLot->id, ...])`. **No** crear kits ni sincronizar a todos. | [`:866-900`](../../../app/Livewire/Admin/CapacityWizard.php#L866) |

> **Dependencia (ya resuelta):** los pasos 3 y creación final consumen el modelo `CrimpLot` — **ya creado**
> por Mauricio con la firma `lot_id, crimp_lot_number, lote_fabricante, quantity, comments`. Josadec puede
> cerrar la persistencia de una vez; no hay nada que esperar.

### 4.2 Shipping List — FPL-10 (Packing Slip)

**Archivos:** `PackingSlips\PackingSlipList/Create/Show`, `Shipping\ShippingQueue`,
[`PackingSlip.php`](../../../app/Models/PackingSlip.php), `InvoiceFromPackingSlipService`
**Rutas:** `admin.shipping-list.*` / `admin.shipping.queue`
**Documentos a leer:** [`Shipping_list_analysis/`](../../Estructura/docs/ef/Shipping_list_analysis/01_shipping_list_analysis.md)
(docs 01, 03–08, 10, 11).

### 4.3 Invoice — FPL-12

**Archivos:** `Invoices\InvoiceList/Show`, [`InvoiceController.php`](../../../app/Http/Controllers/InvoiceController.php),
[`Invoice.php`](../../../app/Models/Invoice.php), `InvoiceItem`, `InvoiceChargeType`, `InvoicePdfService`,
`InvoiceDeleteService`, `config/invoice.php`
**Rutas:** `admin.invoices.*`
**Documentos a leer:** [`02_invoice_analysis.md`](../../Estructura/docs/ef/Shipping_list_analysis/02_invoice_analysis.md),
[`09_fpl12...`](../../Estructura/docs/ef/Shipping_list_analysis/09_fpl12_invoice_analisis_implementacion.md),
[`12_invoice...`](../../Estructura/docs/ef/Shipping_list_analysis/12_invoice_fpl12_analisis_implementacion.md),
[`Invoice_analysis/`](../../Estructura/docs/Invoice_analysis/00_flujo_correccion_ps_con_invoice.md).

---

## 5. Único punto de contacto entre ambos

Todo el Sent List es de Mauricio, así que no se reparten archivos compartidos. Las únicas costuras:

- **Capacidad ↔ CRIMP (resuelto):** el Capacity Wizard consume `CrimpLot` (**ya creado**: `lot_id`,
  `crimp_lot_number`, `lote_fabricante`, `quantity`, `comments`). **Decisión B.1 = Opción A:** cada lote de
  CRIMP cuelga de su viajero (`lot_id`). Josadec no lo redefine, solo lo usa.
- **Empaque → Shipping List:** el Packing Slip (Josadec) toma como insumo los **lotes ya completados/
  empacados** que produce el Empaque (Mauricio). Es una entrega de datos, no de código compartido.

Fuera de eso, ambos tracks corren en paralelo sin esperarse.

---

## 6. Orden de arranque sugerido para Josadec

> **Base lista + decisión A tomada → puedes empezar.** Solo corre `php artisan migrate` (con MySQL arriba).

1. Leer este `05` (panorama + tu área).
2. **Capacidad:** leer `01 §M3` + `02` (tabla `crimp_lots`) + `03 §B.1`.
3. Adaptar **Step 2** y la **UI del Step 3**: lotes de CRIMP **anidados por viajero** (Opción A) con lote de fabricante; la UI defaultea a 1 viajero.
4. Cerrar la **persistencia** en `createSentList()`: por cada `Lot` creado → `CrimpLot::create(['lot_id' => $newLot->id, ...])`. El modelo ya existe; nada que esperar.
5. **Shipping List (FPL-10)** e **Invoice (FPL-12):** independientes del Sent List; leer sus análisis (§4.2, §4.3).

> Referencia técnica de los módulos M1–M10: `01` / `02` / `03`. El `04` (división por flujo) fue eliminado.
