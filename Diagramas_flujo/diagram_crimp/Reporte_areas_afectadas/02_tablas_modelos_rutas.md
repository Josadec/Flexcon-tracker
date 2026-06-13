# 02 — Inventario: Tablas, Modelos, Rutas y Vistas

> Inventario consolidado de **qué tocar exactamente**. Las decisiones de modelado marcadas con ⚠️
> deben confirmarse con el cliente (ver `03_riesgos_y_decisiones.md`).
>
> La columna **"Paso"** referencia el flujo de los **diagramas 1-2** (8 pasos):
> **1** crear viajero · **2** agregar lotes de CRIMP (+ lote de fabricante) · **3** entregar a Empaque ·
> **4** pesar CRIMP + piezas "manguitas" · **5** confirmar + correo · **6** decisión (D1/D2a-c/D3) ·
> **7** entrega de viajero · **8** regresar sobrantes. `§4`/`§5` = pesadas Producción/Calidad (`Flujo.mkd`);
> `T` = transversal (no es un paso único); `legacy` = deja de usarse en crimp.

---

## A. Tablas de base de datos

### A.1 Tablas a CREAR

| Paso | Tabla (propuesta) | Propósito | Columnas mínimas sugeridas |
| ---- | ----------------- | --------- | -------------------------- |
| **2** | `crimp_lots` ⚠️ | Lotes de CRIMP hijos del **viajero** (1 viajero → N lotes de CRIMP). | `id`, `lot_id` (FK → `lots`, el viajero), `crimp_lot_number`, `lote_fabricante` (string), `quantity`, `comments`, `timestamps`, `softDeletes` |
| **4** | `packaging_piece_weighings` | Pesadas de **piezas / manguitas** en Empaque. | `id`, `lot_id` (FK, viajero), `crimp_lot_id` (FK nullable), `packed_quantity`, `weight`, `comments`, `packed_at`, `packed_by` (FK users), `timestamps`, `softDeletes` |
| **4** | `packaging_crimp_weighings` | Pesadas de **CRIMP** en Empaque. | igual estructura que la anterior, para CRIMP. |
| **5** | `crimp_packaging_emails` *(opcional)* | Historial del correo "Empaque terminado CRIMP Viajero". | `id`, `lot_id`, `sent_to`, `payload` (json), `sent_at`, `sent_by` |

> ⚠️ **Decisión de modelado clave:** El "viajero" se mapea al actual `Lot` (que ya tiene WO + cantidad
> total + número). Los "lotes de CRIMP" son una **tabla hija nueva** (`crimp_lots`). Esto sustituye la
> relación `kit_lot ⇄ kits` que hoy cumple ese rol para crimp. Alternativa: reutilizar `kits` renombrado,
> pero **no se recomienda** (arrastra el ciclo de aprobación del kit que ya no aplica).

### A.2 Tablas a CORREGIR / revisar (NO romper NO-CRIMP)

| Paso | Tabla | Migración base | Qué cambia |
| ---- | ----- | -------------- | ---------- |
| **1, 6** | `lots` | [2025_12_28_202009_create_lots_table.php](../../../database/migrations/2025_12_28_202009_create_lots_table.php) + [2026_02_27_070000_create_packaging_records_and_update_lots.php](../../../database/migrations/2026_02_27_070000_create_packaging_records_and_update_lots.php) | Pasa a representar el **viajero** cuando la parte es crimp. ⚠️ Evaluar agregar: flag/cálculos de cantidades **completadas/sobrantes de CRIMP** y de **piezas** a nivel viajero (hoy `getPackaging*` solo manejan un total). Posible `viajero_*` ya existe (`viajero_received*`). |
| **§4** | `weighings` | [2026_02_06_063441_create_weighings_table.php](../../../database/migrations/2026_02_06_063441_create_weighings_table.php) | `kit_id` (nullable FK) **deja de poblarse** para crimp. No se elimina la columna (legacy). |
| **§5** | `quality_weighings` | [2026_02_15_000000_create_quality_weighings_table.php](../../../database/migrations/2026_02_15_000000_create_quality_weighings_table.php) | `kit_id` **deja de poblarse** para crimp. Columna se mantiene. |
| **4-6** | `packaging_records` | [2026_02_27_070000_…](../../../database/migrations/2026_02_27_070000_create_packaging_records_and_update_lots.php) | `kit_id` deja de poblarse. ⚠️ Decidir si esta tabla **se reemplaza** por las dos nuevas de piezas/CRIMP, o coexiste (un registro consolidado + dos de detalle). |
| **T** | `parts` | [2025_12_10_051116_create_parts_table.php](../../../database/migrations/2025_12_10_051116_create_parts_table.php) | Sin cambios de esquema; `is_crimp` es el **interruptor** de toda la lógica nueva. |

### A.3 Tablas que quedan "legacy" para CRIMP (no se borran)

- `kits` ([2025_12_28_200903_…](../../../database/migrations/2025_12_28_200903_create_kits_table.php))
- `kit_lot` (pivot) ([2026_02_02_032603_…](../../../database/migrations/2026_02_02_032603_create_kit_lot_table.php))
- `kit_approval_cycles`, `kit_incidents`

> Se conservan para historial de partes crimp anteriores. **Deben dejar de ser requisito** en Inspección,
> pesadas y semáforos (ver módulos M2, M4, M5, M7, M8).

---

## B. Modelos Eloquent

| Paso | Modelo | Archivo | Cambios |
| ---- | ------ | ------- | ------- |
| **T** | `Part` | [app/Models/Part.php](../../../app/Models/Part.php) | Sin cambios estructurales. `is_crimp` ya existe (cast boolean). Es el discriminador. |
| **1** | `Lot` | [app/Models/Lot.php](../../../app/Models/Lot.php) | 🔴 `canBeInspected()` / `getInspectionBlockedReason()` / `getReleasedKit()` (M7); `getTraceabilityData()` (M10). Nueva relación `crimpLots()` (HasMany). Nuevos helpers de "completado/sobrante de CRIMP" y "de piezas". Renombre conceptual a viajero (accessor `isViajero()` = `part->is_crimp`). |
| **legacy** | `Kit` | [app/Models/Kit.php](../../../app/Models/Kit.php) | Se vuelve legacy para crimp; no se elimina. |
| **T** | `WorkOrder` | [app/Models/WorkOrder.php](../../../app/Models/WorkOrder.php) | `updateSentPieces()` ([:283](../../../app/Models/WorkOrder.php#L283)) y `forceDeleteWithRelations()` ([:255](../../../app/Models/WorkOrder.php#L255)) deben contemplar `crimpLots` y las nuevas pesadas. `kits()` queda legacy. |
| **§4** | `Weighing` | [app/Models/Weighing.php](../../../app/Models/Weighing.php) | `kit_id` deja de usarse para crimp (sin cambio de esquema). |
| **§5** | `QualityWeighing` | [app/Models/QualityWeighing.php](../../../app/Models/QualityWeighing.php) | igual. |
| **4-6** | `PackagingRecord` | [app/Models/PackagingRecord.php](../../../app/Models/PackagingRecord.php) | ⚠️ posible reemplazo/coexistencia con las 2 nuevas tablas. |
| **2** | **`CrimpLot`** (nuevo) | `app/Models/CrimpLot.php` | `belongsTo(Lot)` (viajero), `lote_fabricante`, cantidad. |
| **4** | **`PackagingPieceWeighing`** (nuevo) | `app/Models/PackagingPieceWeighing.php` | pesadas de piezas/manguitas. |
| **4** | **`PackagingCrimpWeighing`** (nuevo) | `app/Models/PackagingCrimpWeighing.php` | pesadas de CRIMP. |
| **5** | **`EmpaqueTerminadoCrimpViajero`** (nuevo, Mailable) | `app/Mail/EmpaqueTerminadoCrimpViajero.php` | correo final (M9). |

---

## C. Rutas (todas dentro de `routes/admin.php`, prefijo `admin.`)

> **Ninguna ruta nueva es estrictamente necesaria** si las nuevas pesadas se manejan como modales dentro de
> Empaque (recomendado por `Flujo.mkd` §7). Las rutas existentes que **cambian de comportamiento**:

| Paso | Ruta (name) | Componente | Definición | Cambio |
| ---- | ----------- | ---------- | ---------- | ------ |
| **§4** | `admin.production.weighings` | `WeighingManagement` | [admin.php:216](../../../routes/admin.php#L216) | Sin kit para crimp (M4). |
| **§5** | `admin.quality.weighings` | `QualityWeighings` | [admin.php:225](../../../routes/admin.php#L225) | Sin kit para crimp (M5). |
| **gate** | `admin.quality.inspection` | `InspectionList` | [admin.php:224](../../../routes/admin.php#L224) | Gate de inspección sin kit (M7). |
| **legacy** | `admin.kits.index/create/show` | `KitList`/`KitCreate`/`KitShow` | [admin.php:276-278](../../../routes/admin.php#L276) | Legacy para crimp (M2). |
| **1** | `admin.lots.*` | `LotList`/`LotShow`/`LotEdit` | [admin.php:280-283](../../../routes/admin.php#L280) | Mostrar "Viajero" para crimp (M10). |
| **4-7** | `admin.packaging.*` | `PackagingDashboard`/`PackagingManagement` | [admin.php:240-241](../../../routes/admin.php#L240) | 2 pesadas + confirmación (M6). |
| **T** | `tv.display` (público) | `TvDisplay` | [web.php:12](../../../routes/web.php#L12) | Semáforos sin kit para crimp (M8). |
| **1-3, 6-8** | `admin.sent-lists.*` | vistas por depto | `routes/admin.php` (grupo sent-lists) | Materiales/Empaque (M1, M6). |

**Nueva ruta opcional:** si las pesadas de Empaque se hacen como página propia (no modal), agregar p.ej.
`admin.packaging.crimp-weighings`. Recomendado: **modal**, sin ruta nueva.

---

## D. Vistas Blade afectadas

| Paso | Vista | Módulo | Cambio |
| ---- | ----- | ------ | ------ |
| **1-3** | `sent-lists/materials-view.blade.php` | M1 | Modal lote→viajero, captura de lotes de CRIMP + lote de fabricante, quitar modal de kit. |
| **legacy** | `materials/kit-management.blade.php` | M2 | Legacy / ocultar para crimp. |
| **1-2** | `capacity-wizard/step3.blade.php` | M3 | Campos kit → lotes de CRIMP + lote de fabricante. |
| **§4** | `production/weighing-management.blade.php` | M4 | Quitar select de kit (líneas ~211-218). |
| **§5** | `quality/quality-weighings.blade.php` | M5 | Quitar select de kit. |
| **4-7** | `sent-lists/packaging-view.blade.php` | M6 | Modal de 2 pesadas, resumen "Empaque Terminado", decisiones D1/D2/D3 (~líneas 454-660). |
| **T** | `sent-lists/tv-display.blade.php` | M8 | Columna "Kit" sin kit para crimp. |
| **T** | `sent-lists/shipping-list-display.blade.php` | M8 | Usos de `is_crimp`. |
| **T** | Dashboards de área (`materials/`, `production/`, `quality/`, `packaging/`) | M8 | Stats de semáforo. |
| **5** | `emails/empaque-terminado-crimp-viajero.blade.php` | M9 | **Nuevo** template de correo. |

---

## E. Campos `kit_id` y dependencias de Kit a desacoplar (checklist técnico)

Lugares concretos donde hoy el **Kit es llave/requisito** para crimp y deben desacoplarse:

1. `Lot::canBeInspected()` → `kits()->where('status', RELEASED)` — [app/Models/Lot.php:493-495](../../../app/Models/Lot.php#L493)
2. `Lot::getReleasedKit()` / `getInspectionBlockedReason()` — [app/Models/Lot.php:501-542](../../../app/Models/Lot.php#L501)
3. `WeighingManagement`: `selectedKitId`, `$lot->kits`, `kit_id` en save — [app/Livewire/Admin/Production/WeighingManagement.php:69-162](../../../app/Livewire/Admin/Production/WeighingManagement.php#L69)
4. `QualityWeighings`: `qualKitId`, `qualKits`, `kit_id` en save — [app/Livewire/Admin/Quality/QualityWeighings.php:144-242](../../../app/Livewire/Admin/Quality/QualityWeighings.php#L144)
5. `SentListMaterialsView`: modal de kit, validación de envío, `kits()->released` — [:139-265](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php#L139)
6. `SentListPackagingView`: crea/resetea kit en decisiones — [:254-256, :409-419](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L254)
7. `CapacityWizard`: crea kits para crimp — [:866-889](../../../app/Livewire/Admin/CapacityWizard.php#L866)
8. `TvMonitor` / `ComputesAreaStats`: columna kit por estado de Kit — [TvMonitor.php:57-67](../../../app/Livewire/Public/TvMonitor.php#L57) · [ComputesAreaStats.php:46-57](../../../app/Traits/ComputesAreaStats.php#L46)
