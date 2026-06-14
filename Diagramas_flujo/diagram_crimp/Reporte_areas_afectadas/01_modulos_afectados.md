# 01 — Análisis módulo por módulo

> Para cada módulo: **(a)** qué hace hoy (vieja forma), **(b)** qué cambia (nueva forma),
> **(c)** archivos / modelos / tablas / rutas afectados. Referencias a código con archivo:línea.

---

> ## Mapeo de conceptos y flujo (según diagramas 1–4 + `Flujo.mkd`)
>
> | Concepto del cliente | Entidad / tabla real | Nota |
> | -------------------- | -------------------- | ---- |
> | **Viajero** | `Lot` (`lots`) — es el mismo "lote", **solo se llama "viajero" en crimp** | conserva WO + cantidad total |
> | **Lote de CRIMP** | **tabla NUEVA `crimp_lots`** (hija del viajero) | **el `Kit` se ELIMINA** del flujo crimp y lo sustituye esta entidad nueva |
> | **Lote de fabricante** | columna `crimp_lots.lote_fabricante` (+ `comments`) | campo **manual** que se escribe al **crear** el lote de crimp (Materiales) y se **reutiliza en Empaque** (`Flujo.mkd §3.2-3.3`) |
>
> Relación: **Viajero (`Lot`) 1 → N Lotes de CRIMP (`crimp_lots`)**. El `Kit` (`kits`, `kit_lot`,
> `kit_approval_cycles`, `kit_incidents`) **deja de usarse para crimp** (queda solo como historial).
>
> ### Flujo de los diagramas (8 pasos) y dónde cae cada módulo
> | Paso (diagramas 1-2) | Acción | Módulo |
> | --- | --- | --- |
> | 1 | Crear **viajero** para la orden (WO + cantidad total) | M1 Materiales |
> | 2 | Agregar **lotes de CRIMP** al viajero (+ lote de fabricante) | M1 Materiales / M3 Capacity |
> | 3 | Entregar CRIMP a Empaque | M1 / M6 |
> | 4 | Pesar y empacar: **CRUD pesadas CRIMP + piezas "manguitas"** | M6 Empaque |
> | 5 | **Confirmar piezas y CRIMP** + correo "Empaque Viajero" | M6 + M9 |
> | 6 | Resumen + **toma de decisión** (D1 / D2a-c / D3) | M6 Empaque |
> | 7 | Entrega de viajero ("Viajero recibido") | M6 |
> | 8 | Regresar sobrantes a Materiales | M6 / M1 |
>
> Producción (M4) y Calidad (M5) pesan **a nivel viajero, sin kit** (`Flujo.mkd §4-5`).

---

## M1 · Sent List — Vista de Materiales

**Archivo:** [app/Livewire/Admin/SentLists/SentListMaterialsView.php](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php)
**Vista:** `resources/views/livewire/admin/sent-lists/materials-view.blade.php`
**Ruta de acceso:** dentro de `admin.sent-lists.show` → vista por departamento (Materiales).

### (a) Vieja forma
- `openLotModal()` / `saveLots()` ([:49-128](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php#L49)): crea/edita **lotes** (`Lot`) por WO. Valida que la suma no exceda `wo->original_quantity`.
- `openKitModal()` / `saveKit()` ([:139-179](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php#L139)): **solo CRIMP** — crea un `Kit`, lo asocia a lotes (`kit->lots()->attach`).
- `openKitStatusModal()` / `saveKitStatus()` ([:301-323](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php#L301)): cambia estado del Kit (CRIMP).
- `openMaterialModal()` / `saveMaterial()` ([:275-297](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php#L275)): cambia `material_status` del lote (NO-CRIMP).
- `openSendModal()` ([:192-226](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php#L192)): **bloquea el envío** si un WO CRIMP no tiene kits (`$wo->purchaseOrder->part->is_crimp && $wo->kits->isEmpty()`).
- `sendToInspection()` ([:228-265](../../../app/Livewire/Admin/SentLists/SentListMaterialsView.php#L228)): CRIMP → marca todos los kits `released`; NO-CRIMP → marca `lots.material_status = released`.

### (b) Nueva forma — diagrama **pasos 1-2** (crear viajero + agregar lotes de CRIMP)
- **Paso 1:** el modal de **Lote** pasa a ser modal de **Viajero** (rótulo + textos). El viajero conserva
  la cantidad total (igual que hoy el lote).
- **Paso 2:** **eliminar el modal de Kit** (`openKitModal/saveKit/openKitStatusModal/saveKitStatus`) y, en su
  lugar, **agregar la captura de "lotes de CRIMP"** del viajero (tabla nueva `crimp_lots`), cada uno con su
  **lote de fabricante** (campo de texto manual) + comentarios.
- `openSendModal()`: cambiar la validación "CRIMP sin kit" por "viajero CRIMP sin **lotes de CRIMP**".
- `sendToInspection()`: para CRIMP, la liberación de material se marca **a nivel viajero**
  (equivalente a `material_status = released`), ya no por estado de Kit.
- NO-CRIMP: **sin cambios**.

### (c) Impacto
- **Modelos:** `Lot` (= viajero), **nuevo `CrimpLot`** (lote de crimp), `Kit` (deja de usarse para crimp).
- **Tablas:** **nueva `crimp_lots`** (`crimp_lot_number`, `lote_fabricante`, `quantity`, `comments`);
  `lots` (uso/rótulo como viajero).
- **Vista:** `materials-view.blade.php` (modal lote→viajero; modal kit → modal de **lote de CRIMP** con lote de fabricante).
- **Riesgo:** medio-alto — punto de entrada del flujo; tocar sin afectar NO-CRIMP.

---

## M2 · Materiales — Gestión de Kits (CRUD)

**Archivo:** [app/Livewire/Admin/Materials/KitManagement.php](../../../app/Livewire/Admin/Materials/KitManagement.php)
**Vista:** `resources/views/livewire/admin/materials/kit-management.blade.php`
**Otros:** `KitList`, `KitCreate`, `KitShow` (rutas `admin.kits.*`).

### (a) Vieja forma
- CRUD completo de Kits: crear seleccionando lotes (`selectedLots`), validar que la suma de kits no exceda
  la cantidad de lotes ([:188-198](../../../app/Livewire/Admin/Materials/KitManagement.php#L188)), enviar a inspección
  (`submitToInspection`), historial de aprobación. Pivot `kit_lot`.
- `Kit::generateKitNumber()` ([app/Models/Kit.php:242](../../../app/Models/Kit.php#L242)).

### (b) Nueva forma
- El **Kit se elimina del flujo CRIMP**. Su rol lo asume la nueva entidad **Lote de CRIMP** (`crimp_lots`),
  que **no** tiene ciclo de aprobación de kit (`submitToInspection`, `kit_approval_cycles`).
- Decidir con cliente (ver `03`):
  - **Opción A (recomendada):** dejar el CRUD de Kits **solo para historial/lectura** (no se crean kits nuevos
    para crimp; los lotes de crimp se gestionan desde el modal de Materiales / un CRUD nuevo de Lote de CRIMP).
  - **Opción B:** retirar Kits del menú una vez migrado todo el flujo crimp.
- Los Kits **no** deben re-aparecer como requisito en Inspección/pesadas (ver M4, M5, M7).

### (c) Impacto
- **Modelos:** `Kit`, `KitApprovalCycle`, `KitIncident`, pivot `kit_lot` → **legacy para crimp**; nuevo `CrimpLot`.
- **Rutas:** `admin.kits.index/create/show` ([routes/admin.php:276-278](../../../routes/admin.php#L276)).
- **Riesgo:** medio — el Kit está acoplado en varias áreas; el cambio es **dejar de usarlo**, no borrar datos.

---

## M3 · Capacity Wizard (creación masiva desde capacidad)

**Archivo:** [app/Livewire/Admin/CapacityWizard.php](../../../app/Livewire/Admin/CapacityWizard.php)
**Vista:** `resources/views/livewire/admin/capacity-wizard/step3.blade.php`

### (a) Vieja forma
- En el paso final crea lotes y, para partes crimp, **crea registros `Kit`** y los asocia
  ([:866-889](../../../app/Livewire/Admin/CapacityWizard.php#L866)). Tiene campos de captura `kitNumbers`
  y métodos de modal de kit ([:618](../../../app/Livewire/Admin/CapacityWizard.php#L618)).
- `step3.blade.php` muestra columna/campos "Kit" y `is_crimp` ([:65, :422](../../../app/Livewire/Admin/CapacityWizard.php#L65)).

### (b) Nueva forma
- Para crimp, en lugar de crear `Kit`, crear **viajero (`Lot`) + lotes de CRIMP (`crimp_lots`)** con su
  **`lote_fabricante`** y comentarios.
- Sustituir la captura `kitNumbers` por la captura de **lotes de CRIMP** del viajero (número + lote de fabricante).
- La nota del cliente lo confirma: *"Revisar la parte de capacidad para verificar el cambio que se
  realizará con CRIMP"* (`Flujo.mkd` §3.1).

### (c) Impacto
- **Modelos:** `Kit` (deja de crearse para crimp), nuevo `CrimpLot`, `Lot` (viajero).
- **Vista:** `step3.blade.php` (campos de kit → lotes de CRIMP + lote de fabricante).
- **Riesgo:** medio — es un generador masivo; un error duplica/omite registros en cadena.

---

## M4 · Pesadas de Producción

**Archivo:** [app/Livewire/Admin/Production/WeighingManagement.php](../../../app/Livewire/Admin/Production/WeighingManagement.php)
**Vista:** `resources/views/livewire/admin/production/weighing-management.blade.php`
**Ruta:** `admin.production.weighings` ([routes/admin.php:216](../../../routes/admin.php#L216)).

### (a) Vieja forma
- Al elegir lote (`updatedSelectedLotId` [:58-75](../../../app/Livewire/Admin/Production/WeighingManagement.php#L58)),
  si es crimp carga `$this->kits = $lot->kits` y la UI muestra **select de Kit** (`selectedKitId`).
- La vista muestra el campo "Kit (opcional)" solo si `$isCrimp` (blade `weighing-management.blade.php:211-218`).
- `save()` ([:143-183](../../../app/Livewire/Admin/Production/WeighingManagement.php#L143)) guarda
  `kit_id => $this->isCrimp ? $this->selectedKitId : null` en `weighings`.

### (b) Nueva forma
- Para CRIMP: **eliminar la selección de Kit**. La pesada se registra **a nivel viajero** (igual que NO-CRIMP),
  con `kit_id = null` (`Flujo.mkd` §4).
- Mantener historial: las pesadas viejas con `kit_id` se conservan.

### (c) Impacto
- **Modelo:** `Weighing` (`kit_id` deja de poblarse para crimp; columna se mantiene nullable).
- **Tabla:** `weighings` (sin cambios de esquema; cambia el uso).
- **Vista:** `weighing-management.blade.php` (quitar bloque de kit para crimp).
- **Riesgo:** medio — reportes/consultas que agrupen por `kit_id` quedarán vacíos para crimp (ver M10).

---

## M5 · Pesadas de Calidad

**Archivo:** [app/Livewire/Admin/Quality/QualityWeighings.php](../../../app/Livewire/Admin/Quality/QualityWeighings.php)
**Vista:** `resources/views/livewire/admin/quality/quality-weighings.blade.php`
**Ruta:** `admin.quality.weighings` ([routes/admin.php:225](../../../routes/admin.php#L225)).

### (a) Vieja forma
- `openWeighingModal()` ([:135-148](../../../app/Livewire/Admin/Quality/QualityWeighings.php#L135)): si crimp,
  carga `qualKits = $selectedLot->kits` y expone `qualKitId` / `qualIsCrimp`.
- `saveQualityWeighing()` ([:188-259](../../../app/Livewire/Admin/Quality/QualityWeighings.php#L188)) guarda
  `kit_id => $this->qualKitId` en `quality_weighings`.

### (b) Nueva forma
- Para CRIMP: **eliminar selección de kit/lote de CRIMP**. La pesada de calidad se hace a **nivel viajero**
  (`Flujo.mkd` §5: *"No se selecciona kit. No se selecciona lote de CRIMP"*).
- NO-CRIMP: sin cambios.

### (c) Impacto
- **Modelo:** `QualityWeighing` (`kit_id` deja de poblarse para crimp).
- **Tabla:** `quality_weighings` (sin cambio de esquema; cambia el uso).
- **Vista:** `quality-weighings.blade.php` (quitar bloque kit).
- **Riesgo:** medio.

---

## M6 · Empaque — Pesadas, Confirmación y Decisiones (Paso 5 y 6) 🔴

**Archivo:** [app/Livewire/Admin/SentLists/SentListPackagingView.php](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php)
**Vista:** `resources/views/livewire/admin/sent-lists/packaging-view.blade.php`
**Relacionado:** `PackagingManagement`, `PackagingDashboard`.

### (a) Vieja forma
- **Una sola pesada de empaque** por lote: `openPackagingModal/savePackaging` ([:57-106](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L57))
  crea **un** `PackagingRecord` con `packed_pieces` + `surplus_pieces`. **No separa piezas de CRIMP.**
- Recepción de viajero: `receiveViajero()` ([:125-135](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L125)).
- **Decisiones (Paso 6)** sobre el lote:
  - `decisionCompleteLot()` ([:192-261](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L192)) — reinicia el mismo lote con faltantes; **si crimp, resetea kits a `preparing`** ([:254-256](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L254)).
  - `decisionNewLot()` + `confirmCreateLot()` ([:267-466](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L267)) — crea lote nuevo y **si crimp, crea Kit automáticamente** ([:409-419](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L409)); regresa la lista a Materiales.
  - `decisionCloseAsIs()` ([:280-304](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L280)).
- `decIsCrimp` se calcula en `openDecisionModal()` ([:167](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L167)).

### (b) Nueva forma (es el módulo de mayor impacto)
- **Dos CRUD de pesadas separados** dentro de Empaque (`Flujo.mkd` §6-7, diagramas 2-3):
  1. **Pesadas de piezas / "manguitas"** — tabla nueva.
  2. **Pesadas de CRIMP** — tabla nueva.
  Cada una con: viajero, cantidad pesada, peso, usuario, fecha, comentarios.
- **Confirmación (Paso 5, diagrama 3):** modal que selecciona el lote de CRIMP, captura ambas pesadas,
  muestra resumen "Empaque Terminado" (en pantalla, **sin PDF**) y confirma cantidades completadas/sobrantes
  de **piezas Y CRIMP**.
- **Decisiones (Paso 6, diagrama 4):** ampliar la lógica a:
  - D1 Cerrar lote · D2a Completar CRIMP (`completar CRIMP = piezas sobrantes − CRIMP sobrante`) ·
    D2b Completar piezas/manguitas · D2c Completar piezas y CRIMP · D3 Nuevo lote (redondeo a múltiplos de 100).
  - Adaptar `confirmCreateLot()` / `decisionCompleteLot()`: hoy **crean/reinician un `Kit`** para crimp
    ([:409-419](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L409), [:254-256](../../../app/Livewire/Admin/SentLists/SentListPackagingView.php#L254));
    deben operar sobre **lotes de CRIMP (`crimp_lots`)** y dejar de crear/resetear kits.
- Disparar el **correo "Empaque terminado CRIMP Viajero"** al confirmar (ver M9).

### (c) Impacto
- **Modelos:** nuevos `PackagingPieceWeighing`, `PackagingCrimpWeighing`; `Lot` (viajero), `CrimpLot`,
  `PackagingRecord`.
- **Tablas:** nuevas `packaging_piece_weighings`, `packaging_crimp_weighings`; evaluar campos de
  "completado/sobrante de CRIMP" a nivel viajero en `lots`.
- **Métodos a refactor:** `savePackaging`, `decisionCompleteLot`, `decisionNewLot`, `confirmCreateLot`.
- **Vista:** `packaging-view.blade.php` (modal de empaque, sección de decisiones ~líneas 454-660).
- **Riesgo:** **alto** — concentra el cambio de cierre con doble validación (piezas/CRIMP) + correo.

---

## M7 · Inspección

**Archivo:** [app/Livewire/Admin/Inspection/InspectionList.php](../../../app/Livewire/Admin/Inspection/InspectionList.php)
**Lógica clave:** [app/Models/Lot.php:484-542](../../../app/Models/Lot.php#L484) (`canBeInspected()`, `getInspectionBlockedReason()`, `getReleasedKit()`).
**Ruta:** `admin.quality.inspection` ([routes/admin.php:224](../../../routes/admin.php#L224)).

### (a) Vieja forma
- Para CRIMP, **un lote solo puede inspeccionarse si tiene un `Kit` con estado `released`**
  (`canBeInspected()` consulta `kits()->where('status', RELEASED)`).
- Para NO-CRIMP, requiere `material_status = released`.
- `getInspectionBlockedReason()` devuelve mensajes basados en el estado del Kit.

### (b) Nueva forma
- Para CRIMP, la condición de inspección debe pasar a **nivel viajero** (equivalente a `material_status`),
  ya que no habrá Kit. Reescribir `canBeInspected()` / `getInspectionBlockedReason()` para que crimp
  **no dependa de `kits()`**.
- NO-CRIMP: sin cambios.

### (c) Impacto
- **Modelo:** `Lot` (`canBeInspected`, `getInspectionBlockedReason`, `getReleasedKit`).
- **Vista/Componente:** `InspectionList` y su blade (mensajes/labels que digan "kit").
- **Riesgo:** medio — es un **gate** del flujo: si queda mal, los viajeros crimp no avanzan a inspección.

---

## M8 · Semáforos — TV Monitor y Dashboards de área

**Archivos:**
- [app/Livewire/Public/TvMonitor.php](../../../app/Livewire/Public/TvMonitor.php) (público `/tv`)
- [app/Traits/ComputesAreaStats.php](../../../app/Traits/ComputesAreaStats.php)
- Dashboards: `MaterialsHubDashboard`, `ProductionHubDashboard`, `QualityAreaDashboard`, `PackagingDashboard`.
- También `TvDisplay`, `ShippingListDisplay` (otra pantalla con `is_crimp`).

### (a) Vieja forma
- La **columna "Kit"** se calcula así: si `part->is_crimp`, usa el **estado del Kit** del lote
  (`released → green`, otro → yellow, ninguno → gray); si no, usa `material_status`.
  Ver `TvMonitor` ([:57-67](../../../app/Livewire/Public/TvMonitor.php#L57)) y `ComputesAreaStats` ([:46-57](../../../app/Traits/ComputesAreaStats.php#L46)).
- Eager-load incluye `lots.kits` en ambos.

### (b) Nueva forma
- Para CRIMP, la columna "Kit" debe basarse en el **estado de liberación a nivel viajero** (no en `kits`).
  Decidir si la columna se renombra a "Material/Viajero" para crimp.
- Quitar la dependencia de `lots.kits` en el cálculo de crimp (puede dejarse el eager-load para legacy).

### (c) Impacto
- **Componentes/Traits:** `TvMonitor`, `ComputesAreaStats`, 4 dashboards de área.
- **Vistas:** `tv-display.blade.php`, `shipping-list-display.blade.php`, dashboards.
- **Riesgo:** medio — visual + agregaciones; no bloquea el flujo pero confunde a operadores si queda mal.

> Nota: este módulo se cruza con la **Feature 2 (alarmas sonoras)** del documento `5-Nuevas_features.mkd`,
> que también toca `ComputesAreaStats` y las vistas con `wire:poll`.

---

## M9 · Correos / Notificaciones — "Empaque terminado CRIMP Viajero" 🔴 (nuevo)

### (a) Vieja forma
- **No existe nada.** No hay carpeta `app/Mail` ni `app/Notifications`. Hoy el resumen "Empaque Terminado"
  solo se muestra en pantalla (diagrama 3: *"El documento ya no se descarga en PDF"*). La empacadora
  comunica el resultado **por correo manual** (`Flujo.mkd` §8.1).

### (b) Nueva forma
- Crear un **Mailable** `EmpaqueTerminadoCrimpViajero` con su template Blade y la lógica de envío
  (`Flujo.mkd` §9). Campos: descripción, No. orden (WO+viajero), No. etiquetas (pendiente de validar),
  cantidad en viajero, completadas piezas, completadas CRIMP, sobrante CRIMP, sobrante piezas,
  empacadora, fecha, comentarios. Destinatarios: Empaque + Materiales (diagrama 3).
- Definir disparador (al confirmar empaque) y si se guarda historial del envío.

### (c) Impacto
- **Nuevo:** `app/Mail/EmpaqueTerminadoCrimpViajero.php` + `resources/views/emails/...blade.php`.
- **Config:** `MAIL_*` en `.env` (verificar que hay transporte SMTP configurado; hoy `BROADCAST_CONNECTION=log`).
- **Tabla (opcional):** historial de correos enviados.
- **Riesgo:** alto-funcional — requiere infraestructura de correo inexistente y definición de destinatarios.

---

## M10 · Reportes, Trazabilidad y CRUD de Lots/Kits

**Archivos:** `app/Livewire/Admin/Reports/*`, controladores `ReportController`/`PartsReportController`
(rutas `admin.reports.*` [routes/admin.php:189-208](../../../routes/admin.php#L189)),
`LotShow`/`LotList`/`LotEdit`, `KitList`/`KitShow`, `Lot::getTraceabilityData()` ([app/Models/Lot.php:414-432](../../../app/Models/Lot.php#L414)),
`Kit::getTraceabilityChain()` ([app/Models/Kit.php:343-377](../../../app/Models/Kit.php#L343)).

### (a) Vieja forma
- Trazabilidad y reportes de CRIMP pasan por la cadena **lote → kit**. `Lot::getTraceabilityData()`
  incluye `kits`; `Kit::getTraceabilityChain()` arma la cadena material→kit.
- Reportes agrupan/etiquetan por "lote" y, en producción/calidad, por `kit_id`.

### (b) Nueva forma
- Reemplazar, para crimp, la trazabilidad **lote→kit** por **viajero→lotes de CRIMP** (incluyendo
  `lote_fabricante`). Renombrar etiquetas "Lote"→"Viajero" donde corresponda a crimp.
- Reportes que agrupen por `kit_id` deben agrupar por viajero / lote de CRIMP para crimp.

### (c) Impacto
- **Modelos:** `Lot::getTraceabilityData()`, `Kit::getTraceabilityChain()`.
- **Vistas/Reportes:** dashboards de reportes, PDFs/Excel por área (textos y agrupaciones).
- **Riesgo:** bajo-medio — mayormente presentación; el riesgo es dejar reportes "vacíos" por agrupar por kit.

---

## Resumen de archivos de código tocados (núcleo)

| Capa | Archivos |
| ---- | -------- |
| **Modelos** | `Part`, `Lot` (viajero), `WorkOrder`, `Weighing`, `QualityWeighing`, `PackagingRecord`, `Kit` (legacy) + nuevos `CrimpLot`, `PackagingPieceWeighing`, `PackagingCrimpWeighing` |
| **Livewire** | `SentListMaterialsView`, `KitManagement`, `CapacityWizard`, `WeighingManagement`, `QualityWeighings`, `SentListPackagingView`, `InspectionList`, `TvMonitor`, dashboards de área |
| **Traits** | `ComputesAreaStats` |
| **Vistas** | `materials-view`, `kit-management`, `capacity-wizard/step3`, `weighing-management`, `quality-weighings`, `packaging-view`, `tv-display`, `shipping-list-display`, dashboards, `emails/*` (nuevo) |
| **Mail** | `EmpaqueTerminadoCrimpViajero` (nuevo) |
