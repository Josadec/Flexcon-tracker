# 06 — Plan de implementación: Capacity Wizard / Step 3 (CRIMP)

> **Dueño:** Josadec · **Módulo:** Capacidad — `CapacityWizard` (M3).
> **Objetivo:** migrar la captura y persistencia de **Kits** → **Lotes de CRIMP** (`crimp_lots`),
> manteniendo intacto el flujo **NO-CRIMP**.
> **Base (verificada en código):** migración `crimp_lots` **ya corrida** (batch 9), modelo `CrimpLot`,
> relación `Lot::crimpLots()` y helper `Lot::isViajero()` **ya existen**. Decisión **B.1 = Opción A**
> (cada lote de CRIMP cuelga de su viajero vía `crimp_lots.lot_id`).
> **Fecha:** 2026-06-13.

---

## 0. Resumen del cambio

Hoy el Step 3 del wizard captura **Kits** por PO crimp (`kitNumbers`) y, al generar la lista, crea
registros `Kit` asociados a **todos** los lotes de la WO. La nueva forma:

1. Renombrar la captura "Kits" → **"Lotes de CRIMP"** (solo `is_crimp`).
2. Añadir el campo nuevo **`lote_fabricante`** (texto manual) por cada lote de CRIMP.
3. Persistir cada lote de CRIMP colgando de **su viajero** (`CrimpLot::create(['lot_id' => $viajero->id, ...])`),
   **no** crear `Kit`.
4. NO tocar la rama NO-CRIMP ni la creación de `Lot` (que es común a ambos flujos).

> **Candado de producción (heredado del análisis):** el gate `Lot::canBeInspected()` (línea 501) aún exige
> un `Kit` con estado `released` para crimp. Si Capacidad deja de crear Kits **antes** de que Mauricio
> actualice ese gate (M7), los viajeros crimp se varan en inspección. → Este plan se **desarrolla y prueba
> en dev** ya; el merge a producción se sincroniza con M7. Ver §7.

---

## 1. Estado actual del código (evidencia)

**Componente:** `app/Livewire/Admin/CapacityWizard.php`

| Elemento | Línea | Qué es |
| --- | --- | --- |
| Propiedades `kitNumbers`, `showKitModal`, `currentKitIndex`, `tempKits`, `kitModalError` | 66-70 | Estado de la captura de kits |
| `is_crimp` en el item preliminar | 422 | `(bool) ($po->part->is_crimp ?? false)` — discriminador, **se conserva** |
| `openKitModal()` / `closeKitModal()` | 621-647 | Abre/cierra modal de kits |
| `addKitInput()` / `removeKitInput()` | 649-662 | Filas dinámicas del modal |
| `saveKits()` | 664-691 | Valida (CAP-1, cantidad > 0) y guarda en `kitNumbers[$index]` |
| `validateLotKitQuantities()` | 697-736 | Validación final de lotes **y** kits |
| `generateSentList()` | 738-923 | Genera lista; crea `Lot` (834-864) y **`Kit`** (866-905) |
| `reset([... 'kitNumbers', 'showKitModal', 'currentKitIndex', 'tempKits' ...])` | 945-948 | Reset del wizard |

**Vista:** `resources/views/livewire/admin/capacity-wizard/step3.blade.php`

| Elemento | Línea | Qué es |
| --- | --- | --- |
| Encabezado columna "Kits" | 133 | `<th>Kits</th>` |
| Celda de kits (solo crimp) + botón `openKitModal` | 191-222 | Chips de kits / "Sin kits" |
| Modal "Gestionar Kits" | 384-490 | Inputs `tempKits.*.number` / `tempKits.*.quantity` |

**Estructura de datos actual:** `kitNumbers[$index] = [ ['number' => 'K-1', 'quantity' => 100], ... ]`,
indexado por **item** (PO/WO), **no** por viajero. Los kits se asocian a todos los lotes del WO
(`syncWithoutDetaching($createdLotIds)`, línea 900).

---

## 2. Modelo de datos destino (Opción A)

```
Item del wizard (index)
  └── Viajero = Lot (lotNumbers[$index])            ← ya existe, no cambia
        └── Lote de CRIMP = CrimpLot (NUEVO)        ← cuelga de lot_id
              { crimp_lot_number, lote_fabricante, quantity, comments }
```

**Firma real de `crimp_lots` (verificada en BD):**
`id`, `lot_id` (FK→lots, cascade), `crimp_lot_number` (idx), `lote_fabricante` (nullable),
`quantity` (unsignedInteger nullable), `comments` (text nullable), timestamps, `deleted_at`.

`CrimpLot::$fillable = [lot_id, crimp_lot_number, lote_fabricante, quantity, comments]`.

### 2.1 Decisión de estructura de estado (a confirmar)

Cada lote de CRIMP debe colgar de **un** viajero. Como `crimpLots` se captura por item, hay que **referenciar
a qué viajero pertenece**. Propuesta (mínimo cambio, reutiliza el patrón del modal por item):

```php
// Nueva propiedad, reemplaza a kitNumbers
public array $crimpLots = [];
// crimpLots[$index] = [
//   ['lot_ref' => 'L-001', 'number' => 'CL-1', 'lote_fabricante' => 'F-22', 'quantity' => 100, 'comments' => ''],
//   ...
// ]
```

- `lot_ref` = el `number` del viajero (lote) al que pertenece, dentro del mismo item.
- **Default 1 viajero:** si el item tiene un solo lote, `lot_ref` se pre-rellena con ese lote y el selector
  de viajero queda oculto (caso simple = simple). Solo si hay >1 viajero se muestra el selector.

> Alternativa más "pura": anidar `crimp_lots` dentro de cada fila de `lotNumbers`
> (`lotNumbers[$index][$i]['crimp_lots'] = [...]`). Persistencia trivial, pero obliga a rediseñar el modal
> de lotes. **Se recomienda la propuesta con `lot_ref`** por menor superficie de cambio. Confirmar con Josadec.

---

## 3. Cambios en el componente `CapacityWizard.php`

### 3.1 Propiedades (líneas 66-70)
- Renombrar/reemplazar `kitNumbers` → `crimpLots`; `tempKits` → `tempCrimpLots`;
  `currentKitIndex` → `currentCrimpIndex`; `showKitModal` → `showCrimpModal`; `kitModalError` → `crimpModalError`.
- Mantener `is_crimp` (422) **sin cambios**.

### 3.2 Métodos del modal (renombrar 621-691)
- `openCrimpModal(int $index)` — carga `tempCrimpLots` desde `crimpLots[$index]`; si vacío, fila inicial
  `['lot_ref' => <lote único o ''>, 'number' => '', 'lote_fabricante' => '', 'quantity' => '', 'comments' => '']`.
  Cargar los lotes disponibles del item (`lotNumbers[$index]`) para poblar el selector `lot_ref`.
- `addCrimpInput()` / `removeCrimpInput()` — análogos a `addKitInput`/`removeKitInput`.
- `saveCrimpLots()` — filtra filas con `number` no vacío; **valida**: `quantity >= 1` y, si la regla de negocio
  lo exige, `lote_fabricante` requerido (ver §6). Guarda en `crimpLots[$currentCrimpIndex]`.

### 3.3 Validación `validateLotKitQuantities()` (697-736)
- Renombrar a `validateLotCrimpQuantities()` (o mantener nombre y cambiar el bloque interno).
- Sustituir el bloque "Kits" (723-732) por validación de **lotes de CRIMP**:
  - cada `quantity >= 1`;
  - `lot_ref` debe corresponder a un lote existente del item;
  - **(opcional, confirmar B.1)** la suma de `quantity` de los lotes de CRIMP de un viajero **no debe exceder**
    la cantidad de ese viajero. Hoy NO se valida cuadre kit↔lote; decidir si se añade.

### 3.4 Persistencia en `generateSentList()` (bloque 866-905)

**Paso previo (en el loop de creación de `Lot`, 840-864):** construir un mapa número→id:
```php
$lotIdByNumber = [];               // declarar antes del foreach de lotes
// dentro del foreach, tras resolver $newLot/$existingLot:
$lotIdByNumber[$lotNumber] = $newLot->id ?? $existingLot->id;
```

**Reemplazar el bloque Kit (866-905) por:**
```php
$isCrimp = $item['is_crimp'] ?? false;
if ($isCrimp && $purchaseOrder && $purchaseOrder->workOrder) {
    foreach (($this->crimpLots[$index] ?? []) as $cl) {
        $number = trim($cl['number'] ?? '');
        if ($number === '') {
            continue;
        }
        // Resolver el viajero al que pertenece (Opción A)
        $lotRef = $cl['lot_ref'] ?? array_key_first($lotIdByNumber);
        $lotId  = $lotIdByNumber[$lotRef] ?? null;
        if (!$lotId) {
            continue; // viajero no resuelto: no persistir huérfano
        }
        // Idempotencia: no duplicar por (lot_id, crimp_lot_number)
        $exists = \App\Models\CrimpLot::where('lot_id', $lotId)
            ->where('crimp_lot_number', $number)->exists();
        if ($exists) {
            continue;
        }
        \App\Models\CrimpLot::create([
            'lot_id'           => $lotId,
            'crimp_lot_number' => $number,
            'lote_fabricante'  => trim($cl['lote_fabricante'] ?? '') ?: null,
            'quantity'         => ($cl['quantity'] ?? '') !== '' ? (int) $cl['quantity'] : null,
            'comments'         => trim($cl['comments'] ?? '') ?: null,
        ]);
    }
}
```
- **Eliminar** el `use App\Models\Kit;` si ya no se usa en el archivo (verificar otros usos antes).
- Añadir `use App\Models\CrimpLot;`.
- Todo dentro del `if ($isCrimp ...)`: **NO-CRIMP no entra**.

### 3.5 Reset (945-948)
- Actualizar la lista del `reset()` a las nuevas propiedades (`crimpLots`, `showCrimpModal`,
  `currentCrimpIndex`, `tempCrimpLots`).

---

## 4. Cambios en la vista `step3.blade.php`

| Zona | Línea | Cambio |
| --- | --- | --- |
| Encabezado columna | 133 | `Kits` → `Lotes de CRIMP` |
| Variables `@php` | 142-143 | `$kits = $kitNumbers[...]` → `$crimps = $crimpLots[...]`; recalcular `$crimpCount` |
| Celda crimp (chips) | 191-222 | Mostrar chips de lotes de CRIMP con `number` + `lote_fabricante` + `(quantity)`; botón `openCrimpModal` |
| Modal | 384-490 | Título "Gestionar Lotes de CRIMP"; por fila: **selector de viajero** (`tempCrimpLots.*.lot_ref`, oculto si 1 lote), **No. lote CRIMP** (`*.number`), **Lote de fabricante** (`*.lote_fabricante`), **Cantidad** (`*.quantity`), **Comentarios** (`*.comments`); botones `addCrimpInput` / `removeCrimpInput` / `saveCrimpLots` / `closeCrimpModal` |

- Mantener el `@if ($isCrimp) ... @else N/A @endif` que ya existe (220): la columna sigue siendo solo para crimp.
- Reusar los estilos de chips morados (`bg-purple-*`) ya presentes.

---

## 5. Checklist de archivos a tocar

- [ ] `app/Livewire/Admin/CapacityWizard.php` — props (66-70), modal (621-691), validación (697-736),
      persistencia (834-905), reset (945-948), imports (`CrimpLot`, quitar `Kit` si procede).
- [ ] `resources/views/livewire/admin/capacity-wizard/step3.blade.php` — header (133), celda (191-222),
      modal (384-490).
- [ ] (No nuevas rutas, no nuevas migraciones — la base ya existe.)

---

## 6. Decisiones de negocio a confirmar antes de cerrar persistencia

1. **`lote_fabricante` ¿obligatorio?** La columna es nullable en BD. Si es obligatorio, validarlo en
   `saveCrimpLots()` y marcar `required` en el input. (Decisión B.1 — lote de fabricante.)
2. **`crimp_lot_number` ¿manual o autogenerado?** Hoy los kits son manuales; los lotes (`lot_number`) se
   autogeneran. Confirmar si el No. de lote de CRIMP se captura a mano (propuesta actual) o se genera.
3. **¿Cuadre de cantidades?** ¿La suma de `quantity` de los lotes de CRIMP de un viajero debe ≤ cantidad del
   viajero? Hoy NO se valida kit↔lote. Definir para §3.3.
4. **¿Varios viajeros por item?** El default es 1 viajero. Confirmar que el selector multi-viajero es
   necesario en capacidad o si en este módulo siempre es 1.

---

## 7. Riesgos y orden de trabajo

| # | Riesgo | Mitigación |
| - | ------ | ---------- |
| R1 | **Romper NO-CRIMP** en `generateSentList()` | Todo el cambio vive dentro de `if ($isCrimp)`. La creación de `Lot` (834-864) **no se toca**. Prueba de regresión con una parte `is_crimp = false`. |
| R2 | **Gate de inspección M7** sigue exigiendo `Kit` `released` (`Lot::canBeInspected()` L501) | **Bloqueante de producción.** Desarrollar/probar en dev; **no mergear a prod** "dejar de crear Kit" hasta que Mauricio actualice el gate crimp (`crimpLots()->exists()` o nuevo status). Coordinar el merge conjunto. |
| R3 | **Lotes de CRIMP huérfanos** si `lot_ref` no resuelve a un viajero | El `continue` cuando `!$lotId` evita registros sin viajero; validar `lot_ref` en `validateLotCrimpQuantities()`. |
| R4 | **Datos residuales de Kit** de listas viejas | No se borran (legacy). Solo se deja de crear. |

**Orden sugerido para Josadec:**
1. UI del Step 3 (props + modal + vista) — captura de lotes de CRIMP con `lote_fabricante`. *No bloquea.*
2. Validación `validateLotCrimpQuantities()`.
3. Persistencia en `generateSentList()` (mapa `lotIdByNumber` + `CrimpLot::create`).
4. Prueba en dev: crear lista crimp → verificar filas en `crimp_lots` colgando del `lot_id` correcto;
   verificar que `canBeInspected()` aún devuelve `false` (confirma el vínculo con M7).
5. Regresión NO-CRIMP completa.
6. Coordinar con Mauricio el merge a prod junto al gate M7.

---

## 8. Referencias

- `01_modulos_afectados.md` → **M3 · Capacity Wizard**.
- `02_tablas_modelos_rutas.md` → tabla `crimp_lots`, modelo `CrimpLot`.
- `03_riesgos_y_decisiones.md` → §B.1 (decisiones del viajero / lotes de CRIMP), §A (R1/R2).
- `05_reparto_por_areas_2_devs.md` → §4.1 (Capacidad) y §6 (orden de arranque Josadec).
- Código: `app/Livewire/Admin/CapacityWizard.php`, `resources/views/livewire/admin/capacity-wizard/step3.blade.php`,
  `app/Models/CrimpLot.php`, `app/Models/Lot.php` (`crimpLots()` L163, `isViajero()` L171, `canBeInspected()` L501).
