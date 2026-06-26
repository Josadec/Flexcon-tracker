# 07 — Revisión del Capacity Wizard (Step 3) y arranque de Josadec

> **Dueño:** Josadec · **Módulos:** Capacidad (M3) · Shipping List (FPL-10) · Invoice (FPL-12).
> **Tipo:** Revisión técnica / verificación de código (no contiene código aplicado).
> **Fecha:** 2026-06-21.
> **Base:** verificación del código real contra [`06_plan_implementacion_capacity_step3.md`](06_plan_implementacion_capacity_step3.md)
> y el reparto de [`05_reparto_por_areas_2_devs.md`](05_reparto_por_areas_2_devs.md).
> **Regla de oro:** toda la lógica nueva aplica **solo a partes con CRIMP** (`parts.is_crimp = true`);
> las partes sin CRIMP conservan su flujo actual.

> ⚠️ **Nota de líneas:** el archivo `CapacityWizard.php` creció respecto al doc 06, por lo que las líneas
> reales difieren de las del plan. Las referencias de este documento son las **verificadas en el código actual**.

---

## A. Veredicto de la verificación del Step 3

**Implementación correcta y completa. Fiel al plan. Sin bugs bloqueantes.**

Se revisó punto por punto contra el doc 06. Todo lo especificado está implementado.

### A.1 `app/Livewire/Admin/CapacityWizard.php`

| Elemento | Línea (real) | Estado |
| -------- | ------------ | ------ |
| Import `CrimpLot` agregado · `Kit` eliminado del archivo | ~L6 | ✅ Cero referencias a `Kit` en el componente |
| Props nuevas: `crimpLots`, `showCrimpModal`, `currentCrimpIndex`, `tempCrimpLots`, `crimpModalError`, `crimpLotRefOptions` | ~L67-72 | ✅ Reemplazan a las de Kit |
| `is_crimp` conservado en el item preliminar | ~L430 | ✅ Discriminador intacto |
| Modal: `openCrimpModal` / `closeCrimpModal` / `addCrimpInput` / `removeCrimpInput` / `saveCrimpLots` + helper `lotRefOptionsForIndex` | ~L674 / L709 / L718 / L730 / L747 / L660 | ✅ Completo |
| Default 1 viajero (pre-rellena `lot_ref`, oculta selector) | ~L682, L720 | ✅ |
| `validateLotCrimpQuantities()`: cantidad ≥ 1 y `lot_ref` existente | ~L781 (L813, L818) | ✅ Bloque NO-crimp sin tocar |
| Persistencia: mapa `lotIdByNumber` (poblado L955 nuevos / L962 existentes) | ~L926 | ✅ Opción A — CrimpLot cuelga del `lot_id` del viajero |
| Bloque `CrimpLot::create` **dentro de `if ($isCrimp)`** | ~L968-996 (guard L970) | ✅ NO-CRIMP no entra |
| Manejo de huérfanos: `continue` si `!$lotId` | ~L979-981 | ✅ |
| Idempotencia por `(lot_id, crimp_lot_number)` | ~L983-987 | ✅ |
| Normalización de vacíos a `null` | ~L988-994 | ✅ |
| `resetWizard()` actualizado con las props nuevas, sin restos de Kit | ~L1038-1042 | ✅ |

### A.2 `resources/views/livewire/admin/capacity-wizard/step3.blade.php`

| Elemento | Línea (real) | Estado |
| -------- | ------------ | ------ |
| Header de columna "Lotes de CRIMP" | ~L149 | ✅ |
| Columna principal dinámica "Viajeros"/"Lotes" según `$listHasCrimp` | ~L146 | ✅ Extra sobre el plan, bien resuelto |
| Variables `@php` `$crimps` / `$crimpCount` | ~L158-159 | ✅ |
| Celda de chips solo crimp (number + lote_fabricante + (quantity)) + `@else N/A` | ~L208-240 | ✅ |
| Modal completo (selector viajero, número, lote_fabricante, cantidad, comentarios) | ~L418-563 | ✅ |

### A.3 Modelos y migración

- `app/Models/CrimpLot.php`: `$fillable` coincide con la firma; relaciones `lot()` y alias `viajero()`.
- `app/Models/Lot.php`: `crimpLots()` (~L163) e `isViajero()` (~L171) existen.
- Migración `2026_06_13_120000_create_crimp_lots_table.php`: firma idéntica a la documentada
  (`lot_id` FK cascade, `crimp_lot_number` indexado, `lote_fabricante`/`quantity`/`comments` nullable,
  timestamps, softDeletes).

### A.4 Regresión NO-CRIMP

La creación de `Lot` (~L927-966) **no se tocó**; todo lo nuevo vive dentro del `if ($isCrimp)`.
El flujo no-crimp queda idéntico.

### A.5 Hallazgos menores (NO bloquean — son decisiones de negocio)

| Severidad | Hallazgo | Detalle |
| --------- | -------- | ------- |
| 🟢 BAJA | `lote_fabricante` opcional | `saveCrimpLots` lo trata como opcional y la vista lo marca "(opcional)"; coherente con BD (nullable). **Decisión B.1 (¿obligatorio?) sigue abierta.** |
| 🟢 BAJA | Sin validación de cuadre CRIMP↔viajero | No se valida que la suma de lotes de CRIMP ≤ cantidad del viajero. Consistente con el plan ("opcional"). |
| 🔵 INFO | Selector `lot_ref` oculto | Usa `<input type="hidden" wire:model=...>` sin `value` (step3 ~L479); funciona porque se pre-rellena. Confirmar en prueba manual con 1 viajero que el `lot_ref` viaje al guardar. |
| 🔵 INFO | `crimp_lot_number` manual | No autogenerado (consistente con el plan). Decisión B.1 #2 abierta. |

**Conclusión A:** el Step 3 quedó bien. Solo restan confirmaciones de negocio (B.1), no correcciones de código.

---

## B. ¿Qué sigue? ¿Puede arrancarlo Josadec?

Según [`06 §7`](06_plan_implementacion_capacity_step3.md) y [`05 §6`](05_reparto_por_areas_2_devs.md), en Capacidad
solo queda:

1. **Prueba en dev:** crear una lista crimp → verificar filas en `crimp_lots` colgando del `lot_id` correcto
   → confirmar que `Lot::canBeInspected()` **aún devuelve `false`** (verifica el vínculo con el candado M7).
2. **Regresión NO-CRIMP** completa.

**Puede arrancar estas pruebas ahora mismo, en dev.**

> ✅ **Bloqueo de producción (riesgo R2) — RESUELTO (2026-06-24).** Mauricio ya reescribió el gate M7 en el
> commit `b1a3083` "work with proyect crimp", que entró a `main_jos` vía el merge `581583d` (b1a3083 es
> ancestro de HEAD). `Lot::canBeInspected()` ([app/Models/Lot.php](../../../app/Models/Lot.php) ~L518-521)
> **ya NO exige `Kit released`**: ahora evalúa `($this->material_status ?? 'pending') === 'released'` a nivel
> viajero, tanto para CRIMP como NO-CRIMP. El Capacity Wizard CRIMP **ya no está bloqueado para merge**.
> Validado en dev con `CrimpMaterialsInspectionTest` (gate M7) + `CrimpLifecycleOrderTest` (reorden del
> lifecycle post-calidad). Pendiente menor: ratificar con Mauricio que `b1a3083` es la versión definitiva.

---

## C. Arranque de Shipping List (FPL-10) e Invoice (FPL-12)

**Puede empezar ya. No dependen del track CRIMP de Mauricio.**

- **Cero acoplamiento con CRIMP (verificado):** ni `PackingSlip.php`, ni `Invoice.php`, ni los componentes
  Livewire de PackingSlips/Invoices contienen referencias a `crimp`, `CrimpLot`, `is_crimp` o `viajero`.
- La costura "Empaque → Shipping List" ([`05 §5`](05_reparto_por_areas_2_devs.md)) es una **entrega de datos**,
  no de código.
- **Punto de entrada recomendado:** **Invoice (FPL-12)** primero (menos incógnitas; el Packing Slip ya opera
  y los campos de precio ya existen "dormidos" en `packing_slip_items`). Lectura: doc 09 → doc 12 → doc 02 →
  `Invoice_analysis/00_...`. Para **FPL-10**, revisar gaps con los docs 07/11 del análisis de shipping antes de tocar código.
- El análisis específico de Shipping List + CRIMP está en [`08_shipping_list_crimp_analisis.md`](08_shipping_list_crimp_analisis.md).

---

## D. Resumen accionable

| Frente | Estado | Siguiente acción |
| ------ | ------ | ---------------- |
| **Capacidad (Step 3)** | ✅ Implementado y verificado | Validado en dev (tests CRIMP verdes). **Merge a prod DESBLOQUEADO** — M7 ya resuelto en `b1a3083` (§B). Solo ratificar con Mauricio. |
| **Invoice (FPL-12)** | Sin bloqueo | Arrancar por el doc 09 (entrada recomendada). |
| **Shipping List (FPL-10)** | Implementado en base | Revisar gaps (docs 07/11) + ver [`08`](08_shipping_list_crimp_analisis.md) para los cambios CRIMP. |

---

## E. Referencias

- [`00_README.md`](00_README.md) — resumen ejecutivo y mapa de módulos.
- [`01_modulos_afectados.md`](01_modulos_afectados.md) — M3 Capacity Wizard.
- [`02_tablas_modelos_rutas.md`](02_tablas_modelos_rutas.md) — tabla `crimp_lots`, modelo `CrimpLot`.
- [`03_riesgos_y_decisiones.md`](03_riesgos_y_decisiones.md) — §B.1, §A R1/R2.
- [`05_reparto_por_areas_2_devs.md`](05_reparto_por_areas_2_devs.md) — área de Josadec.
- [`06_plan_implementacion_capacity_step3.md`](06_plan_implementacion_capacity_step3.md) — plan implementado.
- [`08_shipping_list_crimp_analisis.md`](08_shipping_list_crimp_analisis.md) — análisis Shipping List + CRIMP.
- Código: [`app/Livewire/Admin/CapacityWizard.php`](../../../app/Livewire/Admin/CapacityWizard.php),
  [`resources/views/livewire/admin/capacity-wizard/step3.blade.php`](../../../resources/views/livewire/admin/capacity-wizard/step3.blade.php),
  [`app/Models/CrimpLot.php`](../../../app/Models/CrimpLot.php),
  [`app/Models/Lot.php`](../../../app/Models/Lot.php) (`canBeInspected()` ~L501 — candado M7),
  `database/migrations/2026_06_13_120000_create_crimp_lots_table.php`.
