# Análisis Técnico: PO/WO Carryover Semanal (Partial Shipment Rollover)

**Fecha:** 2026-05-18  
**Autor:** Agent Architect  
**Estado:** Borrador — pendiente de aprobación técnica  
**Versión:** 1.0

---

## Tabla de Contenidos

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Análisis del Estado Actual](#2-análisis-del-estado-actual)
3. [Diseño de la Solución Propuesta](#3-diseño-de-la-solución-propuesta)
4. [Cambios en Base de Datos](#4-cambios-en-base-de-datos)
5. [Cambios en Modelos](#5-cambios-en-modelos)
6. [Cambios en Lógica de Negocio / Servicios](#6-cambios-en-lógica-de-negocio--servicios)
7. [Cambios en Componentes Livewire / UI](#7-cambios-en-componentes-livewire--ui)
8. [Riesgos Identificados](#8-riesgos-identificados)
9. [Impactos en el Proyecto](#9-impactos-en-el-proyecto)
10. [Archivos y Componentes a Modificar](#10-archivos-y-componentes-a-modificar)
11. [Estimación de Tiempo](#11-estimación-de-tiempo)
12. [Viabilidad General](#12-viabilidad-general)

---

## 1. Resumen Ejecutivo

### Qué se quiere lograr

El cliente reporta que una PO con su WO (Work Order) puede no terminarse dentro de una semana de producción. Actualmente, cuando se realiza un envío parcial (partial shipment), la PO puede quedar "cortada" sin que el sistema tenga un mecanismo para continuar su producción la semana siguiente. El cliente solicita que esa PO incompleta se **enrolle automáticamente (carryover) a la capacidad de la siguiente semana**, usando el mismo registro de PO y WO — sin duplicar datos.

### Es viable?

**Sí, es completamente viable.** La arquitectura actual del proyecto ya contempla el concepto de BackOrder en el diagrama de flujo general (`Flexcon_Tracker_ERP.md`, nodo `P[BackOrder] --> E`), aunque no está implementado. El modelo `WorkOrder` ya tiene los campos `sent_pieces` (piezas enviadas) y `pending_quantity` (accessor calculado) que permiten determinar cuántas piezas faltan. La tabla `sent_list_purchase_orders` (pivot many-to-many entre `SentList` y `PurchaseOrder`) permite que el mismo PO aparezca en múltiples SentLists sin crear registros duplicados.

La solución propuesta consiste en **habilitar que el mismo PO/WO pueda ser incluido en una nueva SentList de la semana siguiente**, con las piezas pendientes como cantidad, y marcar el origen del carryover para trazabilidad.

---

## 2. Análisis del Estado Actual

### 2.1 Estructura de datos relevante

#### Tabla `purchase_orders`

| Campo | Tipo | Rol en el carryover |
|---|---|---|
| `id` | bigint PK | Identidad del PO — NO se duplica |
| `po_number` | string unique | Número de PO del cliente |
| `wo` | string nullable | Número de WO externo (del cliente) |
| `quantity` | integer | Cantidad total originalmente ordenada |
| `status` | enum | `pending`, `approved`, `rejected`, `pending_correction` |

**Hallazgo crítico:** No existe columna `remaining_quantity` ni `shipped_quantity` en `purchase_orders`. La cantidad pendiente se calcula en el modelo `WorkOrder` vía el accessor `pending_quantity_attribute` = `quantity - sent_pieces`.

#### Tabla `work_orders`

| Campo | Tipo | Rol en el carryover |
|---|---|---|
| `id` | bigint PK | Identidad del WO — NO se duplica |
| `purchase_order_id` | FK | Relación 1:1 con `purchase_orders` |
| `sent_list_id` | bigint nullable | FK legacy al primer SentList (flujo antiguo) |
| `sent_pieces` | integer | Piezas acumuladas de lotes `completed` |
| `status_id` | FK | Estado del WO (tabla `statuses_wo`) |
| `scheduled_send_date` | date | Fecha programada de envío |

**Hallazgo clave:** `WorkOrder::pending_quantity_attribute` = `max(0, original_quantity - sent_pieces)`. Este accessor es la fuente de verdad para saber cuántas piezas faltan.

`WorkOrder::isComplete()` retorna `true` cuando `pending_quantity === 0`. Este método es el gatillo natural para detectar WOs terminados vs. con carryover pendiente.

#### Tabla `sent_lists`

| Campo relevante | Tipo | Descripción |
|---|---|---|
| `start_date` | date | Inicio de la semana de producción |
| `end_date` | date | Fin de la semana de producción |
| `status` | enum | `pending`, `confirmed`, `canceled` |
| `current_department` | string | Departamento actual en el flujo |

#### Tabla `sent_list_purchase_orders` (pivot many-to-many)

```
sent_list_id         FK → sent_lists
purchase_order_id    FK → purchase_orders
quantity             integer    (cantidad comprometida para esa semana)
required_hours       decimal    (horas calculadas para esa cantidad)
lot_number           string     (lotes asignados)
```

**Este es el mecanismo clave:** La misma `purchase_order_id` puede estar en múltiples `SentList` ya que la relación es many-to-many. El sistema ya está diseñado para permitirlo. La restricción `unique(['sent_list_id', 'purchase_order_id'])` solo impide que el mismo PO aparezca dos veces en la _misma_ SentList, pero no en SentLists distintas.

#### Lotes (`lots`) y su impacto

Cada `Lot` pertenece a un `WorkOrder`. El `work_order_id` en `lots` NO cambia. Cuando se hace un partial shipment, algunos lotes pasan a estado `completed` y otros quedan en estados intermedios. `WorkOrder::updateSentPieces()` suma solo los lotes `completed` para actualizar `sent_pieces`.

### 2.2 Flujo actual (sin carryover)

```
PO aprobada
  └─→ WO creado (status: Open)
       └─→ CapacityWizard: PO incluida en SentList semana N
            └─→ Lotes creados, flujo por departamentos
                 └─→ Algunos lotes enviados (Packing Slip → Invoice)
                      └─→ WO.sent_pieces actualizado
                           └─→ ¿WO completo?
                                ├─ Sí: WO cierra
                                └─ No: PO queda "flotando" — SIN FLUJO DEFINIDO
```

### 2.3 Filtro actual en `CapacityWizard` que bloquea el carryover

En `CapacityWizard::getAvailablePOsProperty()` existe la siguiente lógica de exclusión:

```php
// Excluir POs ya asignados a cualquier Shipping List (sin importar su status)
->whereDoesntHave('sentLists')
// Excluir POs cuyo WO tiene sent_list_id asignado (flujo legacy)
->whereDoesntHave('workOrder', function($q) {
    $q->whereNotNull('sent_list_id');
})
```

Esta es exactamente la restricción que impide que una PO con carryover aparezca en el wizard de la semana siguiente. **Este es el punto de entrada principal del cambio.**

### 2.4 Diagrama del flujo documentado (del diseño original)

El `Flexcon_Tracker_ERP.md` ya incluye explícitamente el nodo de BackOrder:

```
N{WO Completo?} -->|No| P[BackOrder] --> E[Calcular Capacidad]
```

El sistema fue diseñado con carryover en mente pero nunca se implementó.

---

## 3. Diseño de la Solución Propuesta

### 3.1 Principio de diseño

**No se crea ningún registro nuevo de PO ni de WO.** El carryover es simplemente una nueva entrada en `sent_list_purchase_orders` que apunta al mismo `purchase_order_id` pero con una nueva `SentList` de la semana siguiente. La cantidad en el pivot refleja las piezas pendientes, no la cantidad original del PO.

### 3.2 Definición: ¿Cuándo un PO es elegible para carryover?

Un PO es elegible para aparecer en el Capacity Wizard de la semana siguiente cuando:

1. Su `PurchaseOrder.status` = `approved`
2. Su `WorkOrder.isComplete()` retorna `false` (hay piezas pendientes)
3. Su `WorkOrder.pending_quantity > 0`
4. Tiene al menos una SentList previa en cualquier estado (ya fue planificado antes)
5. No está ya incluido en una SentList activa con `status = 'pending'` de la semana actual

### 3.3 Estrategia de implementación: 3 pasos

#### Paso A — Modificar el filtro del CapacityWizard

Reemplazar `whereDoesntHave('sentLists')` por una condición más granular que permita POs con carryover:

```
Un PO puede aparecer en el Wizard si:
  - NO tiene sentLists activas (nunca fue planificado), O
  - Tiene sentLists previas pero su WO NO está completo y NO está en una sentList pending activa
```

#### Paso B — Agregar columna `is_carryover` y `carryover_from_sent_list_id` al pivot

Esto permite trazabilidad de qué SentList originó el carryover, sin modificar la estructura principal de PO/WO.

#### Paso C — Calcular la cantidad correcta en el Wizard

Cuando un PO en carryover se agrega al Wizard, la cantidad default debe ser `workOrder.pending_quantity`, no `purchaseOrder.quantity`.

### 3.4 Flujo propuesto con carryover

```
PO aprobada
  └─→ WO creado
       └─→ SentList semana N: PO incluida con qty = 1000 piezas
            └─→ 600 piezas enviadas (Packing Slip + Invoice)
                 └─→ WO.sent_pieces = 600
                      └─→ WO.pending_quantity = 400  ← carryover
                           └─→ PO elegible para SentList semana N+1
                                └─→ CapacityWizard semana N+1:
                                     PO aparece con qty sugerida = 400
                                      └─→ SentList N+1: mismo PO/WO,
                                           pivot.quantity = 400
                                           pivot.is_carryover = true
                                           pivot.carryover_from_sent_list_id = ID_semana_N
```

---

## 4. Cambios en Base de Datos

### 4.1 Migración: Agregar campos de carryover al pivot `sent_list_purchase_orders`

**Archivo:** `database/migrations/YYYY_MM_DD_xxxxxx_add_carryover_to_sent_list_purchase_orders.php`

```php
Schema::table('sent_list_purchase_orders', function (Blueprint $table) {
    // Indica si este registro es continuación de una planificación anterior
    $table->boolean('is_carryover')->default(false)->after('lot_number');
    
    // Referencia al SentList anterior del que proviene el carryover
    $table->foreignId('carryover_from_sent_list_id')
          ->nullable()
          ->after('is_carryover')
          ->constrained('sent_lists')
          ->nullOnDelete();
    
    // Cantidad de piezas pendientes al momento del carryover (snapshot)
    $table->integer('pending_quantity_at_carryover')
          ->nullable()
          ->after('carryover_from_sent_list_id')
          ->comment('Snapshot de piezas pendientes cuando se creó el carryover');
    
    // Índice para consultas de carryover
    $table->index('is_carryover');
    $table->index('carryover_from_sent_list_id');
});
```

**Justificación de diseño:**
- `is_carryover` permite filtrar fácilmente en reportes y UI para distinguir planificaciones originales de continuaciones.
- `carryover_from_sent_list_id` provee trazabilidad completa hacia atrás (¿de qué semana proviene este carryover?).
- `pending_quantity_at_carryover` captura un snapshot de las piezas pendientes en el momento exacto del carryover, lo cual es útil para auditoría aunque el WO siga acumulando piezas.
- **No se modifica** `purchase_orders`, `work_orders`, ni `sent_lists` — se mantiene la integridad de las tablas principales.

### 4.2 Migración opcional: Índice de performance en `work_orders`

Para optimizar la consulta de WOs incompletos, agregar un índice compuesto:

```php
Schema::table('work_orders', function (Blueprint $table) {
    // Facilita encontrar WOs abiertos con piezas pendientes
    $table->index(['purchase_order_id', 'sent_pieces'], 'idx_wo_po_sent_pieces');
});
```

### 4.3 Resumen de cambios en BD

| Tabla | Tipo de cambio | Campos agregados | Riesgo de migración |
|---|---|---|---|
| `sent_list_purchase_orders` | ALTER (add columns) | `is_carryover`, `carryover_from_sent_list_id`, `pending_quantity_at_carryover` | Bajo — columnas nullable con default |
| `work_orders` | ALTER (add index) | Índice compuesto | Mínimo |
| `purchase_orders` | Ninguno | — | — |
| `sent_lists` | Ninguno | — | — |
| `lots` | Ninguno | — | — |

---

## 5. Cambios en Modelos

### 5.1 `PurchaseOrder` — agregar scopes de carryover

**Archivo:** `app/Models/PurchaseOrder.php`

Agregar dos scopes:

```php
/**
 * Scope: POs elegibles para nueva planificación (nunca planificados O con carryover pendiente).
 */
public function scopeElegibleForPlanning(Builder $query): Builder
{
    return $query->where('status', self::STATUS_APPROVED)
        ->where(function ($q) {
            // Opción 1: PO nunca fue planificado
            $q->whereDoesntHave('sentLists')
              // Opción 2: PO tiene historial pero su WO no está completo
              ->orWhere(function ($q2) {
                  $q2->whereHas('sentLists')
                     ->whereHas('workOrder', function ($woQ) {
                         // WO con piezas pendientes: sent_pieces < quantity del PO
                         $woQ->whereColumn(
                             'work_orders.sent_pieces',
                             '<',
                             'purchase_orders.quantity'
                         );
                     });
              });
        });
}

/**
 * Scope: POs específicamente en carryover (tiene historial y piezas pendientes).
 */
public function scopeWithCarryover(Builder $query): Builder
{
    return $query->where('status', self::STATUS_APPROVED)
        ->whereHas('sentLists')
        ->whereHas('workOrder', function ($woQ) {
            $woQ->whereColumn(
                'work_orders.sent_pieces',
                '<',
                'purchase_orders.quantity'
            );
        });
}
```

Agregar también el método auxiliar:

```php
/**
 * Retorna las piezas pendientes según el WO asociado.
 * Retorna la cantidad total del PO si no existe WO aún.
 */
public function getPendingQuantityAttribute(): int
{
    if ($this->workOrder) {
        return $this->workOrder->pending_quantity;
    }
    return $this->quantity;
}

/**
 * Indica si este PO tiene un carryover activo (piezas pendientes en WO).
 */
public function hasActiveCarryover(): bool
{
    return $this->workOrder !== null
        && !$this->workOrder->isComplete()
        && $this->workOrder->sent_pieces > 0;
}
```

### 5.2 `WorkOrder` — sin cambios estructurales necesarios

El modelo ya tiene todos los campos necesarios:
- `sent_pieces` — piezas acumuladas (actualizado automáticamente por `updateSentPieces()`)
- `pending_quantity` — accessor que calcula `max(0, quantity - sent_pieces)`
- `isComplete()` — método booleano

No se requieren cambios en `WorkOrder.php`.

### 5.3 `SentList` — agregar relación hacia carryovers

**Archivo:** `app/Models/SentList.php`

Actualizar el `withPivot` en `purchaseOrders()` para incluir los nuevos campos:

```php
public function purchaseOrders(): BelongsToMany
{
    return $this->belongsToMany(PurchaseOrder::class, 'sent_list_purchase_orders')
        ->withPivot([
            'quantity',
            'required_hours',
            'lot_number',
            'is_carryover',                    // NUEVO
            'carryover_from_sent_list_id',     // NUEVO
            'pending_quantity_at_carryover',   // NUEVO
        ])
        ->withTimestamps();
}

/**
 * Retorna el SentList del que proviene este carryover (si aplica).
 * NUEVO método.
 */
public function carryoverSourceSentList(): BelongsTo
{
    // Para acceso desde el pivot: no es una relación directa en SentList,
    // sino que se accede desde el pivot de purchaseOrders.
    // Este método es un helper para uso en la vista/servicio.
    return $this->belongsTo(SentList::class, 'carryover_from_sent_list_id');
}

/**
 * Retorna los POs marcados como carryover en esta SentList.
 * NUEVO scope.
 */
public function carryoverPurchaseOrders(): BelongsToMany
{
    return $this->purchaseOrders()->wherePivot('is_carryover', true);
}
```

---

## 6. Cambios en Lógica de Negocio / Servicios

### 6.1 Nuevo servicio: `CarryoverService`

**Archivo:** `app/Services/CarryoverService.php` (NUEVO)

Este servicio encapsula la lógica de carryover y mantiene el `CapacityCalculatorService` sin cambios (respeta el principio de responsabilidad única).

```php
<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SentList;
use Illuminate\Support\Collection;

class CarryoverService
{
    /**
     * Retorna todos los POs elegibles para carryover:
     * - Aprobados
     * - Con WO incompleto (sent_pieces < quantity)
     * - Que hayan tenido al menos una SentList anterior
     * - Que NO estén ya en una SentList pending activa
     *
     * @param array $excludePoIds  PO IDs ya agregados al Wizard actual
     */
    public function getCarryoverCandidates(array $excludePoIds = []): Collection
    {
        return PurchaseOrder::with(['workOrder', 'part', 'sentLists'])
            ->withCarryover()
            // No incluir POs que ya están en la lista actual del wizard
            ->whereNotIn('id', $excludePoIds)
            // No incluir POs que ya están en una SentList pending activa
            ->whereDoesntHave('sentLists', function ($q) {
                $q->where('status', SentList::STATUS_PENDING);
            })
            ->get();
    }

    /**
     * Retorna la cantidad pendiente para un PO específico.
     * Si el WO tiene sent_pieces, resta. De lo contrario, usa la cantidad del PO.
     */
    public function getPendingQuantityForPO(PurchaseOrder $po): int
    {
        return $po->workOrder
            ? $po->workOrder->pending_quantity
            : $po->quantity;
    }

    /**
     * Determina el SentList más reciente del que proviene el PO
     * (para almacenar en carryover_from_sent_list_id).
     */
    public function getLastSentListForPO(PurchaseOrder $po): ?SentList
    {
        return $po->sentLists()
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Construye el array de datos de pivot para el carryover al adjuntar
     * un PO a una nueva SentList.
     */
    public function buildCarryoverPivotData(
        PurchaseOrder $po,
        int $quantity,
        float $requiredHours,
        ?string $lotNumber = null
    ): array {
        $lastSentList = $this->getLastSentListForPO($po);

        return [
            'quantity'                     => $quantity,
            'required_hours'               => $requiredHours,
            'lot_number'                   => $lotNumber,
            'is_carryover'                 => true,
            'carryover_from_sent_list_id'  => $lastSentList?->id,
            'pending_quantity_at_carryover' => $po->workOrder?->pending_quantity ?? $po->quantity,
        ];
    }

    /**
     * Construye el array de datos de pivot estándar (no carryover).
     */
    public function buildStandardPivotData(
        int $quantity,
        float $requiredHours,
        ?string $lotNumber = null
    ): array {
        return [
            'quantity'                     => $quantity,
            'required_hours'               => $requiredHours,
            'lot_number'                   => $lotNumber,
            'is_carryover'                 => false,
            'carryover_from_sent_list_id'  => null,
            'pending_quantity_at_carryover' => null,
        ];
    }
}
```

### 6.2 Modificaciones en `CapacityCalculatorService`

**Archivo:** `app/Services/CapacityCalculatorService.php`

No se requieren cambios en los métodos de cálculo (`calculateTotalAvailableHours`, `calculateRequiredHours`, `validateCapacity`). El servicio opera sobre cantidades arbitrarias, independientemente de si son originales o de carryover.

### 6.3 Regla de negocio clave

La cantidad usada en el Wizard para un PO de carryover debe ser `workOrder.pending_quantity`, no `purchaseOrder.quantity`. Esto es importante porque:

- `purchaseOrder.quantity` = 1000 piezas (pedido original)
- `workOrder.sent_pieces` = 600 (enviadas en semana anterior)
- `workOrder.pending_quantity` = 400 (lo que se planifica en semana N+1)

Si se usara `purchaseOrder.quantity = 1000` al calcular horas, se sobrestimaría la capacidad necesaria para el carryover.

---

## 7. Cambios en Componentes Livewire / UI

### 7.1 `CapacityWizard` — cambios principales

**Archivo:** `app/Livewire/Admin/CapacityWizard.php`

#### 7.1.1 Modificar `getAvailablePOsProperty()`

Reemplazar la cláusula `whereDoesntHave('sentLists')` por el nuevo scope:

```php
// ANTES (bloquea carryover):
->whereDoesntHave('sentLists')
->whereDoesntHave('workOrder', function($q) {
    $q->whereNotNull('sent_list_id');
})

// DESPUÉS (permite carryover):
->where(function ($q) {
    // PO nunca planificado
    $q->whereDoesntHave('sentLists')
      // O PO con carryover (WO incompleto y no en pending activa)
      ->orWhere(function ($q2) {
          $q2->whereHas('sentLists')
             ->whereHas('workOrder', function ($woQ) {
                 $woQ->whereRaw('work_orders.sent_pieces < purchase_orders.quantity');
             })
             ->whereDoesntHave('sentLists', function ($slQ) {
                 $slQ->where('status', \App\Models\SentList::STATUS_PENDING);
             });
      });
})
```

#### 7.1.2 Marcar visualmente los POs en carryover en el modal de POs

Agregar en el array de datos del item al momento de agregarlo al `workOrderItems`:

```php
// Determinar si es carryover
$isCarryover = $po->hasActiveCarryover();
$pendingQty = $isCarryover
    ? $po->workOrder->pending_quantity
    : $po->quantity;

$this->workOrderItems[] = [
    // ... campos existentes ...
    'quantity'       => $pendingQty,         // Usar piezas pendientes, no originales
    'is_carryover'   => $isCarryover,        // NUEVO — para UI
    'original_qty'   => $po->quantity,       // NUEVO — para mostrar en tooltip
    'sent_pieces'    => $po->workOrder?->sent_pieces ?? 0,  // NUEVO
    // ...
];
```

#### 7.1.3 Modificar `generateSentList()` para usar `CarryoverService`

Al hacer el `$sentList->purchaseOrders()->attach(...)`, usar el servicio para construir el pivot correcto:

```php
use App\Services\CarryoverService;

// En el método generateSentList():
$carryoverService = app(CarryoverService::class);
$purchaseOrder = PurchaseOrder::with('workOrder')->find($item['po_id']);

if ($item['is_carryover'] ?? false) {
    $pivotData = $carryoverService->buildCarryoverPivotData(
        $purchaseOrder,
        $item['quantity'],
        $item['required_hours'],
        $lotNumbersString
    );
} else {
    $pivotData = $carryoverService->buildStandardPivotData(
        $item['quantity'],
        $item['required_hours'],
        $lotNumbersString
    );
}

$sentList->purchaseOrders()->attach($item['po_id'], $pivotData);
```

### 7.2 Vista del modal de POs — indicador visual de carryover

**Archivo:** `resources/views/livewire/admin/capacity-wizard.blade.php`

En el modal de selección de POs, agregar un badge para distinguir POs en carryover:

```blade
{{-- Junto al po_number en la tabla del modal --}}
@if($po->hasActiveCarryover())
    <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-medium">
        Carryover ({{ $po->workOrder->pending_quantity }} pz pendientes)
    </span>
@endif
```

En el Step 2 de la lista de items, mostrar para los POs en carryover:

```blade
@if($item['is_carryover'] ?? false)
    <div class="text-xs text-orange-600 mt-1">
        Carryover: {{ $item['sent_pieces'] }} de {{ $item['original_qty'] }} piezas ya enviadas.
        Planificando {{ $item['quantity'] }} piezas restantes.
    </div>
@endif
```

### 7.3 Vista del SentList Show — historial de carryover

**Archivo:** `resources/views/livewire/admin/sent-lists/` (vista show, si existe)

Agregar en la tabla de POs la columna `Origen`:

```blade
@if($po->pivot->is_carryover)
    <td>
        <span class="text-orange-600 text-xs">
            Carryover desde SentList #{{ $po->pivot->carryover_from_sent_list_id }}
            ({{ $po->pivot->pending_quantity_at_carryover }} pz pendientes al inicio)
        </span>
    </td>
@else
    <td><span class="text-gray-400 text-xs">Original</span></td>
@endif
```

### 7.4 Nuevo Livewire component: `CarryoverDashboard` (opcional — Fase 2)

Un componente dedicado que muestre todos los WOs con piezas pendientes agrupados por semana de producción. Este componente es una mejora futura y no es bloqueante para la funcionalidad principal.

---

## 8. Riesgos Identificados

### 8.1 Riesgos técnicos

- **Unique constraint en pivot:** La tabla `sent_list_purchase_orders` tiene `unique(['sent_list_id', 'purchase_order_id'])`. Si por error se intenta agregar el mismo PO dos veces a la misma SentList (un carryover y una entrada manual simultánea), la BD lanzará un error de unicidad. El wizard debe filtrar POs ya presentes en `$this->workOrderItems`.

- **Condición de carrera:** Si dos usuarios abren el Wizard al mismo tiempo y ambos agregan el mismo PO en carryover, el segundo `attach` fallará por el constraint. Esto requiere manejo de excepción de `QueryException` en `generateSentList()` con un mensaje de usuario amigable.

- **Consulta `whereRaw` en scope:** El scope `scopeWithCarryover` usa `whereRaw('work_orders.sent_pieces < purchase_orders.quantity')` que requiere que `work_orders` esté en la consulta (via `whereHas` o `join`). Si se usa en un contexto sin esa relación, la consulta fallará. Se debe usar exclusivamente a través del `whereHas` o con eager loading.

- **Sent pieces desactualizadas:** Si `WorkOrder.updateSentPieces()` no fue llamado correctamente en algún flujo de cierre de lotes, `sent_pieces` puede estar desactualizado. Esto haría que el `pending_quantity` fuera incorrecto. Se recomienda agregar un comando Artisan de reconciliación: `php artisan wo:recalculate-sent-pieces`.

- **WO con status no "Open":** Un WO podría tener status diferente a "Open" pero aún tener piezas pendientes (ej: en inspección, en espera). El filtro del Wizard actualmente valida `workOrder.status.name = 'Open'`. Para carryover se debe revisar si ese filtro aplica correctamente a WOs que ya tienen lotes completados parcialmente.

### 8.2 Riesgos de datos

- **POs de importación masiva:** El proyecto tiene migraciones de importación Excel (ver `Diagramas_flujo/DB/00_importacion_masiva_excel_2026-03-25.md`). Si hay POs importados sin WO creado, `workOrder` retornará `null` y el carryover no aplica. El código debe manejar `$po->workOrder === null` graciosamente.

- **Datos históricos:** Los POs anteriores al carryover feature tendrán `is_carryover = false` en todos sus pivot records (valor default). No se requiere backfill pero sí documentarlo.

- **Cantidad en pivot vs. WO:** El campo `quantity` en `sent_list_purchase_orders` representa la cantidad planificada para esa semana. En un carryover, será `pending_quantity`, que puede diferir de `purchaseOrder.quantity`. Cualquier reporte que sume `pivot.quantity` sin distinción de `is_carryover` puede sobrecontar. **Los reportes existentes deben ser revisados.**

### 8.3 Riesgos de negocio

- **Sobreplanificación:** Si un operador agrega el mismo PO en carryover a dos SentLists distintas de la misma semana (una via carryover automático y otra manual), se planificaría el doble de piezas. El filtro `whereDoesntHave('sentLists', fn($q) => $q->where('status', 'pending'))` protege contra esto, pero debe validarse.

- **Lotes de carryover y numeración:** En el Wizard se asignan números de lote. Para carryover, los lotes nuevos deben tener números continuación del WO existente (ej: si el WO ya tiene lotes 001-003, el carryover debe generar desde 004). Esto ya está manejado por `Lot::generateNextLotNumber($workOrderId)` que incluye soft-deleted.

- **Fecha de envío:** El carryover actualiza `WorkOrder.scheduled_send_date`. El WO ya tiene una fecha de la semana anterior. Si el Wizard sobrescribe ese campo, se pierde la fecha histórica. Se puede agregar un campo `original_scheduled_send_date` o simplemente registrar el cambio en `WOStatusLog`.

---

## 9. Impactos en el Proyecto

### 9.1 Módulos directamente afectados

| Módulo | Impacto | Tipo |
|---|---|---|
| `CapacityWizard` (Livewire) | Modificación del filtro de POs disponibles y lógica de agregado | Alto |
| `SentList` (Model) | Actualizar `withPivot()` con nuevos campos | Bajo |
| `PurchaseOrder` (Model) | Nuevos scopes y accessor | Bajo |
| `CarryoverService` | Archivo completamente nuevo | Nuevo |
| `sent_list_purchase_orders` (migración) | Nuevas columnas | Bajo |
| `CapacityWizard` (Vista Blade) | Indicadores visuales de carryover | Medio |
| `SentList Show` (Vista) | Columna de origen en tabla de POs | Bajo |

### 9.2 Módulos con posible regresión (requieren verificación)

- **Reportes (`ReportDashboard`, `PartsReport`):** Cualquier reporte que sume cantidades de `sent_list_purchase_orders.quantity` debe considerar si quiere incluir o excluir carryovers para no doble-contar. **Alta prioridad de verificación.**

- **Invoice y PackingSlip:** El flujo de Packing Slip no usa `sent_list_purchase_orders` directamente — opera sobre `Lot` y `PackingSlipItem`. Sin impacto directo. Solo verificar que el cierre de PS/Invoice sigue actualizando `WorkOrder.sent_pieces` correctamente vía `Lot::updateSentPieces()`.

- **Flujo de departamentos en SentList:** El flujo `Materiales → Inspección → Producción → Calidad → Empaque` no cambia. Una SentList de carryover es idéntica a una SentList normal en cuanto a flujo departamental.

- **SentListDepartmentView, SentListMaterialsView, etc.:** Estos componentes muestran la SentList activa. No hay impacto directo, pero agregar un indicador visual de "Esta SentList incluye carryovers" puede mejorar la UX.

- **Filtro de POs en POList:** El componente `POList` muestra todos los POs. No hay impacto.

- **WOList:** La lista de WOs no cambia. El campo `sent_pieces` se sigue actualizando igual.

### 9.3 Consideración de integridad de datos

La relación fundamental `PurchaseOrder (1) → WorkOrder (1)` permanece intacta. El carryover no rompe ninguna FK ni constraint existente. La única tabla modificada a nivel de schema es `sent_list_purchase_orders` con columnas nullable (retrocompatible).

---

## 10. Archivos y Componentes a Modificar

### Archivos nuevos

| Archivo | Descripción |
|---|---|
| `app/Services/CarryoverService.php` | Servicio con lógica de carryover |
| `database/migrations/YYYY_MM_DD_add_carryover_to_sent_list_purchase_orders.php` | Migración de nuevas columnas |

### Archivos modificados

| Archivo | Tipo de cambio | Complejidad |
|---|---|---|
| `app/Models/PurchaseOrder.php` | Agregar scopes `scopeElegibleForPlanning`, `scopeWithCarryover`, métodos `getPendingQuantityAttribute`, `hasActiveCarryover` | Baja |
| `app/Models/SentList.php` | Actualizar `withPivot()` en `purchaseOrders()`, agregar `carryoverPurchaseOrders()` | Baja |
| `app/Livewire/Admin/CapacityWizard.php` | Modificar `getAvailablePOsProperty()`, `addSelectedPOs()`, `generateSentList()` | Media |
| `resources/views/livewire/admin/capacity-wizard.blade.php` | Badges de carryover en modal PO y Step 2 | Baja |
| Vista(s) de SentList Show | Agregar columna de origen carryover | Baja |

### Archivos que requieren verificación (sin cambios necesarios)

| Archivo | Razón de verificación |
|---|---|
| `app/Livewire/Admin/Reports/ReportDashboard.php` | Verificar que sum de `quantity` en pivot no dobla-cuenta |
| `app/Livewire/Admin/Reports/PartsReport.php` | Ídem |
| `app/Livewire/Admin/SentLists/SentListMaterialsView.php` | Verificar que muestra correctamente la cantidad planificada (pendiente) |
| `app/Livewire/Admin/SentLists/SentListProductionView.php` | Ídem |
| `app/Services/CapacityCalculatorService.php` | Sin cambios — verificar que `createSentList()` no está siendo llamado directamente con datos de PO (usa el Wizard) |

---

## 11. Estimación de Tiempo

| Tarea | Descripción | Horas estimadas | Complejidad |
|---|---|---|---|
| **T1** | Crear migración `add_carryover_to_sent_list_purchase_orders` y ejecutar | 1h | Baja |
| **T2** | Crear `CarryoverService` con métodos `getCarryoverCandidates`, `buildCarryoverPivotData`, `buildStandardPivotData`, `getPendingQuantityForPO` | 3h | Media |
| **T3** | Modificar `PurchaseOrder.php`: scopes `scopeElegibleForPlanning`, `scopeWithCarryover`, accessors `pending_quantity`, `hasActiveCarryover` | 2h | Baja |
| **T4** | Modificar `SentList.php`: actualizar `withPivot()`, agregar `carryoverPurchaseOrders()` | 1h | Baja |
| **T5** | Modificar `CapacityWizard.php`: filtro de POs disponibles, lógica de cantidad pendiente, integración con `CarryoverService` en `generateSentList()` | 4h | Alta |
| **T6** | Actualizar vista `capacity-wizard.blade.php`: badges, tooltips y texto de carryover en Step 2 y modal | 2h | Baja |
| **T7** | Actualizar vista SentList Show: columna de origen (original vs. carryover) | 1h | Baja |
| **T8** | Pruebas manuales end-to-end: crear PO, enviar parcialmente, verificar carryover en siguiente semana | 3h | Media |
| **T9** | Verificación de reportes y vistas de SentList departamental para detectar regresiones | 2h | Media |
| **T10** | Comando Artisan `wo:recalculate-sent-pieces` para reconciliación de datos históricos (recomendado) | 2h | Media |
| **T11** | Documentación y tests unitarios para `CarryoverService` | 3h | Media |
| **TOTAL** | | **24 horas** | |

**Estimación realista con margen de contingencia (20%):** **28-30 horas**

**Distribución sugerida:**
- Día 1 (6h): T1 + T2 + T3 + T4 (base de datos y modelos)
- Día 2 (6h): T5 + T6 + T7 (Wizard y vistas)
- Día 3 (4h): T8 + T9 (pruebas y verificación de regresiones)
- Día 4 (2h): T10 + T11 (herramientas de mantenimiento y documentación)

---

## 12. Viabilidad General

### Calificación: ALTA — Completamente viable

**Justificación técnica:**

1. **La arquitectura ya soporta el carryover.** La relación many-to-many entre `SentList` y `PurchaseOrder` vía `sent_list_purchase_orders` fue diseñada precisamente para que un PO pueda aparecer en múltiples SentLists. La única barrera era el filtro en `CapacityWizard::getAvailablePOsProperty()` que excluía POs con SentList previa.

2. **No se rompe ningún dato existente.** Los cambios en BD son exclusivamente columnas nullable con valores por defecto. Los datos históricos quedan intactos.

3. **El flujo de lotes y WOs no cambia.** `WorkOrder.updateSentPieces()` ya funciona correctamente. Los lotes nuevos del carryover se crean con `Lot::generateNextLotNumber()` que numera secuencialmente dentro del mismo WO.

4. **El diagrama de flujo del sistema ya lo contempla.** El nodo `P[BackOrder] --> E` en `Flexcon_Tracker_ERP.md` demuestra que esta funcionalidad fue intencionada desde el diseño original.

5. **Riesgos controlables.** Los riesgos principales (condición de carrera, doble-conteo en reportes) tienen mitigaciones definidas y no son bloqueantes.

**El único aspecto a cuidar** es la revisión de reportes para asegurarse de que no se doble-cuenten cantidades cuando un PO aparece en múltiples SentLists. Esto requiere que los reportes distingan entre la `quantity` original del PO y la suma de `sent_list_purchase_orders.quantity` por semana.

### Dependencias previas necesarias

- La tabla `sent_list_purchase_orders` debe existir en producción (confirmar que la migración `2026_01_20_061024_create_sent_list_purchase_orders_table.php` fue ejecutada).
- Los `WorkOrder.sent_pieces` deben estar correctamente actualizados para todos los WOs con lotes completados. Si hay inconsistencias, ejecutar la reconciliación (T10) antes del despliegue.

---

*Fin del documento*

**Archivos de referencia consultados para este análisis:**
- `app/Models/PurchaseOrder.php`
- `app/Models/WorkOrder.php`
- `app/Models/SentList.php`
- `app/Models/Lot.php`
- `app/Models/PackingSlip.php`
- `app/Models/StatusWO.php`
- `app/Livewire/Admin/CapacityWizard.php`
- `app/Livewire/Admin/Shipping/ShippingQueue.php`
- `app/Livewire/Admin/WorkOrders/WOList.php`
- `app/Livewire/CapacityCalculator.php`
- `app/Services/CapacityCalculatorService.php`
- `database/migrations/2025_12_10_080000_create_purchase_orders_table.php`
- `database/migrations/2025_12_10_090000_create_work_orders_table.php`
- `database/migrations/2025_12_26_024833_create_sent_lists_table.php`
- `database/migrations/2025_12_28_202009_create_lots_table.php`
- `database/migrations/2026_01_20_061024_create_sent_list_purchase_orders_table.php`
- `Diagramas_flujo/Estructura/Flexcon_Tracker_ERP.md`
- `Diagramas_flujo/Estructura/specs/09_production_capacity_calculator_implementation_analysis.md`
