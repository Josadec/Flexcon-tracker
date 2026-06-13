# Reporte de Lógica — Features 2, 3 y 4 (no-CRIMP)

> **Tipo:** Análisis técnico de lógica e impacto (no contiene código aplicado).
> **Fecha:** 2026-06-06
> **Fuente:** [`5-Nuevas_features.mkd`](../5-Nuevas_features.mkd) + [`6-Propuesta_Tiempo_Costo.mkd`](../6-Propuesta_Tiempo_Costo.mkd),
> mapeado contra el código real (Laravel 12 · Livewire 3 · Flux 2 · Volt · Tailwind · Spatie · dompdf).
> **Tiempo real actual:** `wire:poll` (NO hay Reverb/Pusher; `BROADCAST_CONNECTION=log`).
>
> La **Feature 1 (Reajuste de CRIMP)** está documentada aparte en
> [`../Reporte_areas_afectadas/`](../Reporte_areas_afectadas/00_README.md).

---

## Resumen ejecutivo

| # | Feature | Área del sistema | Esfuerzo (Propuesta) | Riesgo | BD |
| - | ------- | ---------------- | -------------------- | ------ | -- |
| 2 | **Alarmas sonoras** al cambiar semáforos | TV Monitor + dashboards de área | 3.5 días | Medio | Sin cambios (opcional: prefs de usuario) |
| 3 | **Comentarios por WO** en Lista de Envío | Todas las vistas de Sent List por depto + BD | 3 días | Bajo-Medio | 1 tabla nueva |
| 4 | **4° botón de cierre por faltante** (último lote) | Empaque → Toma de Decisión | 3.5 días | Medio | Sin cambios |

> Las tres son **independientes del flujo de CRIMP** y entre sí. La 3 es la única que **sí** toca BD
> (tabla nueva). La 2 y la 4 reutilizan estructuras existentes.

---

# Feature 2 — Alarmas sonoras al cambiar los semáforos

### Requerimiento
> *"Sistema de alarmas con sonido para que los usuarios identifiquen cuando exista un cambio en los
> semáforos de todas las áreas, aunque el usuario no esté frente a su máquina."*

### Lógica actual en el código
- Los semáforos se calculan **server-side por lote** y se **agregan por área**:
  - Por lote: `Lot::getQualitySemaphoreStatus()` ([app/Models/Lot.php:685](../../../app/Models/Lot.php#L685)) y
    `Lot::getPackagingSemaphoreStatus()` ([:882](../../../app/Models/Lot.php#L882)).
  - Agregado por área (kit, inspección, producción, calidad, empaque) con conteos `green/yellow/gray/total`:
    `ComputesAreaStats::computeAreaStats()` ([app/Traits/ComputesAreaStats.php:15](../../../app/Traits/ComputesAreaStats.php#L15)).
  - `TvMonitor` (público `/tv`) replica el cálculo inline y arma `rows` + `areaStats`
    ([app/Livewire/Public/TvMonitor.php:36-146](../../../app/Livewire/Public/TvMonitor.php#L36)).
- **Refresco "tiempo real" = polling Livewire** (no push real):
  - `tv-display.blade.php:28` → `wire:poll.{{ $refreshInterval }}s="refreshDisplay"` (30 s por defecto).
  - `shipping-list-display.blade.php:1` → `wire:poll.30s="refreshDisplay"`.
  - Dashboards que consumen `ComputesAreaStats`: `QualityAreaDashboard`, `PackagingDashboard`,
    `MaterialsHubDashboard`, `ProductionHubDashboard`.
- **No hay WebSockets:** sin `config/broadcasting.php`, sin Reverb/Pusher, `BROADCAST_CONNECTION=log`.

### Lógica nueva (cómo debe funcionar)

**Opción A — Detección de cambios sobre el polling existente (RECOMENDADA).**
1. **Server:** exponer una **"firma"/hash** del estado de semáforos por área (p. ej. hash de los conteos
   `green/yellow/gray` que ya produce `computeAreaStats()`), o el conteo crudo.
2. **Cliente (Alpine):** guardar la firma previa; en cada ciclo de `wire:poll`, comparar.
   Si cambió → `new Audio('/sounds/alarm.mp3').play()` + resaltar visualmente el área que cambió.
3. **Autoplay del navegador:** el audio solo suena tras una **primera interacción** del usuario
   (mostrar un botón "Activar sonido" una vez).
4. **Preferencias por usuario:** activar/silenciar, volumen y, opcionalmente, filtrar a su propia área
   (un empacador solo escucha cambios de Empaque).
- **Pros:** sin infraestructura nueva, reutiliza `wire:poll` y `ComputesAreaStats`.
- **Contras:** latencia de hasta 30 s; cada cliente recalcula al hacer poll.

**Opción B — Tiempo real con Laravel Reverb (broadcasting).**
1. Instalar/configurar Reverb (`config/broadcasting.php`, `.env`, proceso `reverb:start`).
2. Emitir un evento `ShouldBroadcast` cuando un lote cambia de estado de semáforo.
3. Cliente con `Echo` escucha el canal del área y dispara el sonido al instante.
- **Pros:** instantáneo. **Contras:** requiere correr/mantener el servidor Reverb (complejo en XAMPP/Windows).

### Áreas / archivos afectados
- `app/Traits/ComputesAreaStats.php` — devolver también una **firma/hash** del estado.
- `app/Livewire/Public/TvMonitor.php` y dashboards de área — exponer la firma a la vista.
- Vistas con `wire:poll` (`tv-display.blade.php`, `shipping-list-display.blade.php`, dashboards) —
  wrapper Alpine para comparar y sonar.
- **Nuevo asset:** `public/sounds/alarm.mp3` + control "Activar/silenciar sonido".

### Tablas / Modelos / Rutas
- **Tablas:** núcleo **sin cambios**. *(Opcional)* preferencias por usuario: columnas
  `users.notif_sound_enabled`, `notif_area`, `notif_volume` (o un JSON en `users`).
- **Modelos:** `User` (solo si se agregan preferencias). Sin modelos nuevos obligatorios.
- **Rutas:** **ninguna nueva** (se trabaja sobre las pantallas existentes; `tv.display` es público).

### Casos borde / decisiones
- ¿Qué transición dispara la alarma? ¿cualquier cambio, o solo `→ green` (área lista) o `→ yellow/rojo` (atención)?
- ¿Alarma **global** (todas las áreas) o **por rol/área** del usuario?
- Pestañas múltiples abiertas → evitar sonido duplicado.
- Autoplay bloqueado por el navegador hasta la primera interacción.

---

# Feature 3 — Comentarios por WO dentro de la Lista de Envío

### Requerimiento
> *"Sistema de comentarios dentro de la Lista de Envío que se puedan agregar por cada WO para procesos internos."*

### Lógica actual en el código
- `WorkOrder` ya tiene **una columna simple `comments`** ([app/Models/WorkOrder.php:31](../../../app/Models/WorkOrder.php#L31),
  fillable) — **un solo texto**, NO es un hilo con autor/fecha.
- `SentList` tiene `notes` (una sola nota a nivel lista) — usada p. ej. en `SentListMaterialsView::sendToInspection()`.
- **No existe tabla de comentarios** (no hay migración `*comment*`).
- Sí existe un **patrón polimórfico reutilizable**: `AuditTrail` ([app/Models/AuditTrail.php](../../../app/Models/AuditTrail.php))
  con `morphTo auditable`, `user_id`, `action`, `old_values/new_values`, `created_at`; ya usado por
  `Lot::auditTrail()` y `Kit::auditTrail()`.
- Vistas de Lista de Envío por departamento (donde se ven los WO): `SentListMaterialsView`,
  `SentListInspectionView`, `SentListProductionView`, `SentListQualityView`, `SentListPackagingView`,
  `DynamicSentListView`, `SentListDepartmentView`.

### Lógica nueva (cómo debe funcionar)
Crear un **hilo de comentarios** asociado a cada WO (varios comentarios, con autor y fecha).

**Modelo de datos — recomendado: tabla dedicada.**
- Nueva migración `create_work_order_comments_table`:
  `id`, `work_order_id` (FK), `user_id` (FK), `body` (text), `timestamps`, `softDeletes`.
  - Opcional: `sent_list_id` para anclar el comentario al contexto de la lista; `is_internal` (bool).
- **Alternativa:** tabla **polimórfica** `comments` (`commentable_type/id`) si se prevé comentar también
  Lotes/PO/SentList. Más flexible y sigue el patrón de `AuditTrail`.

**Modelo / relaciones.**
- `app/Models/WorkOrderComment.php` (nuevo): `belongsTo(WorkOrder)`, `belongsTo(User)`.
- `WorkOrder::comments()` como `HasMany` **⚠️ COLISIONA con la columna `comments` existente** → usar otro
  nombre de relación (`commentThread()` / `internalComments()`) o migrar la columna actual.

**UI / Livewire.**
- Componente Livewire reutilizable (p. ej. `WorkOrderComments`) o métodos en las vistas de Sent List,
  embebido **por cada WO** (panel colapsable o modal "Comentarios" junto al número de WO; punto natural:
  el header del WO en `packaging-view.blade.php` ~líneas 41-51).
- Acciones: agregar, listar (cronológico, con nombre + fecha), editar/borrar (solo autor o rol con permiso).
- Permisos vía Spatie (p. ej. `wo-comments.create` / `.delete`).

### Áreas / archivos afectados
- `database/migrations/xxxx_create_work_order_comments_table.php` (nuevo).
- `app/Models/WorkOrderComment.php` (nuevo) + relación en `app/Models/WorkOrder.php`.
- Vistas de Sent List por depto (al menos un **partial compartido**) para mostrar el hilo por WO.
- *(Opcional)* permisos/seeder Spatie.

### Tablas / Modelos / Rutas
- **Tablas:** **1 nueva** (`work_order_comments` **o** `comments` polimórfica). Sin romper el esquema actual.
- **Modelos:** `WorkOrderComment` (nuevo); `WorkOrder` (nueva relación).
- **Rutas:** **ninguna nueva** (el hilo va embebido en las vistas de Sent List existentes).

### Casos borde / decisiones
- ¿El hilo es **por WO global** (se ve en todas las listas/depto) o **por WO dentro de una Sent List**
  específica? (define si se ancla `sent_list_id`).
- ¿Quién puede **ver/editar/borrar**? (todos los deptos vs. solo el área dueña).
- ¿Se **migra** el `WorkOrder.comments` actual al nuevo hilo o se conserva como "nota principal"?
- ¿Adjuntos? (probablemente fuera de alcance v1).

---

# Feature 4 — 4° botón de cierre por faltante (último lote, **solo SIN crimp**)

### Requerimiento
> *"Agregar un cuarto botón al cierre de un lote por Empaque, solo en el último lote y siempre que la
> sumatoria de todos los lotes aún no sea suficiente para completar la orden/WO. En esa última opción el
> cliente puede poner la cantidad del lote, pero el sistema le sugiere la cantidad recomendada (opcional)."*
> **Aclaración del cliente:** *"Solo aplica para los que NO tienen crimp."*

**Ejemplo:** PO = 100,000 pz. Se abren 2 lotes de 50,000. El lote 1 quedó corto (49,000), el lote 2 cumplió
50,000 → total real 99,000. Faltan 1,000 pz para la orden. El sistema debe **abrir un nuevo lote por ese
faltante de la PO** (sugerido 1,000), con la cantidad editable por el usuario.

### Lógica actual en el código
El modal de **Toma de Decisión** (`packaging-view.blade.php`) ofrece **3 opciones**, en `SentListPackagingView`:
1. **Completar Lote** → `decisionCompleteLot()` (botón en `packaging-view.blade.php:459`) — reinicia el
   **mismo** lote con sus faltantes ([SentListPackagingView.php:192](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L192)).
2. **Nuevo Lote** → `decisionNewLot()` (botón `:472`) / `confirmCreateLot()` — crea lote nuevo con
   `decLotTotal − decPacked` (faltante **de ese lote**) y regresa la lista a Materiales
   ([:267](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L267), [:377](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L377)).
3. **Cerrar Lote** → `decisionCloseAsIs()` (botón `:484`/`:498`) — cierra aceptando faltantes ([:280](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L280)).

Datos **ya disponibles** para el cálculo a nivel WO/PO:
- `WorkOrder::getOriginalQuantityAttribute()` = `purchaseOrder->quantity` (total de la PO/WO) — [WorkOrder.php:221](../../../app/Models/WorkOrder.php#L221).
- `WorkOrder::$sent_pieces` (acumulado real, mantenido por `updateSentPieces()` — [:283](../../../app/Models/WorkOrder.php#L283)).
- `WorkOrder::getPendingQuantityAttribute()` = `max(0, original − sent_pieces)` — [:229](../../../app/Models/WorkOrder.php#L229)
  → **este es el "faltante de la PO" sugerido**.
- `Lot::is_crimp` vía `workOrder->purchaseOrder->part->is_crimp`; el modal ya calcula `$decIsCrimp`
  en `openDecisionModal()` ([:167](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L167)).
- El campo de cantidad del modal de creación (`createLotQuantity`) **ya es editable** (`packaging-view.blade.php:652`).

### Diferencia clave vs. "Nuevo Lote" actual
| | Nuevo Lote (existente) | 4° botón (nuevo) |
| - | - | - |
| Nivel del faltante | Del **lote** (`total lote − empacadas`) | De la **WO/PO** (`qty PO − Σ piezas reales`) |
| Cuándo aparece | Si hay faltantes/sobrantes en el lote | Solo en el **último lote** del WO **y** si `pending_quantity(WO) > 0` |
| Tipo de parte | Crimp y no-crimp | **Solo NO-crimp** |
| Cantidad | `total − empacadas` | **Sugerida** = `pending_quantity(WO)`, **editable** |

### Lógica nueva (cómo debe funcionar)
1. **Condición de visibilidad del 4° botón** (en `openDecisionModal()`, por lote):
   - La parte es **NO crimp** (`!$decIsCrimp`).
   - El lote es el **último** del WO (mayor `lot_number` / último creado entre los lotes no cancelados).
   - La **sumatoria de todos los lotes** del WO aún **no completa** la PO/WO → `workOrder->pending_quantity > 0`.
2. **Cálculo de sugerencia:** `cantidadSugerida = workOrder->pending_quantity` (= `qty PO − sent_pieces`),
   precargada y **editable** (igual que `createLotQuantity` hoy).
3. **Acción:** crear un **nuevo lote** en el mismo WO con la cantidad confirmada (reutilizar el flujo de
   `confirmCreateLot()` con un `createLotType` nuevo, p. ej. `'wo_shortfall'`). Como es **no-crimp**,
   **no** se crea kit (el `Lot` actúa como kit).
4. **Estado de la lista:** definir si, como en "Nuevo Lote", la Sent List **regresa a Materiales** para que
   el lote nuevo recorra el flujo (probablemente **sí**).

### Áreas / archivos afectados
- `app/Livewire/Admin/SentLists/SentListPackagingView.php`:
  - Nuevo método (p. ej. `decisionWoShortfall()`) + cálculo de `pendingWo` y flag `canOfferWoShortfall`
    en `openDecisionModal()`.
  - Adaptar `confirmCreateLot()` para el nuevo tipo (`'wo_shortfall'`: sin kit, no-crimp).
- `resources/views/livewire/admin/sent-lists/packaging-view.blade.php`:
  - 4° botón en el grid de decisiones (~líneas 456-498), visible **solo** bajo las condiciones.
  - Campo de cantidad con sugerencia editable en el modal de creación (~líneas 649-660).

### Tablas / Modelos / Rutas
- **Tablas:** **ninguna** prevista — se reutiliza `lots` y los campos de cierre/decisión existentes.
  *(Opcional)* registrar la decisión en `LotCompletionLog` o un nuevo valor de `closure_decision` para trazabilidad.
- **Modelos:** `WorkOrder` (ya expone `pending_quantity`/`original_quantity`), `Lot` (`closure_decision`).
  **Sin modelos nuevos.**
- **Rutas:** **ninguna nueva**.

### Casos borde / decisiones
- ¿"Último lote" = mayor `lot_number`, o el último por `created_at`? (excluir cancelados / soft-deleted).
- Validación: cantidad ingresada `>= 1`; ¿permitir **exceder** `pending_quantity`? (probable permitir, pero advertir).
- Interacción con `updateSentPieces()`: el nuevo lote, al completarse, debe sumar al `sent_pieces` del WO
  **sin doble conteo** (la lógica ya distingue lotes con ciclo vs. simples).
- ¿Aplica a nivel **PO** con múltiples WO, o solo dentro de un **WO**? El ejemplo habla de PO; en el modelo,
  PO→WO→Lots. **Confirmar** (en muchos casos 1 PO ↔ 1 WO).
- ¿Mutuamente excluyente o complementario con "Nuevo Lote" cuando ambos aplicarían?

---

# Consolidado — Tablas, Modelos y Rutas

| Feature | Tablas | Modelos | Rutas | Vistas |
| ------- | ------ | ------- | ----- | ------ |
| 2 Alarmas | sin cambios (opc. prefs en `users`) | `User` (opc.) | ninguna | `tv-display`, `shipping-list-display`, dashboards de área + `public/sounds/alarm.mp3` |
| 3 Comentarios | **1 nueva** (`work_order_comments` o `comments` polimórfica) | **`WorkOrderComment`** (nuevo) + `WorkOrder` | ninguna | partial de comentarios en las vistas de Sent List por depto |
| 4 4° botón | sin cambios (opc. `LotCompletionLog`) | `WorkOrder`, `Lot` | ninguna | `packaging-view.blade.php` (grid de decisiones + modal de cantidad) |

# Preguntas abiertas (para el cliente / definición)

**Feature 2 (alarmas):**
- ¿Polling (latencia ≤30 s, sin infra) o Reverb (instantáneo, requiere servidor)?
- ¿Qué transición dispara la alarma y es global o por área/rol del usuario?

**Feature 3 (comentarios):**
- ¿Hilo por WO global o por WO dentro de cada Sent List?
- ¿Permisos de ver/editar/borrar por departamento?
- ¿Se conserva o migra el `WorkOrder.comments` actual?

**Feature 4 (4° botón):**
- ¿El faltante se mide contra la cantidad de la **PO** o de la **WO**?
- ¿"Último lote" por número de lote o por fecha de creación?
- ¿La lista regresa a Materiales al crear el lote del faltante?
