# Implementación: PO/WO Carryover Semanal

**Fecha:** 2026-05-18  
**Estado:** Implementado y migración ejecutada

---

## Archivos creados

| Archivo | Descripción |
|---|---|
| `database/migrations/2026_05_18_100000_add_carryover_fields_to_sent_list_purchase_orders.php` | Agrega `is_carryover`, `carryover_from_sent_list_id`, `pending_quantity_at_carryover` al pivot |
| `app/Services/CarryoverService.php` | Servicio con lógica de candidatos, cálculo de cantidades y construcción de datos pivot |

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/PurchaseOrder.php` | `scopeWithCarryover`, `getPendingQuantityAttribute`, `hasActiveCarryover()`, `withPivot` actualizado |
| `app/Models/SentList.php` | `withPivot` con nuevas columnas, método `carryoverPurchaseOrders()` |
| `app/Livewire/Admin/CapacityWizard.php` | Filtro de POs actualizado para incluir carryovers, `addSelectedPOs` usa `pending_quantity`, `generateSentList` usa `CarryoverService` para guardar datos pivot correctos |
| `resources/views/livewire/admin/capacity-wizard/step2.blade.php` | Badges naranja "Carryover" en el modal y en la tabla de items con desglose de piezas |
| `resources/views/livewire/admin/sent-lists/sent-list-department-view.blade.php` | Fila destacada en naranja + indicador "Carryover" con cantidad pendiente al inicio |

## Cómo funciona ahora

1. Un PO con envío parcial (WO con `sent_pieces < quantity`) que ya tuvo una SentList completada/cancelada **aparece automáticamente** en el modal del Wizard con su cantidad pendiente pre-calculada y un badge naranja "Carryover"
2. Al agregarlo, el Wizard usa las piezas pendientes para calcular horas (no la cantidad original)
3. Al generar la SentList, el pivot guarda `is_carryover=true`, referencia a la SentList anterior y un snapshot de piezas pendientes
4. En las vistas de departamentos, las filas de carryover se destacan en naranja con el indicador de origen

## Regla clave anti-doble-planificación

Un PO no aparece como carryover si ya está en una SentList con `status = pending` — evita planificar el mismo trabajo dos veces en paralelo.
