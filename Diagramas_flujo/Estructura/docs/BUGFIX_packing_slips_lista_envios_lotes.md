# Analisis Tecnico: Bugs en Modulo Packing Slips — Lista de Envios y Lotes

**Fecha:** 2026-05-19
**Modulo:** Packing Slips (FPL-10)
**Prioridad:** Alta
**Estado:** En analisis

---

## Resumen Ejecutivo

Se identificaron tres bugs relacionados entre si en el modulo de Packing Slips:

1. **Bug 1 — Lista de envios no carga el primer lote despues de crear o completar un PS:** Despues de que `ShippingQueue::createPackingSlip()` crea exitosamente un PS, el componente permanece en la misma pagina y muestra `$successMessage`. Sin embargo, la lista de lotes en cola (`$lotsInQueue`) no se refresca de forma visible porque el componente Livewire ya ejecuto `render()` antes de que el usuario interactue de nuevo. El primer lote del nuevo PS no desaparece de la vista inmediatamente en algunos escenarios de re-render parcial.

2. **Bug 2 — Desglose por WO en el panel de edicion de lotes no respeta `purchaseOrder->wo`:** El panel de edicion de lotes en `packing-slip-show.blade.php` calcula el preview del codigo WO usando `$lot->workOrder->external_wo_number` directamente, ignorando el fallback a `purchaseOrder->wo` que implementa `hasExternalWoNumber()` y `buildWoCode()`. Esto provoca que lotes cuyo WO numero proviene de `purchaseOrder->wo` aparezcan como "Sin WO externo" en la columna Work Order del panel de edicion.

3. **Bug 3 — Sumatoria total por lote se ejecuta incorrectamente en la lista:** En `packing-slip-list-v2.blade.php`, la columna "Piezas" usa `$ps->total_quantity` que internamente ejecuta una nueva consulta SQL (`items()->sum('quantity_packed')`). Cuando la relacion `items` ya fue eager-loaded en `PackingSlipList::render()` con `with(['creator', 'items'])`, esta segunda consulta es redundante e innecesaria. Ademas, si por cualquier razon la coleccion en memoria difiere de la base de datos (por ejemplo por cache de Livewire entre renders), el total puede mostrar un valor inconsistente con la suma visible de items individuales.

---

## Analisis del Flujo Actual

### Flujo de Creacion de Packing Slip (dos rutas)

```
RUTA A: ShippingQueue (app/Livewire/Admin/Shipping/ShippingQueue.php)
  Usuario selecciona lotes en la cola → openCreatePsModal() → createPackingSlip()
    └─ PackingSlip::create([status => draft])
    └─ PackingSlipItem::create() x N lotes
    └─ $successMessage = "..." (permanece en ShippingQueue)
    └─ NO hay redirect — el componente re-renderiza en la misma pagina
    └─ $lotsInQueue = Lot::readyForShipping()...paginate(25)  ← re-ejecutado en render()
    └─ Como los lotes ya tienen PackingSlipItem, scopeReadyForShipping los filtra correctamente

RUTA B: PackingSlipCreate (app/Livewire/Admin/PackingSlips/PackingSlipCreate.php)
  Usuario llena formulario → save()
    └─ PackingSlip::create([status => draft])
    └─ PackingSlipItem::create() x N lotes
    └─ $this->redirect(route('admin.packing-slips.show', $packingSlip), navigate: true)
    └─ PackingSlipShow::mount() carga el nuevo PS
```

### Flujo de Carga de Lista de Envios (PackingSlipList)

```
PackingSlipList::render()  (app/Livewire/Admin/PackingSlips/PackingSlipList.php)
  └─ Query: PackingSlip::with(['creator', 'items'])->search()->orderBy()->paginate()
  └─ $packingSlips retorna LengthAwarePaginator ordenado por created_at DESC
  └─ Vista: packing-slip-list-v2.blade.php
       └─ @forelse ($packingSlips as $ps)
            └─ $ps->items->count()          ← usa coleccion eager-loaded (OK)
            └─ $ps->total_quantity          ← ejecuta nueva query SQL (BUG 3)
```

### Flujo de Desglose por WO en PackingSlipShow

```
PackingSlipShow::render()  (app/Livewire/Admin/PackingSlips/PackingSlipShow.php L.336-365)
  └─ $itemsGroupedByPo = $this->packingSlip->items          ← usa coleccion en memoria
       ->groupBy(fn ($item) => $item->lot?->workOrder?->purchaseOrder?->po_number ?? 'Sin PO')
       ->map(fn ($poItems) => $poItems->sortByDesc('quantity_packed')->values())

  En la vista (packing-slip-show.blade.php):
  └─ Panel de Items: agrupa correctamente por PO
       └─ Usa $item->wo_number_ps (snapshot en BD — CORRECTO)
       └─ Subtotal por PO: $poItems->sum('quantity_packed')   ← usa coleccion en memoria (OK)
       └─ Total general: $packingSlip->items->sum('quantity_packed') ← coleccion en memoria (OK)

  └─ Panel de Edicion de Lotes (L.366-421):
       └─ $availableLots = Lot con readyForShipping + lotes actuales del PS
       └─ Preview de WO (L.379-388): USA $lot->workOrder->external_wo_number DIRECTAMENTE
          └─ IGNORA el fallback a purchaseOrder->wo  ← BUG 2
```

---

## Diagrama de Flujo del Proceso

```
[Usuario en ShippingQueue]
        |
        v
[Selecciona Lotes]
        |
        v
[openCreatePsModal()] --> Modal visible
        |
        v
[createPackingSlip()]
        |
        +--[Validar lotes en cola]
        |
        +--[Validar hasExternalWoNumber()]
        |         ↑
        |    Usa getEffectiveWoNumber():
        |    external_wo_number ?? purchaseOrder->wo
        |
        +--[DB::beginTransaction()]
        |
        +--[PackingSlip::create(status=draft)]
        |
        +--[PackingSlipItem::create() x N] <-- buildWoCode() usa getEffectiveWoNumber()
        |
        +--[DB::commit()]
        |
        v
[$successMessage = "PS creado"] <-- componente re-renderiza
        |
        v
[render() en ShippingQueue]
        |
        v
[Lot::readyForShipping()->paginate()] <-- lotes con PackingSlipItem estan excluidos
        |
        v
[Vista muestra $successMessage + lista actualizada]
        |
        *---- PROBLEMA: Primer lote puede aparecer 
              brevemente si hay cache de DOM de Livewire/Alpine


[Usuario en PackingSlipList]
        |
        v
[render()]
        |
        +--[PackingSlip::with(['creator','items'])->paginate()]
        |
        v
[Vista packing-slip-list-v2]
        |
        +--[$ps->items->count()]          OK - usa eager load
        |
        +--[$ps->total_quantity]          PROBLEMA: nueva query SQL
                |
                v
          [PackingSlip::getTotalQuantityAttribute()]
                |
                v
          [items()->sum('quantity_packed')] <-- N+1 queries


[PackingSlipShow - Panel Edicion Lotes]
        |
        v
[$availableLots = readyForShipping + currentLotIds]
        |
        v
[Vista muestra preview WO]
        |
        v
[$lot->workOrder->external_wo_number ?? null]  <-- IGNORA purchaseOrder->wo
        |
        *---- PROBLEMA: Lotes con WO via purchaseOrder->wo
              muestran "Sin WO externo" aunque son validos
```

---

## Causa Raiz de Cada Problema

### Bug 1 — El primer lote no desaparece de la lista tras crear un PS (ShippingQueue)

**Archivo:** `app/Livewire/Admin/Shipping/ShippingQueue.php`
**Lineas:** 160-261 (`createPackingSlip()`) y 444-502 (`render()`)

**Causa raiz:** No existe un bug de logica en la consulta SQL. El `scopeReadyForShipping` excluye correctamente los lotes que ya tienen un `PackingSlipItem` mediante `whereDoesntHave('packingSlipItem')`. Sin embargo, hay dos escenarios donde el primer lote puede parecer que no desaparece:

1. **Escenario A — Paginator stale:** Si el usuario esta en una pagina diferente a la primera del paginador (p.ej. pagina 2), despues de crear el PS el componente regresa a la pagina actual. Si los lotes del PS estaban en la pagina 1, el usuario los ve desaparecer correctamente. Si estaban en otra pagina, el desplazamiento de registros puede parecer que "falta" un lote.

2. **Escenario B — Livewire wire:navigate con cache de DOM:** Cuando se usa `wire:navigate`, Livewire puede servir una version del DOM cacheada antes de que la consulta fresca se ejecute, mostrando brevemente el lote que ya fue asignado al PS.

3. **Escenario C — Flash messages y re-render:** El `$successMessage` se muestra en el mismo render en que se creo el PS. Si el usuario tiene lotes seleccionados de paginas diferentes, `render()` puede no reflejar el estado mas reciente hasta el siguiente ciclo completo de Livewire.

**El problema real mas grave:** En `PackingSlipList` (no `ShippingQueue`), cuando el usuario navega a la lista de PS despues de crear uno desde `ShippingQueue`, el nuevo PS deberia aparecer en la primera posicion (orden por `created_at DESC`). Si el usuario estaba en una pagina diferente a la 1, el nuevo PS no es visible.

### Bug 2 — Desglose por WO ignora `purchaseOrder->wo` en el panel de edicion

**Archivo:** `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php`
**Lineas:** 379-388

**Causa raiz:** El codigo PHP embebido en la vista del panel de edicion calcula el preview del codigo WO usando directamente `$lot->workOrder->external_wo_number`, omitiendo el fallback definido en `WorkOrder::getEffectiveWoNumber()`:

```php
// CODIGO ACTUAL (INCORRECTO) — lineas 379-382:
$woPreview = $lot->workOrder?->external_wo_number
    ? 'W0' . $lot->workOrder->external_wo_number . str_pad($lot->lot_number, 3, '0', STR_PAD_LEFT)
    : null;
```

La arquitectura correcta del sistema usa `getEffectiveWoNumber()` que implementa la prioridad:

```
external_wo_number ?? purchaseOrder->wo ?? null
```

Esto esta implementado correctamente en:
- `WorkOrder::buildWoCode()` — linea 115 de `app/Models/WorkOrder.php`
- `WorkOrder::hasExternalWoNumber()` — linea 131 de `app/Models/WorkOrder.php`
- `PackingSlipCreate::save()` — linea 94 de `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php`
- `ShippingQueue::createPackingSlip()` — linea 214 de `app/Livewire/Admin/Shipping/ShippingQueue.php`
- `PackingSlipShow::updateLots()` — linea 313 de `app/Livewire/Admin/PackingSlips/PackingSlipShow.php`

Pero esta ROTO en:
- La vista `packing-slip-show.blade.php` en el bloque del panel de edicion (lineas 379-388)

**Impacto:** Un lote cuya WO tiene `external_wo_number = null` pero `purchaseOrder->wo = "2032137"` aparece como "Sin WO externo" en el panel de edicion aunque el sistema SI lo aceptara al guardar (porque `hasExternalWoNumber()` retorna `true`). Esto confunde al usuario y puede llevarle a creer que el lote no puede incluirse en el PS.

### Bug 3 — Sumatoria total por lote usa N+1 queries

**Archivo principal:** `app/Models/PackingSlip.php` lineas 257-260 (`getTotalQuantityAttribute`)
**Archivo de consumo:** `resources/views/livewire/admin/packing-slips/packing-slip-list-v2.blade.php` linea 181

**Causa raiz:**

```php
// En PackingSlip.php — L.257-260:
public function getTotalQuantityAttribute(): int
{
    return (int) $this->items()->sum('quantity_packed');  // NUEVA QUERY SQL
}
```

El metodo usa `$this->items()` (relacion, retorna QueryBuilder) en lugar de `$this->items` (coleccion ya cargada en memoria). Cuando `PackingSlipList::render()` hace `PackingSlip::with(['creator', 'items'])->paginate()`, los items ya estan en memoria. Pero `getTotalQuantityAttribute()` ignora la coleccion eager-loaded y ejecuta una nueva consulta SQL por cada PS en la pagina.

Con 10 PS por pagina (el valor por defecto `$perPage = 10`), esto genera 10 queries SQL adicionales innecesarias solo para calcular el total de piezas.

**Impacto de inconsistencia:** Si entre el eager-load y la llamada a `total_quantity` alguna otra transaccion modifica los items, el valor de `total_quantity` puede diferir del count visible de items. Aunque esto es improbable en la practica, el patron es arquitecturalmente incorrecto.

**Nota adicional sobre la sumatoria en la vista show:** En `packing-slip-show.blade.php` el footer de la tabla usa correctamente `$packingSlip->items->sum('quantity_packed')` (coleccion en memoria), asi que el total en la vista de detalle es correcto. El bug de N+1 solo afecta a `packing-slip-list-v2.blade.php`.

---

## Mapa de Archivos Involucrados

| Archivo | Linea(s) | Rol | Estado |
|---|---|---|---|
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | 160-261 | Crea PS desde la cola de despacho | Sin bug de logica, pero sin feedback de navegacion |
| `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php` | 65-108 | Crea PS desde formulario dedicado | Correcto |
| `app/Livewire/Admin/PackingSlips/PackingSlipList.php` | 81-111 | Lista paginada de PS | Eager-load correcto, pero no puede controlar el accessor |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | 259-331, 336-365 | Vista de detalle y edicion de lotes | Logica correcta en PHP, bug en vista blade |
| `app/Models/PackingSlip.php` | 257-260 | Accessor `total_quantity` | BUG: usa relacion en lugar de coleccion |
| `app/Models/WorkOrder.php` | 96-134 | `getEffectiveWoNumber`, `buildWoCode`, `hasExternalWoNumber` | Correcto |
| `resources/views/livewire/admin/packing-slips/packing-slip-list-v2.blade.php` | 181 | Muestra total de piezas por PS | Consume accessor con bug |
| `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php` | 379-388 | Preview WO en panel de edicion | BUG: ignora fallback purchaseOrder->wo |

---

## Propuesta de Solucion Tecnica

### Solucion Bug 1 — Mejorar feedback visual despues de crear PS en ShippingQueue

El flujo logico de creacion de PS en `ShippingQueue` es correcto (el scope excluye lotes con PS asignado). El problema es de UX: el usuario no tiene un punto de navegacion claro hacia el PS recien creado, y si la pagina tiene muchos lotes puede no notar que la lista se actualizo.

**Cambio en:** `app/Livewire/Admin/Shipping/ShippingQueue.php`

Reemplazar el final del bloque `DB::commit()` en `createPackingSlip()`:

```php
// ANTES (lineas 244-249):
$this->successMessage = "Packing Slip {$packingSlip->ps_number} creado exitosamente en estado Borrador.";
$this->showCreatePsModal = false;
$this->selectedLotIds = [];
$this->labelSpecs = [];
$this->psNotes = '';
$this->errorMessage = null;

// DESPUES — redirigir a la lista de PS con banner de confirmacion:
$this->showCreatePsModal = false;
$this->selectedLotIds = [];
$this->labelSpecs = [];
$this->psNotes = '';
$this->errorMessage = null;

session()->flash('flash.banner', "Packing Slip {$packingSlip->ps_number} creado exitosamente.");
session()->flash('flash.bannerStyle', 'success');

$this->redirect(route('admin.packing-slips.index'), navigate: true);
```

**Alternativa** (si se prefiere permanecer en ShippingQueue): agregar un enlace directo al PS en el mensaje de exito:

```php
$psUrl = route('admin.packing-slips.show', $packingSlip);
$this->successMessage = "Packing Slip <a href='{$psUrl}' wire:navigate class='underline font-semibold'>{$packingSlip->ps_number}</a> creado exitosamente.";
```

Y en la vista `shipping-queue.blade.php`, cambiar `{{ $successMessage }}` por `{!! $successMessage !!}` solo en ese parrafo.

### Solucion Bug 2 — Usar `buildWoCode()` en el panel de edicion de lotes

**Cambio en:** `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php`
**Lineas:** 379-388

```php
// ANTES (incorrecto — ignora purchaseOrder->wo):
$woPreview = $lot->workOrder?->external_wo_number
    ? 'W0' . $lot->workOrder->external_wo_number . str_pad($lot->lot_number, 3, '0', STR_PAD_LEFT)
    : null;

// DESPUES (correcto — usa el mismo metodo que el backend):
$woPreview = $lot->workOrder?->buildWoCode((int) $lot->lot_number);
```

Este cambio es de una sola linea y hace que el panel de edicion use exactamente el mismo metodo que `PackingSlipCreate::save()`, `PackingSlipShow::updateLots()` y `ShippingQueue::createPackingSlip()`. Elimina la inconsistencia entre lo que se muestra al usuario y lo que se guarda en base de datos.

**Notar que el mismo error existe en `packing-slip-create-v2.blade.php` en la columna Work Order:**

```php
// packing-slip-create-v2.blade.php linea 118-119:
$woPreview = $lot->workOrder?->buildWoCode((int) $lot->lot_number);
```

Revisar si esta vista ya usa `buildWoCode()`. Segun la lectura del archivo en linea 118:

```php
$woPreview = $lot->workOrder?->buildWoCode((int) $lot->lot_number);
```

Esta vista ya usa el metodo correcto. Solo `packing-slip-show.blade.php` tiene el bug.

### Solucion Bug 3 — Eliminar N+1 en `getTotalQuantityAttribute`

**Opcion A (recomendada) — Hacer el accessor inteligente para usar la coleccion si ya esta cargada:**

**Cambio en:** `app/Models/PackingSlip.php` lineas 257-260

```php
// ANTES:
public function getTotalQuantityAttribute(): int
{
    return (int) $this->items()->sum('quantity_packed');
}

// DESPUES — usa la relacion cargada si existe, evita N+1:
public function getTotalQuantityAttribute(): int
{
    if ($this->relationLoaded('items')) {
        return (int) $this->items->sum('quantity_packed');
    }

    return (int) $this->items()->sum('quantity_packed');
}
```

Esta solucion es retrocompatible, no requiere cambios en vistas ni en el componente Livewire, y elimina las N queries innecesarias cuando `items` ya fue eager-loaded.

**Opcion B — Calcular el total directamente en la vista usando la coleccion:**

**Cambio en:** `resources/views/livewire/admin/packing-slips/packing-slip-list-v2.blade.php` linea 181

```php
// ANTES:
{{ number_format($ps->total_quantity) }}

// DESPUES — usa coleccion en memoria:
{{ number_format($ps->items->sum('quantity_packed')) }}
```

Esta opcion es mas simple y directa pero acoplada a la vista. La Opcion A es arquitecturalmente superior porque protege a cualquier futuro uso de `total_quantity` en contextos sin eager-load.

**Recomendacion:** Implementar ambas (Opcion A en el modelo + Opcion B en la vista para mayor claridad), o solo la Opcion A si se quiere mantener el accessor como interfaz publica.

---

## Plan de Implementacion

### Paso 1 — Corregir el accessor N+1 (Bajo riesgo, alto impacto)

Modificar `app/Models/PackingSlip.php`:

```php
public function getTotalQuantityAttribute(): int
{
    if ($this->relationLoaded('items')) {
        return (int) $this->items->sum('quantity_packed');
    }

    return (int) $this->items()->sum('quantity_packed');
}
```

Verificar que `PackingSlipList::render()` mantiene `with(['creator', 'items'])` en la query (ya lo hace — linea 83). No se requieren cambios adicionales.

### Paso 2 — Corregir el preview de WO en el panel de edicion (Bajo riesgo, critico para UX)

Modificar `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php`, bloque del panel de edicion de lotes:

```php
// Reemplazar lineas 379-382:
@php
    $woPreview = $lot->workOrder?->buildWoCode((int) $lot->lot_number);
@endphp
```

Verificar que `$availableLots` fue cargado con `with(['workOrder.purchaseOrder.part'])` — ya lo hace en `PackingSlipShow::render()` linea 353.

### Paso 3 — Mejorar feedback de creacion en ShippingQueue (Medio riesgo, mejora UX)

Evaluar con el equipo si se prefiere:
- A) Redirigir al indice de PS tras crear uno desde ShippingQueue
- B) Mostrar enlace clickeable al PS creado en el mensaje de exito dentro de la misma pagina

Implementar la opcion elegida en `app/Livewire/Admin/Shipping/ShippingQueue.php`.

### Paso 4 — Pruebas

Verificar manualmente:
1. Crear un PS desde ShippingQueue con lotes cuyo WO usa `purchaseOrder->wo` (no `external_wo_number`). El lote debe aceptarse y el codigo WO debe generarse correctamente.
2. Abrir el detalle de un PS en borrador y activar el panel de edicion. Los lotes cuyo WO viene de `purchaseOrder->wo` deben mostrar el codigo WO correcto (no "Sin WO externo").
3. En la lista de PS, verificar que la columna "Piezas" muestra el mismo valor que la suma de items en el detalle.
4. Con las DevTools del navegador, confirmar que la lista de PS no lanza N+1 queries (se puede ver en el profiler de Laravel Debugbar o en los logs).

---

## Archivos que Deben Modificarse

| # | Archivo | Tipo de Cambio | Lineas afectadas |
|---|---|---|---|
| 1 | `app/Models/PackingSlip.php` | Modificar accessor para evitar N+1 | 257-260 |
| 2 | `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php` | Reemplazar calculo manual de WO con llamada a `buildWoCode()` | 379-382 |
| 3 | `app/Livewire/Admin/Shipping/ShippingQueue.php` | Mejorar feedback post-creacion de PS | 244-249 |

---

## Consideraciones Adicionales

### Por que `getTotalQuantityAttribute` es un accessor y no un campo calculado

El accessor actual es correcto conceptualmente — el total de piezas es un valor derivado que no debe almacenarse en la BD para evitar inconsistencias. La unica mejora es hacer que use la coleccion en memoria cuando este disponible.

### Consistencia de `hasExternalWoNumber` en la arquitectura

El metodo `hasExternalWoNumber()` en `WorkOrder` (linea 131) ya implementa la logica correcta:

```php
public function hasExternalWoNumber(): bool
{
    return !empty($this->getEffectiveWoNumber());
}
```

Y `getEffectiveWoNumber()` (linea 96) implementa el fallback correcto:

```php
public function getEffectiveWoNumber(): ?string
{
    return $this->external_wo_number ?? $this->purchaseOrder?->wo ?? null;
}
```

La raiz del Bug 2 es que la vista `packing-slip-show.blade.php` accede directamente a `$lot->workOrder->external_wo_number` en lugar de delegar al modelo con `buildWoCode()`. Este es un patron de violacion de encapsulacion — la vista conoce detalles de implementacion que deberian estar ocultos detras del metodo del modelo.

### Validacion de `hasExternalWoNumber` vs display en panel de edicion

Hay una inconsistencia adicional en el panel de edicion: el sistema valida correctamente con `hasExternalWoNumber()` al guardar (`updateLots()` linea 279), pero muestra incorrectamente "Sin WO externo" en el display previo. Esto crea confusion al usuario:

1. El usuario ve "Sin WO externo" para un lote (porque el display tiene el bug)
2. Aun asi selecciona el lote (el checkbox no esta deshabilitado en el panel de edicion del show)
3. Al guardar, `updateLots()` acepta el lote porque `hasExternalWoNumber()` lo valida correctamente
4. El item se guarda con el codigo WO correcto en `wo_number_ps`
5. La tabla de items muestra el codigo correcto porque usa el snapshot guardado

El resultado final es correcto (el codigo WO se guarda bien), pero la experiencia durante la edicion es confusa. La correccion del Bug 2 elimina esta inconsistencia.

### Campo `wo_number_ps` como snapshot inmutable

El campo `packing_slip_items.wo_number_ps` es un snapshot generado al momento de crear el item. Una vez guardado, no depende de `external_wo_number` ni de `purchaseOrder->wo`. Esto es correcto por diseno (principio de snapshot inmutable declarado en la migracion `2026_03_08_100003_create_packing_slip_items_table.php` linea 18). Los bugs descritos no afectan los datos ya guardados en BD — solo afectan la experiencia de usuario durante la creacion/edicion.
