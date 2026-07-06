# 10 — Análisis técnico: Auto-status de WO en ManagerPO vía Observer (coexistencia con cambio manual)

> **Dueño:** Josadec · **Módulo:** ManagerPO (Work Orders) / Sent Lists / flujo por departamentos.
> **Objetivo:** poner en **automático** el `status` de las Work Orders (`statuses_wo`) que ya están en una
> **Sent List** y cuyo flujo de fabricación arrancó (Materiales + Inspección), de modo que mientras la WO esté
> **en cualquiera** de los procesos *Materiales, Inspección, Producción, Calidad, Empaque* su status quede
> **siempre en "In Progress"** — **sin** eliminar la capacidad del usuario de cambiar el status a mano.
> **Base (verificada en código 2026-07-04):** el status de la WO vive en `work_orders.status_id → statuses_wo`
> (Open / In Progress / Completed / Cancelled / On Hold) y hoy se setea **solo manualmente** desde
> `WOEdit` / `WOList` a través de `PurchaseOrderService::updateWorkOrderStatus()`. El avance por departamentos
> vive en `sent_lists.current_department` (+ `department_history` + `*_approved_at`) y en los estados de `lots`
> (`status`, `material_status`, `inspection_status`, `packaging_status`).
> **Fecha:** 2026-07-04.
> **Regla de oro:** **NO** se implementa código en esta tarea; este documento es análisis + plan. Y el
> auto-status **jamás** debe pisar un override manual explícito del usuario (ver §5 y decisión D-3).
> **Leyenda:** ✅ = verificado en código · 🟡 = decisión / dato de negocio a confirmar · ⚠️ = riesgo.

---

## 0. Resumen del cambio

Hoy el status de una WO en el ManagerPO es **100 % manual**: nada lo mueve solo. El usuario abre `WOEdit`
(o el dropdown de `WOList`) y elige el estado; el cambio se persiste y se audita en `wo_status_logs` con
`user_id = Auth::id()`. Cuando una WO entra a una Sent List y empieza a fabricarse (Materiales → Inspección →
…), su status en el ManagerPO **se queda pegado en "Open"** salvo que alguien lo cambie a mano.

El pedido de negocio es: **en cuanto una WO esté productivamente "en piso"** (dentro de una Sent List con el
flujo ya arrancado, presente en Materiales/Inspección/Producción/Calidad/Empaque), su status del ManagerPO
debe **reflejar automáticamente "In Progress"**, y mantenerse ahí mientras siga en esos procesos. Pero el
usuario debe poder **forzar manualmente** otro estado (p.ej. "On Hold", "Cancelled") y que el automático
**respete** ese override.

**Diseño propuesto (resumen):** un **Observer** (sobre `SentList` y `Lot`, más un guard en `WorkOrder`) que
delega en un **servicio resolvedor idempotente** (`WorkOrderStatusResolver`) el cálculo del status objetivo por
WO, con un **flag de override manual** en `work_orders` para que auto y manual coexistan sin pelearse, y con
`updateQuietly()` + `wasChanged()` para evitar loops de `saved()` (mismo patrón que
`LotPackagingObserver`, ✅).

---

## 1. Estado actual del código (evidencia ✅)

### 1.1 Los DOS conceptos de "status" que hay que no confundir

| Concepto | Tabla / columna | Valores | Quién lo escribe hoy |
| --- | --- | --- | --- |
| **Status de WO** (ManagerPO) ⭐ objetivo | `work_orders.status_id` → `statuses_wo` | Open, In Progress, Completed, Cancelled, On Hold | **Manual**: `WOEdit`/`WOList` → `PurchaseOrderService::updateWorkOrderStatus()` |
| **Departamento de la Sent List** | `sent_lists.current_department` | `materiales`, `inspeccion`, `produccion`, `calidad`, `envios` | `SentList::moveToNextDepartment()` desde cada vista de depto |
| **Estado del lote** | `lots.status` | `pending`, `in_progress`, `completed`, `cancelled` | Vistas de Producción / Shipping (`Lot::STATUS_*`) |
| **Sub-estados de lote por depto** | `lots.material_status`, `lots.inspection_status`, `lots.packaging_status` | `pending`/`released`; `pending`/`approved`/`rejected`; `pending`/`approved` | Vistas Materiales / Inspección / Empaque |

> ⭐ El pedido es **sobre la primera fila** (status de la WO). Las otras tres filas son las **señales** que
> disparan y alimentan el cálculo automático.

### 1.2 Catálogo de estados de WO — `database/seeders/StatusWOSeeder.php`

| Estado | Línea | Color | Rol en el auto-status |
| --- | --- | --- | --- |
| **Open** | 18 | `#3B82F6` azul | Estado inicial al crear la WO (`createWorkOrderRecord`, ✅). Origen típico del auto → In Progress. |
| **In Progress** | 23 | `#F59E0B` ámbar | ⭐ **Destino del automático.** |
| **Completed** | 28 | `#10B981` verde | Terminal; el auto NO debe tocarlo (ver D-4). |
| **Cancelled** | 33 | `#EF4444` rojo | Terminal / override; el auto NO debe tocarlo. |
| **On Hold** | 38 | `#6B7280` gris | Override manual típico; el auto debe respetarlo (D-3). |

> ⚠️ El seeder usa `firstOrCreate(['name' => ...])`, así que **el `id` de "In Progress" NO es estable** entre
> entornos. El código debe resolverlo **por nombre** (`StatusWO::where('name', 'In Progress')`), nunca por id
> hardcodeado. Igual que `PurchaseOrderService` hace con `'Open'` (líneas 173, 274, ✅).

### 1.3 Cómo se setea el status HOY (100 % manual) ✅

| Punto de entrada | Archivo:línea | Qué hace |
| --- | --- | --- |
| Edición individual | `app/Livewire/Admin/WorkOrders/WOEdit.php:52-80` | `save()` detecta `statusChanged` y llama al servicio |
| Cambio rápido en lista | `app/Livewire/Admin/WorkOrders/WOList.php:68-75` | `updateStatus($id, $statusId)` → servicio |
| Creación de WO | `app/Services/PurchaseOrderService.php:207-222` | nace en **"Open"** + primer `WOStatusLog` |
| **Escritura + auditoría** | `app/Services/PurchaseOrderService.php:319-338` | `updateWorkOrderStatus()`: `update(status_id)` + `WOStatusLog::create(user_id = Auth::id())` |

**Implicación clave:** ya existe un **punto único de escritura** de status (`updateWorkOrderStatus`) que además
audita en `wo_status_logs`. El automático debe reutilizar/emular ese punto para (a) mantener la auditoría y
(b) distinguir cambios de sistema (`user_id = null`) de cambios humanos (`user_id != null`).

### 1.4 Cómo se relaciona la WO con la Sent List (¡DOS rutas!) ⚠️

`sent_lists` y `work_orders` se enlazan por **dos** caminos y **no siempre** por el mismo:

1. **Directo:** `work_orders.sent_list_id` (`SentList::workOrders()`, `WorkOrder.php:21,77`). Se setea en
   `CapacityCalculatorService.php:244` y, de forma **perezosa**, en `SentListMaterialsView.php:97-98`
   (cuando Materiales toca la WO por primera vez).
2. **Vía pivot PO↔SentList:** `sent_list_purchase_orders` (`SentList::purchaseOrders()`), y la WO cuelga del PO.

Por eso el modelo expone **`SentList::getEffectiveWorkOrders()`** (`SentList.php:96-104`, ✅) que **une ambas
rutas** y deduplica. Todo cálculo de "WOs de esta Sent List" en el Observer **debe** usar esa fusión, no solo
`sent_list_id`, o se perderán WOs del flujo legacy/pivot.

### 1.5 Señales de "presencia en cada departamento" (dónde mirar) ✅

| Proceso | Señal a nivel Sent List | Señal a nivel Lote | Evidencia |
| --- | --- | --- | --- |
| **Materiales** | `current_department = materiales` (default al crear, `CapacityWizard.php:869`); `materials_approved_at` | `lots.material_status ∈ {pending, released}` | `SentListMaterialsView.php:284,310` |
| **Inspección** | `current_department = inspeccion`; `inspection_approved_at` | `lots.inspection_status ∈ {pending, approved, rejected}`, `inspection_completed_at` | `SentListInspectionView.php:36-37,153-154` |
| **Producción** | `current_department = produccion`; `production_approved_at` | `lots.status = in_progress` | `SentListProductionView.php:98,154,162` |
| **Calidad** | `current_department = calidad`; `quality_approved_at` | `quality_weighings` del lote | `SentListQualityView.php:221` |
| **Empaque** | `current_department = envios` (`DEPT_SHIPPING = 'envios'`, etiqueta "Empaque"); `shipping_approved_at` | `lots.packaging_status`, `ready_for_shipping` | `SentListPackagingView.php:330-336,458` |

> **Ojo con el naming:** la constante del último depto es `SentList::DEPT_SHIPPING = 'envios'` **pero su
> etiqueta es "Empaque"** (`SentList::getDepartments()`, `SentList.php:205`). El diagrama de flujo del proyecto
> usa "Empaque" → aquí es `envios`.

### 1.6 Precedente de arquitectura de Observer en el repo ✅

`app/Observers/LotPackagingObserver.php` es el **molde a imitar**:
- Escucha `Lot::updated` y **actúa solo si `wasChanged('closure_decision')`** (44) → evita ejecuciones espurias.
- **Idempotencia** por bandera (`ready_for_shipping === true` → skip, 62).
- **Escribe con `updateQuietly()`** (87) para **no re-disparar** el observer (anti-loop).
- Registrado en `AppServiceProvider::boot()` (`app/Providers/AppServiceProvider.php:27`).

El auto-status debe replicar estas tres propiedades (guard por `wasChanged`, idempotencia, `updateQuietly`).

---

## 2. Regla de negocio formalizada

Con las señales de §1.5, la regla del usuario se formaliza así:

**Condición de disparo (gate):** una WO es candidata a auto-status cuando
`WO ∈ SentList` (por cualquiera de las dos rutas de §1.4) **Y** el flujo ya arrancó, entendido como
**Materiales + Inspección iniciados**. 🟡 Señal recomendada (decisión D-1):

> `SentList.materials_approved_at != null` **O** `current_department ∈ {inspeccion, produccion, calidad, envios}`
> (es decir, ya salió de `materiales`, lo que implica que Materiales terminó e Inspección comenzó).

**Efecto:** si la WO está "en" alguno de los 5 procesos (que, dado que `current_department` de una Sent List
activa **siempre** es uno de los 5, equivale a "la Sent List está en curso y no cerrada"):

> **status objetivo = "In Progress"** — **salvo** que aplique una de las excepciones de §2.1.

### 2.1 Excepciones (el auto NO pisa estos casos)

| # | Situación | Qué hace el auto |
| - | --------- | ---------------- |
| E1 | WO ya en **Completed** (o `isComplete()` / todos los lotes `completed` + `ready_for_shipping`) | **No** la baja a In Progress (terminal). Ver D-4. |
| E2 | WO en **Cancelled** | **No** la toca (terminal/override). |
| E3 | WO con **override manual activo** (usuario forzó un estado) | **Respeta** el manual; no reescribe. Ver §5 + D-3. |
| E4 | WO **sin** Sent List, o Sent List **canceled**, o flujo **no arrancado** | **No** aplica; queda como esté (típicamente "Open"). |

---

## 3. Impacto arquitectónico

### 3.1 Backend
- **Nuevo servicio** `app/Services/WorkOrderStatusResolver.php` (fuente única de verdad del cálculo).
  Métodos sugeridos: `resolveTargetStatus(WorkOrder): ?string`, `applyAutoStatus(WorkOrder): void`,
  `recalcForSentList(SentList): void`.
- **Nuevo(s) Observer(s):** `SentListObserver` y `LotStatusObserver` (o extender el existente
  `LotPackagingObserver`). Registro en `AppServiceProvider::boot()` (junto a las líneas 23-27, ✅).
- **Reutilizar** `PurchaseOrderService::updateWorkOrderStatus()` para escribir + auditar, con una variante que
  permita `user_id = null` (cambio de sistema). Hoy usa `Auth::id()` fijo (línea 333) → **requiere un pequeño
  ajuste de firma** para aceptar autor nulo/sistema (D-5).

### 3.2 Base de datos
- **Nueva columna en `work_orders`** para el override manual (elegir uno, D-3):
  - Opción A (recomendada): `status_locked` `boolean default false` + `status_locked_by` (nullable) +
    `status_locked_at` (nullable). El auto **solo** actúa si `status_locked = false`.
  - Opción B (más expresiva): `status_source` `enum('auto','manual') default 'auto'`.
- **Sin** tablas nuevas. `wo_status_logs` ya soporta la auditoría (el auto escribe con `user_id = null` y un
  `comments` tipo `"[auto] En proceso: Sent List #NN en {depto}"`).
- Índice ya existe sobre `status_id` (`work_orders` migración línea 33) — suficiente.

### 3.3 Frontend
- `WOEdit` / `WOList`: al hacer un cambio **manual**, setear el flag de override (Opción A/B). Añadir un
  control para **"volver a automático"** (liberar el lock) — sin esto, una WO tocada a mano nunca vuelve a auto.
- Mostrar en la UI un indicador "Auto" vs "Manual" en el status (opcional, mejora UX).
- **Ningún** cambio en PDFs, Packing Slip, Invoice ni Shipping (el status de WO no alimenta esos snapshots).

---

## 4. Puntos de disparo (qué eventos recalculan)

El status objetivo depende de señales que cambian en varios modelos. Para no perder transiciones, el Observer
debe engancharse donde esas señales se escriben:

| Evento | Modelo / gancho | Por qué dispara | Guard recomendado (`wasChanged`) |
| --- | --- | --- | --- |
| Avance de depto | `SentList::updated` | cambia `current_department` / `*_approved_at` → arranca el flujo | `wasChanged('current_department')` o cualquiera `*_approved_at` |
| Cambio de estado de la lista | `SentList::updated` | `status` → `confirmed`/`canceled` (E4) | `wasChanged('status')` |
| Asignación tardía de WO | `WorkOrder::updated` | `sent_list_id` recién seteado (`SentListMaterialsView:98`) | `wasChanged('sent_list_id')` |
| Lote entra a un depto | `Lot::updated` / `Lot::created` | `material_status`, `inspection_status`, `status`, `packaging_status` | `wasChanged(['status','material_status','inspection_status','packaging_status'])` |
| Cierre / completado | `Lot::updated` | todos los lotes `completed` → posible Completed (D-4) | reusar señal de `LotPackagingObserver` |

> **Anti-loop:** todos los observers escriben el status vía `updateQuietly()` (no re-disparan `WorkOrder`
> observers) y **filtran por `wasChanged`** para no recalcular en cada `save()`. Idempotencia: si el status
> objetivo == status actual, **no escribir** (ni log). Igual que `LotPackagingObserver` (§1.6).

---

## 5. Coexistencia auto ↔ manual (el corazón del diseño)

El requisito es "automático **sin** quitar el manual". Estrategia recomendada (**Opción A**, lock explícito):

1. **Estado normal (auto):** `status_locked = false`. El Observer recalcula y, si corresponde, pone
   "In Progress" (o deja "Open" si el flujo no arrancó). Escribe con `user_id = null` (sistema).
2. **Override manual:** cuando el usuario cambia el status desde `WOEdit`/`WOList`, además de persistir el
   estado se setea `status_locked = true` (+ `status_locked_by`, `status_locked_at`). A partir de ahí el
   Observer **hace early-return** para esa WO (respeta al humano). El cambio se audita con `user_id = Auth::id()`.
3. **Reanudar auto:** un botón "Volver a automático" pone `status_locked = false`; el Observer vuelve a mandar
   y recalcula al vuelo.
4. **Terminales siempre ganan:** aunque `status_locked = false`, si la WO está en **Completed**/**Cancelled**
   el auto no la degrada (E1/E2).

> 🟡 **D-3 (abierta):** ¿el override manual es **permanente** hasta que el usuario lo libere (Opción A), o
> **temporal** hasta el siguiente hito (p.ej. se auto-libera al cerrarse la WO)? Recomendación: **permanente +
> botón de liberar**, es lo más predecible y evita "peleas" auto/humano.

**Por qué un flag y no inferir del log:** se podría intentar deducir "manual" leyendo el último `wo_status_logs`
con `user_id != null`, pero es frágil (un auto y un manual al mismo destino se confunden, y complica la
idempotencia). Un flag booleano es explícito, barato y testeable.

---

## 6. Diseño del Observer / servicio (pseudo-arquitectura)

```
AppServiceProvider::boot()
 ├─ SentList::observe(SentListObserver::class)
 ├─ Lot::observe(LotStatusObserver::class)        // o extender LotPackagingObserver
 └─ WorkOrder::observe(WorkOrderObserver::class)  // solo guard de sent_list_id

WorkOrderStatusResolver  (servicio, fuente única)
 ├─ resolveTargetStatus(WorkOrder $wo): ?string
 │     if $wo->status_locked            → return null          // E3, respeta manual
 │     if status ∈ {Completed,Cancelled}→ return null          // E1/E2 terminales
 │     $sl = sentListEfectiva($wo)                              // §1.4 (directa o pivot)
 │     if !$sl || $sl->isCanceled()     → return null          // E4
 │     if !flujoArrancado($sl)          → return null          // gate D-1 (Materiales+Inspección)
 │     return 'In Progress'                                     // efecto
 ├─ applyAutoStatus(WorkOrder $wo)
 │     $target = resolveTargetStatus($wo)
 │     if $target === null || $target === $wo->status->name → return  // idempotencia
 │     PurchaseOrderService::updateWorkOrderStatus($wo, id($target), '[auto] ...', systemUser: true)
 │       └─ internamente usa updateQuietly() para el WorkOrder    // anti-loop
 └─ recalcForSentList(SentList $sl)
       foreach ($sl->getEffectiveWorkOrders() as $wo) applyAutoStatus($wo)

SentListObserver::updated($sl)
   if wasChanged(current_department | *_approved_at | status) → resolver.recalcForSentList($sl)

LotStatusObserver::saved($lot)
   if wasChanged(status|material_status|inspection_status|packaging_status)
      → resolver.applyAutoStatus($lot->workOrder)  // vía $lot->work_order_id
```

> **Nota anti-loop concreta:** `updateWorkOrderStatus()` hoy hace `$workOrder->update([...])` (línea 324). Si
> se registra un `WorkOrderObserver`, ese `update` lo dispararía. Solución: en la ruta automática usar
> `updateQuietly()` para el campo `status_id`, y dejar el `WOStatusLog::create()` aparte (ese no dispara al
> WorkOrder). Ver D-5.

---

## 7. Módulos / tablas / rutas afectadas

| Capa | Archivo | Cambio |
| --- | --- | --- |
| Provider | `app/Providers/AppServiceProvider.php` | registrar 2-3 observers (junto a 23-27) |
| Servicio (nuevo) | `app/Services/WorkOrderStatusResolver.php` | cálculo + aplicación idempotente |
| Servicio | `app/Services/PurchaseOrderService.php:319-338` | permitir autor "sistema" (`user_id` nullable) + `updateQuietly` |
| Observers (nuevos) | `app/Observers/SentListObserver.php`, `LotStatusObserver.php` (o extender `LotPackagingObserver.php`) | disparo por `wasChanged` |
| Modelo | `app/Models/WorkOrder.php` | `fillable` + casts del flag; helper `sentListEfectiva()` / `isStatusLocked()` |
| Migración (nueva) | `..._add_status_lock_to_work_orders.php` | `status_locked` (+by/at) o `status_source` |
| Frontend | `WOEdit.php`, `WOList.php` + blades | set flag al cambiar manual + botón "volver a auto" + badge Auto/Manual |
| Rutas | — | **ninguna nueva** |

---

## 8. Riesgos y mitigaciones ⚠️

| # | Riesgo | Mitigación |
| - | ------ | ---------- |
| R1 | **Loop infinito de `saved()`** (observer escribe status → re-dispara observer) | `updateQuietly()` + guard `wasChanged` + idempotencia "target == actual → no escribir" (§4, §6). Patrón ya probado en `LotPackagingObserver`. |
| R2 | **Perder WOs enlazadas por pivot** (no por `sent_list_id`) | Usar SIEMPRE `SentList::getEffectiveWorkOrders()` / fusión de ambas rutas (§1.4). |
| R3 | **`id` de "In Progress" inestable** entre entornos | Resolver por `name`, cachear el id en el resolver; nunca hardcodear (§1.2). |
| R4 | **Pisar override manual** del usuario | Flag `status_locked` → early-return del auto (§5, E3). |
| R5 | **Degradar una WO Completed/Cancelled** a In Progress | Excepciones E1/E2: terminales bloquean el auto (§2.1). |
| R6 | **Performance / N+1** al recalcular todas las WOs de una Sent List en cada `updated` | Eager-load (`workOrders.lots`, `purchaseOrders.workOrder`) y disparar `recalcForSentList` solo cuando `wasChanged` de campos relevantes; considerar `dispatchAfterResponse`/cola si el volumen crece. |
| R7 | **Auditoría ruidosa** en `wo_status_logs` (un log por cada recálculo) | Solo loguear cuando el status **realmente cambia** (idempotencia). |
| R8 | ⚠️ **BD real** al testear: `RefreshDatabase` puede vaciar `flexcon_db` con config cacheada | `php artisan config:clear` antes de `php artisan test` (memoria del proyecto). |
| R9 | **Regla CRIMP "Lote"→"Viajero"** no debe verse afectada | El auto-status no toca naming ni `is_crimp`; documentar que es ortogonal (memoria del proyecto). |

---

## 9. Decisiones de negocio / puntos a confirmar

| # | Decisión | Estado |
| - | -------- | ------ |
| D-1 | Señal exacta de "Materiales + Inspección iniciados": ¿`materials_approved_at != null`, o `current_department ∉ {materiales}`, o presencia de lote con `inspection_status != null`? | 🟡 Recomendado: `materials_approved_at != null` **o** salió de `materiales`. Confirmar con el usuario. |
| D-2 | ¿"En proceso" debe cubrir también **Materiales** (primer depto) o recién desde **Inspección**? El texto dice "ya comenzó Materiales **e** Inspección" → arranca en Inspección. | 🟡 Confirmar si el gate es Materiales (más temprano) o Inspección (literal). |
| D-3 | Override manual: ¿**permanente + botón liberar** (Opción A) o **temporal**? ¿`status_locked` boolean o `status_source` enum? | 🟡 Recomendado: Opción A permanente + botón. |
| D-4 | ¿El auto debe también poner **"Completed"** cuando todos los lotes cierran (`ready_for_shipping`/`isComplete()`), o solo gestiona el paso a "In Progress"? | 🟡 Alcance: el pedido literal es solo "In Progress". Completed puede ser fase 2. |
| D-5 | Cambio de sistema en `wo_status_logs`: `user_id = null` + `PurchaseOrderService` con firma que acepte autor sistema y `updateQuietly`. | 🟡 Requiere ajuste menor de firma (hoy usa `Auth::id()` fijo, línea 333). |
| D-6 | ¿"Empaque" = `DEPT_SHIPPING ('envios')`? (naming del código vs. diagrama) | ✅ Sí; etiqueta "Empaque", constante `envios` (`SentList.php:205`). |
| D-7 | ¿Qué pasa con una WO cuya Sent List se **rechaza** y retrocede de depto (`moveToPreviousDepartment`)? ¿Sigue "In Progress"? | 🟡 Recomendado: sí, sigue en proceso (aún está en un depto). Confirmar. |

---

## 10. Plan de implementación por pasos

1. **Migración del flag** de override en `work_orders` (`status_locked` + `status_locked_by/at`), `fillable` +
   casts en `WorkOrder` (D-3).
2. **Servicio `WorkOrderStatusResolver`** con `resolveTargetStatus()` puro (sin efectos) — testeable en
   aislamiento con todas las ramas de §2/§2.1. Resolver el id de "In Progress" por nombre y cachearlo (R3).
3. **Ajustar `PurchaseOrderService::updateWorkOrderStatus()`** para aceptar autor sistema (`user_id` null) y
   escribir el `status_id` con `updateQuietly()` en la ruta automática (D-5, R1).
4. **Observers**: `SentListObserver` (recalc por `wasChanged` de `current_department`/`*_approved_at`/`status`)
   y `LotStatusObserver` (recalc de `$lot->workOrder` por `wasChanged` de estados de lote). Registrar en
   `AppServiceProvider::boot()`.
5. **Guard de asignación tardía**: en `WorkOrder::updated` (o dentro del flujo de `SentListMaterialsView`)
   recalcular cuando `sent_list_id` pasa de null a un id (§4).
6. **Override manual en UI**: `WOEdit`/`WOList` setean `status_locked = true` al cambiar a mano; agregar botón
   "Volver a automático" que lo libera y llama a `applyAutoStatus()`. Badge Auto/Manual (opcional).
7. **Pruebas** (§11).
8. **(Fase 2 opcional)** auto-**Completed** cuando todos los lotes cierran (D-4).

---

## 11. Pruebas (TDD recomendado)

- [ ] **Gate:** WO en Sent List con `current_department = materiales` y `materials_approved_at = null` →
      resolver devuelve `null` (queda "Open"). (D-1/D-2)
- [ ] **Arranque:** al mover la Sent List a `inspeccion` (o setear `materials_approved_at`) → el Observer pone
      la WO en **"In Progress"** y escribe `wo_status_logs` con `user_id = null`.
- [ ] **Pivot:** WO enlazada **solo** por `sent_list_purchase_orders` (sin `sent_list_id`) también pasa a
      In Progress (R2, usa `getEffectiveWorkOrders`).
- [ ] **Override manual respetado:** usuario pone "On Hold" (`status_locked = true`); un `moveToNextDepartment`
      posterior **no** la regresa a In Progress. (E3, R4)
- [ ] **Reanudar auto:** liberar el lock → recalcula a In Progress.
- [ ] **Terminales:** WO "Completed"/"Cancelled" no se degrada a In Progress aunque la Sent List avance. (E1/E2)
- [ ] **Idempotencia / anti-loop:** dos `updated` seguidos de la Sent List no crean logs duplicados ni entran
      en loop (R1/R7). Verificar con conteo de `wo_status_logs`.
- [ ] **Sin Sent List:** WO suelta permanece en "Open". (E4)
- [ ] ⚠️ `php artisan config:clear` antes de `php artisan test` (R8).

---

## 12. Referencias

- Código:
  - `app/Models/WorkOrder.php` — `status()` (53-56), `hasExternalWoNumber()`/`getEffectiveWoNumber()` (96-134),
    `isComplete()` (237-240), `sentList()` (75-78).
  - `app/Models/SentList.php` — `getEffectiveWorkOrders()` (96-104), `getDepartments()` (198-207),
    `moveToNextDepartment()` (229-270), `moveToPreviousDepartment()` (276-303), constantes depto (68-72).
  - `app/Models/Lot.php` — constantes `STATUS_*` (92-98), `INSPECTION_*` (103-107), boot `updated`/`created`
    (112-150), `isViajero()` (180-183).
  - `app/Services/PurchaseOrderService.php` — `createWorkOrderRecord()` (201-232, nace en "Open"),
    `updateWorkOrderStatus()` (319-338, punto único de escritura + log).
  - `app/Observers/LotPackagingObserver.php` — **molde de Observer** (guard `wasChanged` 44, idempotencia 62,
    `updateQuietly` 87).
  - `app/Providers/AppServiceProvider.php` — registro de observers (23-27).
  - `app/Livewire/Admin/WorkOrders/WOEdit.php` (52-80) y `WOList.php` (68-75) — cambio manual actual.
  - Vistas de depto: `SentListMaterialsView.php` (284,310; `sent_list_id` perezoso 97-98),
    `SentListInspectionView.php` (36-37,131,153-154), `SentListProductionView.php` (98,146,154,162),
    `SentListQualityView.php` (221), `SentListPackagingView.php` (330-336,458,598).
  - `app/Services/CapacityCalculatorService.php:244` y `app/Livewire/Admin/CapacityWizard.php:869` — creación
    de Sent List + asignación de WO / depto inicial.
  - `database/seeders/StatusWOSeeder.php` (16-49) — catálogo de estados de WO.
  - `database/migrations/2025_12_10_090000_create_work_orders_table.php` — esquema `work_orders`.
- Documentos previos de esta carpeta: `03_riesgos_y_decisiones.md`, `08_shipping_list_crimp_analisis.md`,
  `09_plan_implementacion_qty_empacada_crimp.md`.
- Memoria del proyecto: naming CRIMP "Lote"→"Viajero" solo si `parts.is_crimp` (ortogonal a este cambio);
  WO en Packing Slips vía `purchaseOrder->wo` / `hasExternalWoNumber()`; peligro `RefreshDatabase` sobre
  `flexcon_db` con config cacheada.
