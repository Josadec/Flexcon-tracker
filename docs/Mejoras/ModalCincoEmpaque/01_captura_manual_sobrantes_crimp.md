# Análisis Técnico: Captura manual de piezas sobrantes por Lote de CRIMP (Modal Paso 5 · Empaque)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-07 |
| **Autor** | Agent Architect |
| **Rama** | `main_jos` |
| **Estado** | Análisis / Diseño — **NO implementado** |
| **Versión** | 1.0 |
| **Pantalla objetivo** | `/admin/sent-lists/display` (y `/admin/sent-lists/{sentList}/packaging`) |
| **Componente objetivo** | `App\Livewire\Admin\SentLists\ShippingListDisplay` **+** `App\Livewire\Admin\SentLists\SentListPackagingView` |
| **Vista objetivo** | `resources/views/livewire/admin/sent-lists/partials/modal-confirm-empaque.blade.php` |
| **Verificación visual** | **Pendiente** — hoy no existe ningún número de parte con CRIMP en ninguna Lista de Envío. Ver §9.4 |

---

## Tabla de Contenidos

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Requerimiento del Cliente](#2-requerimiento-del-cliente)
3. [Estado Actual Real del Sistema](#3-estado-actual-real-del-sistema)
4. [Modelo de Datos Actual Involucrado](#4-modelo-de-datos-actual-involucrado)
5. [Semántica del "Sobrante" — el punto que decide todo](#5-semántica-del-sobrante--el-punto-que-decide-todo)
6. [Modelo de Datos Propuesto](#6-modelo-de-datos-propuesto)
7. [Impacto en Cascada (crítico)](#7-impacto-en-cascada-crítico)
8. [Diseño Backend](#8-diseño-backend)
9. [Diseño Frontend](#9-diseño-frontend)
10. [Reglas de Validación y Casos Borde](#10-reglas-de-validación-y-casos-borde)
11. [Riesgos y Consideraciones](#11-riesgos-y-consideraciones)
12. [Plan de Implementación por Fases](#12-plan-de-implementación-por-fases)
13. [Plan de Pruebas](#13-plan-de-pruebas)
14. [Preguntas Abiertas para el Cliente](#14-preguntas-abiertas-para-el-cliente)

---

## 1. Resumen Ejecutivo

### 1.1 Hallazgo principal — "sobrante" ya existe, pero es **calculado** y vive en el **nivel equivocado**

El modal del Paso 5 **ya muestra dos sobrantes**, pero **no son capturados: son derivados**, y se calculan **a nivel viajero (`Lot`)**, no a nivel Lote de CRIMP:

```php
// app/Models/Lot.php:1086-1089
public function getPackagedPiecesSurplus(): int
{
    return max(0, $this->getPackagingAvailablePieces() - $this->getPackagedPiecesTotal());
}

// app/Models/Lot.php:1094-1097
public function getPackagedCrimpSurplus(): int
{
    return max(0, $this->getCrimpTargetTotal() - $this->getPackagedCrimpTotal());
}
```

Se consumen en `resources/views/livewire/admin/sent-lists/partials/modal-confirm-empaque.blade.php:23-24`, se pintan como *stats* en el Paso 3 (`:183-186`) y se repiten en el resumen del Paso 4 (`:217-218`).

### 1.2 Segundo hallazgo — la **asimetría de nivel** que el requerimiento expone

Dentro del mismo modal conviven hoy dos granularidades distintas y esto es la causa raíz del requerimiento:

| Dato mostrado | Granularidad real | Origen verificado |
|---|---|---|
| "Total capturado" de manguitas (Paso 2) | **Por Lote de CRIMP** | `modal-confirm-empaque.blade.php:19,21` — filtra `->where('crimp_lot_id', $confirmCrimpLotId)` |
| "Total capturado" de CRIMP (Paso 2) | **Por Lote de CRIMP** | `modal-confirm-empaque.blade.php:20,22` |
| "Piezas sobrantes" (Paso 3) | **Por Viajero completo** | `modal-confirm-empaque.blade.php:23` → `Lot::getPackagedPiecesSurplus()` |
| "CRIMP sobrante" (Paso 3) | **Por Viajero completo** | `modal-confirm-empaque.blade.php:24` → `Lot::getPackagedCrimpSurplus()` |

Es decir: **si un viajero tiene 2 Lotes de CRIMP, el usuario ve totales de un solo lote junto a sobrantes de los dos**. El requerimiento ("por cada Lote de CRIMP… un nuevo input dentro de cada bloque") pide precisamente **bajar el sobrante al nivel del Lote de CRIMP**, donde ya viven los totales.

### 1.3 Tercer hallazgo — el modal es un **partial compartido por dos componentes con código duplicado**

`modal-confirm-empaque.blade.php` es incluido desde **dos** blades:

- `resources/views/livewire/admin/sent-lists/shipping-list-display.blade.php:1933`
- `resources/views/livewire/admin/sent-lists/packaging-view.blade.php:452`

Y **cada componente tiene su propia copia de los métodos y propiedades del Paso 5**:

| Componente | Bloque Paso 5 | Guardia de permiso |
|---|---|---|
| `app/Livewire/Admin/SentLists/ShippingListDisplay.php` | `:1062-1332` (`openConfirmModal` `:1067`, `addConfirmPieceWeighing` `:1101`, `addConfirmCrimpWeighing` `:1129`, `confirmPackaging` `:1238`, `closeConfirmModal` `:1315`) | `guardDepartment('packaging')` (`:62-69`) |
| `app/Livewire/Admin/SentLists/SentListPackagingView.php` | `:875-...` (`openConfirmModal` `:877`, `addConfirmPieceWeighing` `:903`, `addConfirmCrimpWeighing` `:933`) | `ensureCanEditDepartment()` (`:905`, `:935`) |

**Consecuencia de diseño obligatoria:** toda propiedad/método nuevo que el partial consuma **debe añadirse a los DOS componentes**, o el modal reventará (`Property [x] not found`) al abrirse desde la vista de Empaque. Esto duplica el esfuerzo de la Fase 2 y es la fuente más probable de un bug de regresión.

### 1.4 Cuarto hallazgo — riesgo de cascada acotado, **pero sólo si el sobrante NO altera los totales**

El sobrante manual **no toca ninguna de las rutas que causaron BUG-A/BUG-B** (commit `2d3351f`) **siempre y cuando** se persista en un campo propio y **no** se reste de `getPackagedPiecesTotal()`. La cadena crítica verificada es:

```
packaging_piece_weighings.quantity
   └─> Lot::getPackagedPiecesTotal()                     (Lot.php:1062-1065)
         ├─> Lot::getCompletionCycles()  (rama isViajero) (Lot.php:945-947)
         │     └─> Lot::getTotalCompletedPieces()         (Lot.php:960-963)
         │           ├─> columna "Pz Completadas"          (shipping-list-display.blade.php:320)
         │           └─> ShippingQueue::createPackingSlip  (ShippingQueue.php:284 → quantity_packed)
         └─> LotPackagingObserver                          (LotPackagingObserver.php:75-77)
               └─> lots.quantity_packed_final
                     ├─> PackingSlipCreate::save           (PackingSlipCreate.php:104)
                     ├─> PackingSlipShow                   (PackingSlipShow.php:317)
                     └─> packing_slip_items.quantity_packed
                           └─> InvoiceFromPackingSlipService (:158, :182) → FPL-12
```

**Regla de protección (RP-01), no negociable: la captura manual de sobrantes NO debe modificar `getPackagedPiecesTotal()`, `getPackagedCrimpTotal()`, `getCompletionCycles()`, `getTotalCompletedPieces()`, `LotPackagingObserver` ni `quantity_packed_final`.** Detalle en §7.

### 1.5 Recomendación en una línea

Dos columnas nuevas **en `crimp_lots`** (`surplus_pieces`, `surplus_crimps`, `unsignedInteger` **nullable**, default `NULL`, valor único no acumulativo) + auditoría, capturadas con un botón "Guardar" propio por bloque, **puramente declarativas** (no restan de nada) en la versión 1, y **con el `wire:key` del bloque re-atado a `$confirmCrimpLotId`** para no repetir el bug de reutilización de DOM del proyecto.

---

## 2. Requerimiento del Cliente

### 2.1 Texto original

> "Por cada **Lote de CRIMP**, dentro del **modal de confirmación de Empaque (Paso 5)**, **después del total de las pesadas de Piezas «manguitas» y Piezas CRIMP**, permitir guardar en un **nuevo input dentro de cada bloque (manguitas y CRIMP)** la captura **manual de las piezas sobrantes**, ya sea de «manguitas» o de CRIMP."

### 2.2 Lectura literal (lo que el texto pide sin interpretar)

| Aspecto | Lo que dice el texto | Verificable en el código |
|---|---|---|
| Granularidad | **Por cada Lote de CRIMP** | Sí — `$confirmCrimpLotId` ya existe (`ShippingListDisplay.php:162`) |
| Ubicación | **Dentro de cada bloque**, **después del total** | Sí — `modal-confirm-empaque.blade.php:168-171` es el bloque "Total capturado" |
| Cantidad de campos | **2** (uno manguitas, uno CRIMP) | El bloque se genera con un `@foreach` sobre 2 configs (`:78-113`) |
| Naturaleza | **Captura manual**, se **guarda** | Requiere persistencia; hoy el sobrante es calculado en vuelo |
| Alcance | Sólo partes con CRIMP | `Lot::isViajero()` (`Lot.php:184-187`) — el modal ya sólo se abre para CRIMP (`shipping-list-display.blade.php:963`) |

### 2.3 Lo que el texto **NO** dice (y por eso hay §14)

- No dice si el sobrante manual **sustituye** al sobrante calculado que ya se muestra en el Paso 3, o **convive** con él.
- No dice si el sobrante **resta** de lo empacado o es informativo.
- No dice si es **acumulativo** (se suma cada vez) o un **valor único** por Lote de CRIMP.
- No dice si debe respetarse la **regla 1:1** entre manguitas sobrantes y CRIMP sobrantes.
- No dice si debe **bloquearse** una vez confirmado el Paso 5 / cerrado el lote.

---

## 3. Estado Actual Real del Sistema

### 3.1 Localización exacta del modal Paso 5

**Vista (única, compartida):**
`resources/views/livewire/admin/sent-lists/partials/modal-confirm-empaque.blade.php` — 271 líneas.
Cabecera del propio archivo (`:1-10`) documenta el contrato de propiedades que exige del componente:

> `$showConfirmModal, $confirmLotId, $confirmCrimpLotId, $editPieceWId, $editCrimpWId, $editPieceWQty, $editCrimpWQty, $cPieceQty, $cCrimpQty, $confirmDone, $confirmComments, $confirmLabelCount`

**Incluida desde:**

| Archivo:línea | Pantalla |
|---|---|
| `resources/views/livewire/admin/sent-lists/shipping-list-display.blade.php:1933` | Tablero de piso `/admin/sent-lists/display` |
| `resources/views/livewire/admin/sent-lists/packaging-view.blade.php:452` | Vista departamental de Empaque |

**Puntos de entrada (quién abre el modal) — hay DOS en el mismo blade:**

| Archivo:línea | Contexto |
|---|---|
| `resources/views/livewire/admin/sent-lists/shipping-list-display.blade.php:963` | Celda "Empaque" de la tabla principal |
| `resources/views/livewire/admin/sent-lists/shipping-list-display.blade.php:1372` | Vista compacta/móvil (`($part->is_crimp ?? false) ? 'openConfirmModal' : 'openPackagingModal'`) |

```blade
{{-- :963 --}}
wire:click="{{ $part->is_crimp ? 'openConfirmModal' : 'openPackagingModal' }}({{ $lot->id }})"
```

Confirmado: **el modal Paso 5 sólo se abre cuando `parts.is_crimp = true`.** Las partes sin CRIMP van a `openPackagingModal`, un modal distinto. Ambos puntos de entrada llaman al mismo método, así que **no requieren cambios** — pero la verificación visual (F6) debe cubrir los dos.

### 3.2 Estructura interna del modal (4 sub-pasos)

| Sub-paso | Líneas del partial | Contenido |
|---|---|---|
| Contexto | `:30-35` | Descripción, WO+viajero, WO+lote de CRIMP, cantidad en viajero |
| **Paso 1** | `:46-71` | Selector de Lote de CRIMP (`x-ui.choice`, un `wire:click="$set('confirmCrimpLotId', …)"` por lote) |
| **Paso 2** | `:74-175` | **Los dos bloques de pesadas** — generados por `@foreach` sobre array literal de 2 configs (`:78-113`) |
| **Paso 3** | `:178-199` | 4 *stats* (manguitas, CRIMP, CRIMP sobrante, Piezas sobrantes) + botón "Confirmar cantidades" |
| **Paso 4** | `:202-244` | Resumen "Empaque Terminado" + No. de etiquetas + comentarios |

### 3.3 Cómo se listan hoy los Lotes de CRIMP en el modal

`modal-confirm-empaque.blade.php:17-18`:

```php
$cfCrimpLots = $cfLot?->crimpLots ?? collect();
$cfSel   = $cfCrimpLots->firstWhere('id', $confirmCrimpLotId);
```

El modal **no muestra los lotes en paralelo**: muestra **un selector** (Paso 1) y **todo lo demás se re-renderiza contra el lote seleccionado**. Es un patrón *master-detail* dentro del mismo modal. Si el viajero no tiene lotes de CRIMP, el modal muestra sólo una advertencia (`:36-42`).

El lote por defecto es el primero: `$this->confirmCrimpLotId = $lot->crimpLots->first()?->id;` (`ShippingListDisplay.php:1085`, `SentListPackagingView.php:886`).

### 3.4 Cómo se capturan y totalizan las pesadas hoy

**Filtrado y totales (en el Blade, no en el componente):**

```php
// modal-confirm-empaque.blade.php:19-22
$cfPW = $cfLot ? $cfLot->packagingPieceWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
$cfCW = $cfLot ? $cfLot->packagingCrimpWeighings->where('crimp_lot_id', $confirmCrimpLotId) : collect();
$cfPiecesTotal = (int) $cfPW->sum('quantity');
$cfCrimpTotal  = (int) $cfCW->sum('quantity');
```

**Nota técnica:** los totales del Paso 2 se calculan **en memoria sobre la colección eager-loaded** (`:13`), filtrada por `crimp_lot_id`. Son **totales por Lote de CRIMP**. En cambio `Lot::getPackagedPiecesTotal()` (`Lot.php:1062-1065`) hace `->sum('quantity')` **en SQL sobre todo el viajero**. Los dos números coinciden sólo si el viajero tiene un único Lote de CRIMP.

**Alta de pesada** (`ShippingListDisplay.php:1101-1155`): valida `confirmCrimpLotId` obligatorio + `cPieceQty|cCrimpQty >= 1`, crea el registro con `lot_id`, `crimp_lot_id`, `quantity`, `weight = null`, `weighed_at`, `weighed_by`, resetea el input, **pone `confirmDone = false`** y despacha `refresh-display`.

**Edición inline** (`:1174-1236`) y **borrado** (`:1157-1171`): mismo patrón; ambos invalidan `confirmDone`.

**Observación:** ninguna de estas operaciones usa transacción (`DB::transaction`). Son escrituras de una sola fila, así que hoy no hace falta.

### 3.5 Dónde se persisten las pesadas

| Tabla | Migración | Columnas relevantes |
|---|---|---|
| `packaging_piece_weighings` (manguitas) | `database/migrations/2026_06_16_120000_create_packaging_piece_weighings_table.php` | `lot_id`, `quantity` (unsignedInteger), `weight` (nullable), `weighed_by`, `weighed_at`, `comments`, timestamps, softDeletes |
| `packaging_crimp_weighings` (CRIMP) | `database/migrations/2026_06_16_120100_create_packaging_crimp_weighings_table.php` | idénticas |
| `crimp_lot_id` (añadido después) | `2026_06_17_120000_…crimp_weighings.php` y `2026_06_17_130000_…piece_weighings.php` | FK al Lote de CRIMP |

Modelos: `app/Models/PackagingPieceWeighing.php` y `app/Models/PackagingCrimpWeighing.php` (fillable incluye `crimp_lot_id`; `quantity` casteado a `integer`; `SoftDeletes`).

### 3.6 Dónde **NO** se persiste nada de sobrantes hoy

`app/Models/CrimpLot.php` completo son **79 líneas**. Su `$fillable` (`:19-26`) es:

```php
'lot_id', 'crimp_lot_number', 'lote_fabricante', 'date_code', 'quantity', 'comments'
```

**No tiene ningún campo de sobrante, ni de cierre, ni de auditoría de empaque.** Sólo expone `getPackagedPiecesTotal()` (`:67-70`) y `getPackagedCrimpTotal()` (`:75-78`), ambos sumando **en memoria** sobre la relación (`$this->packagingPieceWeighings->sum(...)`, sin `()`), lo que exige eager-loading para no caer en N+1.

---

## 4. Modelo de Datos Actual Involucrado

### 4.1 Jerarquía verificada

```
Part (parts.is_crimp)                          ← 2025_12_10_051116_create_parts_table.php:21
  └─ PurchaseOrder
       └─ WorkOrder
            └─ Lot  ("Viajero" si part.is_crimp)      ← Lot::isViajero() Lot.php:184-187
                 ├─ CrimpLot (N)   "Lote de CRIMP"    ← crimp_lots
                 ├─ PackagingPieceWeighing (N)  lot_id + crimp_lot_id
                 ├─ PackagingCrimpWeighing (N)  lot_id + crimp_lot_id
                 └─ PackagingRecord (N)  ← NUNCA se escribe en el flujo CRIMP
```

> ⚠️ **Dato a vigilar:** `parts.is_crimp` tiene **`->default(true)`** (`database/migrations/2025_12_10_051116_create_parts_table.php:21`). Cualquier parte creada sin especificar el campo nace como CRIMP. El cliente reporta que hoy no hay partes CRIMP en la Lista de Envío, lo que implica que los registros actuales lo tienen puesto explícitamente en `false`. **No verificable sin consultar la BD; se documenta como riesgo, no como hecho.**

### 4.2 Los cuatro números del Paso 3, con su fórmula real

| Stat en pantalla | Fórmula | Archivo:línea | Nivel |
|---|---|---|---|
| Piezas «manguitas» | `SUM(packaging_piece_weighings.quantity WHERE crimp_lot_id = sel)` | `modal-confirm-empaque.blade.php:21` | Lote de CRIMP |
| Piezas CRIMP | `SUM(packaging_crimp_weighings.quantity WHERE crimp_lot_id = sel)` | `:22` | Lote de CRIMP |
| **CRIMP sobrante** | `max(0, getCrimpTargetTotal() − getPackagedCrimpTotal())` | `Lot.php:1094-1097` | **Viajero** |
| **Piezas sobrantes** | `max(0, getPackagingAvailablePieces() − getPackagedPiecesTotal())` | `Lot.php:1086-1089` | **Viajero** |

donde `getCrimpTargetTotal()` = `SUM(crimp_lots.quantity)` del viajero (`Lot.php:1078-1081`) y `getPackagingAvailablePieces()` = `getQualityGoodPieces()` (`Lot.php:992-995`), es decir **piezas aprobadas por Calidad**.

### 4.3 El "otro" sobrante (NO-CRIMP) que ya existe y no debe confundirse

`Lot::getPackagingTotalSurplus()` (`Lot.php:1022-1029`) suma `packaging_records.surplus_pieces` / `adjusted_surplus`. **Para un viajero CRIMP devuelve siempre 0**, porque el flujo CRIMP nunca escribe `packaging_records` (documentado en `docs/handoff-mau-hueco-d2-crimp-shipping.md:92`).

Esto tiene una consecuencia **ya existente y no relacionada con este requerimiento**, pero que el cliente probablemente esté viendo y que puede estar detrás de la petición:

| Consumidor | Archivo:línea | Comportamiento en CRIMP hoy |
|---|---|---|
| Columna "Pz Sobrantes" del tablero | `shipping-list-display.blade.php:312-317` y `:1037` | **Siempre 0** (`if ($l->hasPackagingRecords()) … return 0;`) |
| Botón "Entregar N pz sobrantes" (Paso 8) | `shipping-list-display.blade.php:921`, `:1013-1017` | **Nunca aparece** (`$surplus = getPackagingTotalSurplus()` = 0) |
| `getPostQualityLifecycle()` fase "material" | `Lot.php:1214-1215, 1276-1281` | Rama `$hasSurplus = false` → "Materiales debe confirmar recepción" |

**Conclusión de §4.3:** hoy un viajero CRIMP **nunca dispara el flujo de entrega de sobrantes**, aunque el modal Paso 5 le muestre sobrantes calculados. Si el cliente espera que el sobrante capturado alimente ese botón, eso es **alcance adicional** y debe cotizarse aparte (ver P-05 en §14).

---

## 5. Semántica del "Sobrante" — el punto que decide todo

Este es el nudo del análisis. La misma palabra "sobrante" tiene **tres significados posibles** y cada uno cambia el diseño completo.

### 5.1 Interpretación A — **Sobrante físico devuelto** (material bueno que no se empacó)

> "Sobraron 40 manguitas y 40 CRIMP; están en la mesa y hay que regresarlos a Materiales."

- **Coherente con:** el helper actual `getPackagedPiecesSurplus()` y su texto de ayuda en pantalla — *"Piezas buenas que NO se empacaron. Existen físicamente y Empaque debe entregarlas."* (`modal-confirm-empaque.blade.php:186`) y *"CRIMP del objetivo que aún no se empacaron: quedan disponibles."* (`:184`).
- **Invariante:** `empacado + sobrante ≤ disponible`. El sobrante **no resta** de lo empacado — ya está fuera de lo empacado por construcción.
- **Impacto en cascada:** **NULO** sobre `quantity_packed_final`, Packing Slip e Invoice. Sólo alimenta el Paso 8 (entrega de sobrantes) si se decide conectarlo.
- **Por qué el input manual tendría sentido aun así:** porque el sobrante calculado depende de `getQualityGoodPieces()`, que puede no coincidir con lo que Empaque **realmente tiene en la mano** (mermas de manipulación, piezas que Calidad aprobó pero Empaque no recibió, división física distinta entre lotes de CRIMP). El input manual sería la **cifra real contada**, contra la teórica.

### 5.2 Interpretación B — **Merma / scrap** (material perdido, no vuelve)

> "De las 500, 12 se dañaron al empacar. No se empacan y no se devuelven."

- **Invariante:** `empacado + merma + devuelto = disponible`.
- **Impacto en cascada:** **ALTO**. La merma tendría que restarse en `decMissing` (`ShippingListDisplay.php:1961`) y afectaría la decisión del Paso 6 (`decCompletarCrimp`, `:1975`) y por tanto `lots.complete_crimp_qty` / `complete_pieces_qty`.
- **Requeriría** un campo separado de la Interpretación A: son conceptos distintos y sumarlos falsearía el inventario.

### 5.3 Interpretación C — **Corrección declarada que sustituye al calculado**

> "El sistema dice que sobran 40, pero conté 37. Guardo 37 y ése es el bueno."

- **Invariante:** el valor manual **tiene precedencia** sobre `getPackagedPiecesSurplus()` / `getPackagedCrimpSurplus()` en toda la UI.
- **Impacto en cascada:** **MEDIO-ALTO**. `decSurplus` y `decCrimpSurplus` (`ShippingListDisplay.php:1960, 1973`) alimentan `decCompletarCrimp = max(0, decSurplus − decCrimpSurplus)` (`:1975`), que se persiste en `lots.complete_crimp_qty` en la decisión D2a (`decisionCompleteCrimp` → `recordCrimpCompletion`). **Cambiar el sobrante cambia la cantidad a completar del siguiente ciclo.**
- Además implicaría **conflicto de nivel**: el manual es por Lote de CRIMP, el calculado es por Viajero. Habría que definir `sobrante_viajero = SUM(sobrante_manual de sus lotes de CRIMP)` — y decidir qué pasa si sólo se capturó en 1 de 3 lotes.

### 5.4 Tabla de impacto comparada

| | A · Devuelto | B · Merma | C · Corrección |
|---|---|---|---|
| Campo nuevo requerido | 2 | 2 (distintos de A) | 2 |
| Toca `quantity_packed_final` | ❌ No | ❌ No | ❌ No |
| Toca Packing Slip / Invoice | ❌ No | ❌ No | ❌ No |
| Toca `decSurplus` / Paso 6 | ❌ No | ⚠️ Sí (vía `decMissing`) | ⚠️ **Sí** |
| Toca `complete_crimp_qty` | ❌ No | ⚠️ Sí | ⚠️ **Sí** |
| Puede convivir con el calculado | ✅ Sí (lado a lado) | ✅ Sí | ❌ No (lo reemplaza) |
| Riesgo de regresión | **Bajo** | Medio | **Alto** |
| Esfuerzo relativo | 5 pts | 8 pts | 13 pts |

### 5.5 Recomendación de diseño

**Implementar la Interpretación A como versión 1**, con el campo persistido y **puramente declarativo** (se guarda, se muestra, se audita, **no participa en ningún cálculo aguas abajo**). Razones:

1. Es la única lectura **compatible con el texto de ayuda que ya está en pantalla** (`:184-186`), así que no contradice lo que el usuario ya lee.
2. Es la única con **riesgo de cascada nulo** — cumple RP-01 por construcción.
3. Si el cliente después confirma B o C, el campo ya existe y sólo hay que **conectarlo** (cambio aditivo, no destructivo).
4. Lo contrario —conectar primero y desconectar después— implicaría migrar datos ya capturados con semántica equivocada.

**Se debe confirmar con el cliente antes de la Fase 3** (ver P-01 en §14).

---

## 6. Modelo de Datos Propuesto

### 6.1 Opciones evaluadas

#### Opción 1 — Columnas nuevas en `crimp_lots` ✅ **RECOMENDADA**

```
crimp_lots.surplus_pieces           unsignedInteger  NULL  DEFAULT NULL
crimp_lots.surplus_crimps           unsignedInteger  NULL  DEFAULT NULL
crimp_lots.surplus_captured_at      dateTime         NULL
crimp_lots.surplus_captured_by      FK users         NULL  nullOnDelete
```

| Pros | Contras |
|---|---|
| Granularidad **exactamente** la que pide el requerimiento: 1 fila por Lote de CRIMP, 1 valor por concepto | No guarda historial de correcciones (mitigable con `surplus_captured_at/by` + log) |
| `crimp_lots` ya existe, ya tiene modelo, factory-able, y ya se eager-loadea en el modal (`:13`) | Añade 4 columnas a una tabla de 6 |
| Agregación por viajero trivial y en SQL: `SUM(crimp_lots.surplus_pieces)` | — |
| Nullable distingue **"no capturado"** (`NULL`) de **"capturado y es cero"** (`0`) — distinción de negocio real | — |
| Cero impacto en las tablas de la cadena crítica (§7) | — |
| Precedente en el proyecto: `date_code` se añadió a `crimp_lots` por migración (`2026_07_02_120000`) | — |

#### Opción 2 — Columnas en las tablas de pesadas

`packaging_piece_weighings.surplus_quantity` / `packaging_crimp_weighings.surplus_quantity`.

| Pros | Contras |
|---|---|
| No toca `crimp_lots` | ❌ **Cardinalidad equivocada:** hay **N pesadas** por Lote de CRIMP pero **1 sobrante**. ¿En cuál fila se guarda? |
| — | ❌ Si se borra la pesada que lo lleva (`deleteConfirmPieceWeighing`, `ShippingListDisplay.php:1157`), el sobrante desaparece silenciosamente |
| — | ❌ Un Lote de CRIMP **sin ninguna pesada** no podría tener sobrante — caso de negocio válido (todo sobró) |
| — | ❌ `SUM(quantity)` y `SUM(surplus_quantity)` en la misma tabla invitan a confundirlas — riesgo tipo BUG-A/BUG-B |

**Descartada.**

#### Opción 3 — Tabla nueva dedicada `crimp_lot_surpluses`

| Pros | Contras |
|---|---|
| Historial completo de capturas | Sobre-ingeniería para 2 enteros |
| Permite N capturas acumulativas si el negocio lo pidiera | Obliga a `latest()` o `SUM()` en cada lectura → riesgo de N+1 en un modal que ya carga 5 relaciones |
| Auditoría natural | 1 migración + 1 modelo + 1 factory + relaciones en 2 modelos |

**Reservar para v2** si el cliente confirma que necesita historial de correcciones (P-06 en §14).

#### Opción 4 — Campo JSON en `crimp_lots.comments` o columna JSON nueva

| Pros | Contras |
|---|---|
| Sin migración estructural | ❌ Imposible agregar en SQL (`SUM`) sin funciones JSON dependientes del motor — el proyecto soporta **MySQL o PostgreSQL** |
| — | ❌ Sin tipado, sin constraints, sin índices |
| — | ❌ Invalida cualquier validación a nivel BD |

**Descartada.**

### 6.2 Migración propuesta (esquema, NO implementar aún)

```php
// database/migrations/2026_08_XX_XXXXXX_add_surplus_capture_to_crimp_lots.php
Schema::table('crimp_lots', function (Blueprint $table) {
    // Sobrante DECLARADO manualmente por Empaque en el Paso 5, por Lote de CRIMP.
    // NULL = todavía no se capturó.  0 = se capturó y no hubo sobrante.
    $table->unsignedInteger('surplus_pieces')->nullable()->after('quantity')
          ->comment('Manguitas sobrantes declaradas por Empaque (Paso 5). NULL = sin capturar.');
    $table->unsignedInteger('surplus_crimps')->nullable()->after('surplus_pieces')
          ->comment('CRIMP sobrantes declarados por Empaque (Paso 5). NULL = sin capturar.');
    $table->dateTime('surplus_captured_at')->nullable()->after('surplus_crimps');
    $table->foreignId('surplus_captured_by')->nullable()->after('surplus_captured_at')
          ->constrained('users')->nullOnDelete();
});
```

### 6.3 Decisiones de modelado, justificadas

| ID | Decisión | Justificación |
|---|---|---|
| **D-01** | Tipo `unsignedInteger` | Igual que `crimp_lots.quantity` y `packaging_*_weighings.quantity`. Impide negativos a nivel BD (defensa en profundidad frente a la validación de Livewire) |
| **D-02** | **Nullable, default `NULL`** | `NULL ≠ 0`. Permite que la UI distinga "Empaque aún no declaró sobrante" de "Empaque declaró 0". Sin esto, el Paso 6 no puede saber si el dato es confiable |
| **D-03** | **Valor único, NO acumulativo** | El sobrante es un **estado final del lote**, no un evento repetible como la pesada. Acumular obligaría a una tabla hija (Opción 3) y haría imposible corregir a la baja. Segundo guardado **sobrescribe** |
| **D-04** | Auditoría en la misma fila (`_at`, `_by`) | Patrón ya usado en `lots` (`viajero_received_by`, `closure_decided_by`, `surplus_received_by` — `Lot.php:968-987`). Consistente con el proyecto, cero costo extra |
| **D-05** | **Sin `SoftDeletes` adicional** | `crimp_lots` ya usa `SoftDeletes` (`CrimpLot.php:17`); las columnas lo heredan |
| **D-06** | **Sin índice** | No se filtra ni ordena por sobrante. Un índice sólo añadiría costo de escritura |
| **D-07** | Ambas columnas en la **misma** migración | Son un solo concepto de negocio; separar migraciones complicaría el rollback |

### 6.4 Cambios en el modelo `CrimpLot`

```php
// app/Models/CrimpLot.php — añadir a $fillable (:19-26)
'surplus_pieces', 'surplus_crimps', 'surplus_captured_at', 'surplus_captured_by',

// añadir a $casts (:28-30)
'surplus_pieces'      => 'integer',
'surplus_crimps'      => 'integer',
'surplus_captured_at' => 'datetime',

// relación de auditoría
public function surplusCapturedByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'surplus_captured_by');
}

// predicado de conveniencia
public function hasSurplusCaptured(): bool
{
    return $this->surplus_pieces !== null || $this->surplus_crimps !== null;
}
```

### 6.5 Agregado opcional a nivel Viajero (**sólo lectura, sólo si el cliente lo pide**)

```php
// app/Models/Lot.php — NUEVOS métodos, NO tocan los existentes
public function getDeclaredPiecesSurplus(): ?int
{
    $vals = $this->crimpLots->pluck('surplus_pieces')->filter(fn ($v) => $v !== null);
    return $vals->isEmpty() ? null : (int) $vals->sum();
}

public function getDeclaredCrimpSurplus(): ?int { /* idem con surplus_crimps */ }
```

> ⚠️ **Nombrar `getDeclared*` y NO `getPackaged*Surplus`.** Reutilizar o sobrecargar los nombres existentes (`getPackagedPiecesSurplus`, `getPackagedCrimpSurplus`) es exactamente el error que produjo BUG-A/BUG-B: dos fuentes con nombre parecido, un consumidor leyendo la equivocada.

---

## 7. Impacto en Cascada (crítico)

### 7.1 Regla de protección RP-01 — **lo que NO debe cambiar, bajo ninguna circunstancia**

| Elemento | Archivo:línea verificado | Por qué es intocable |
|---|---|---|
| `Lot::getPackagedPiecesTotal()` | `app/Models/Lot.php:1062-1065` | Es la **fuente única** de "manguitas empacadas". Restarle el sobrante haría que el Packing Slip declare menos piezas de las físicamente enviadas → **factura incorrecta** |
| `Lot::getPackagedCrimpTotal()` | `app/Models/Lot.php:1070-1073` | Simétrico; alimenta `decCrimpPacked` en el Paso 6 |
| `Lot::getCompletionCycles()` | `app/Models/Lot.php:928-955` (rama CRIMP `:945-947`) | Corregido en commit `2d3351f` (BUG-B). Cualquier cambio aquí revive el bug |
| `Lot::getTotalCompletedPieces()` | `app/Models/Lot.php:960-963` | Consumido por `ShippingQueue.php:284` (`quantity_packed` del PS) y por `shipping-list-display.blade.php:320` |
| `LotPackagingObserver::updated()` | `app/Observers/LotPackagingObserver.php:75-77` | Corregido en `2d3351f` (BUG-A). Persiste `quantity_packed_final` |
| `lots.quantity_packed_final` | escrito en `LotPackagingObserver.php:88` y en `ShippingListDisplay.php:1824` | Snapshot del que cuelgan PS e Invoice |
| `packing_slip_items.quantity_packed` | `PackingSlipCreate.php:104`, `PackingSlipShow.php:317`, `ShippingQueue.php:284` | Snapshot **inmutable** del FPL-10 |
| `InvoiceFromPackingSlipService` | `app/Services/InvoiceFromPackingSlipService.php:158, 182` | Lee `psItem->quantity_packed` → FPL-12 |
| `CrimpLot::getPackagedPiecesTotal()` / `getPackagedCrimpTotal()` | `app/Models/CrimpLot.php:67-78` | Los usa `WoResume.php:58-59` |

**Formulación operativa de RP-01:** *La captura manual de sobrantes escribe **exclusivamente** en las cuatro columnas nuevas de `crimp_lots`. No lee ni escribe en `packaging_piece_weighings`, `packaging_crimp_weighings`, `lots`, `packaging_records`, `packing_slip_items` ni `invoice_items`.*

### 7.2 Análisis consumidor por consumidor

| # | Consumidor | Archivo:línea | Impacto **si se sigue la Interp. A (recomendada)** | Impacto **si se sigue B o C** |
|---|---|---|---|---|
| 1 | `getPackagedPiecesTotal()` | `Lot.php:1062` | **Ninguno** | Ninguno (tampoco deben tocarse) |
| 2 | `getPackagedCrimpTotal()` | `Lot.php:1070` | **Ninguno** | Ninguno |
| 3 | `getCompletionCycles()` | `Lot.php:945-947` | **Ninguno** | Ninguno |
| 4 | `getTotalCompletedPieces()` | `Lot.php:960` | **Ninguno** | Ninguno |
| 5 | `LotPackagingObserver` → `quantity_packed_final` | `LotPackagingObserver.php:75-92` | **Ninguno** | Ninguno |
| 6 | `markViajeroReceived()` → `quantity_packed_final` (ruta D2) | `ShippingListDisplay.php:1821-1826` | **Ninguno** | Ninguno |
| 7 | Columna **QTY Empacada / Pz Completadas** (`/admin/shipping-list`) | `shipping-list-display.blade.php:320`; `shipping-queue.blade.php` | **Ninguno** | Ninguno |
| 8 | **Packing Slip FPL-10** (snapshot) | `PackingSlipCreate.php:104`, `ShippingQueue.php:284`, `resources/views/pdf/packing-slip.blade.php` | **Ninguno** | Ninguno |
| 9 | **Invoice FPL-12** | `InvoiceFromPackingSlipService.php:158,182`, `resources/views/pdf/invoice.blade.php` | **Ninguno** | Ninguno |
| 10 | **PDF FPL-02** (Lista de Envío) | `resources/views/sent-lists/pdf/shipping-list.blade.php:133,150,167,217` | **Ninguno** — el PDF sólo imprime `lots.quantity` y `crimp_lots.quantity`, **nunca** empacadas ni sobrantes | Ninguno, salvo que se pida columna nueva |
| 11 | **Paso 6 · Decisión** — `decSurplus`, `decCrimpSurplus` | `ShippingListDisplay.php:1960, 1973` | **Ninguno** (siguen leyendo los calculados) | ⚠️ **ALTO** — cambian los números que ve Materiales |
| 12 | **Paso 6** — `decCompletarCrimp` → `lots.complete_crimp_qty` | `ShippingListDisplay.php:1975`; `decisionCompleteCrimp` | **Ninguno** | ⚠️ **ALTO** — cambia la cantidad a producir en el siguiente ciclo |
| 13 | **Paso 6** — `decMissing` | `ShippingListDisplay.php:1961` | **Ninguno** | ⚠️ **ALTO** en Interp. B |
| 14 | **Decisión de cierre del Paso 6** (`closure_decision`) | `Lot::CLOSURE_*` `Lot.php:1169-1190` | **Ninguno** | ⚠️ Cambia qué decisión sugiere el sistema |
| 15 | **Paso 7 · Modal Entrega de Viajero** | `partials/modal-viajero.blade.php:20-21, 54-55` | **Ninguno** — muestra los calculados. *Opcionalmente* añadir 2 stats declarados | — |
| 16 | **Paso 8 · Entrega de sobrantes** | `shipping-list-display.blade.php:921, 1013-1017`; `getPostQualityLifecycle()` `Lot.php:1214` | **Ninguno** — hoy CRIMP nunca lo dispara (§4.3). **Conectarlo es alcance nuevo** | ⚠️ Cambiaría el disparo |
| 17 | Columna **"Pz Sobrantes"** del tablero | `shipping-list-display.blade.php:312-317, 1037` | **Ninguno** — hoy da 0 en CRIMP. Ver P-05 | ⚠️ |
| 18 | Correo **"Empaque Terminado"** | `dispatchEmpaqueTerminado()` `ShippingListDisplay.php:1272-1304`; `EmpaqueTerminadoCrimpViajero` | **Ninguno** salvo que se decida incluir el sobrante declarado (recomendado, ver §8.6) | — |
| 19 | `WoResume` (resumen por WO) | `WoResume.php:53-77` | **Ninguno** | ⚠️ |
| 20 | Partes **NO-CRIMP** | `openPackagingModal` (otro modal) | **Ninguno** — el modal Paso 5 sólo se abre con `is_crimp` (`shipping-list-display.blade.php:963`) | Ninguno |

### 7.3 Riesgo residual identificado — el `confirmDone` invalidado

Toda operación de pesada pone `confirmDone = false` (`ShippingListDisplay.php:1125, 1153, 1161, 1169, 1195, 1227`). Esto obliga a re-confirmar el Paso 3 y bloquea el Paso 6 (botón "Continuar al paso 6" con `:disabled="!$confirmDone"`, `modal-confirm-empaque.blade.php:264`).

**Decisión requerida:** ¿guardar un sobrante debe invalidar `confirmDone` igual que una pesada?

- **Sí** (recomendado si el sobrante forma parte del resumen "Empaque Terminado"): consistente, el resumen del Paso 4 (`:217-218`) siempre refleja lo último.
- **No**: el usuario puede corregir el sobrante después de confirmar sin rehacer el flujo.

Se recomienda **Sí**, por consistencia con las otras 6 operaciones del modal. Ver P-07 en §14.

---

## 8. Diseño Backend

> ⚠️ **Recordatorio de §1.3: todo lo de esta sección debe replicarse en los DOS componentes** (`ShippingListDisplay` y `SentListPackagingView`), o el modal falla al abrirse desde la vista de Empaque.

### 8.1 Propiedades Livewire nuevas

```php
// Valores en edición del bloque actual (se rehidratan al cambiar de Lote de CRIMP)
public $cPieceSurplus = null;   // manguitas sobrantes — string|int|null
public $cCrimpSurplus = null;   // CRIMP sobrantes     — string|int|null
```

Se declaran **sin tipar estrictamente a `int`** porque un `<input type="number">` vacío llega como `''`; en `SentListPackagingView` las propiedades existentes sí están tipadas (`public int $cPieceQty = 0;` `:82`) — para las de sobrante conviene `public $cPieceSurplus = null;` en **ambos** componentes, o Livewire lanzará `TypeError` al vaciar el campo.

### 8.2 Hidratación al abrir el modal y al cambiar de Lote de CRIMP

El valor mostrado **depende del Lote de CRIMP seleccionado**, así que hay que rehidratar en dos momentos:

```php
// 1) En openConfirmModal(), tras fijar $this->confirmCrimpLotId
$this->hydrateSurplusFields();

// 2) Al cambiar de lote — hook de Livewire
public function updatedConfirmCrimpLotId(): void
{
    $this->hydrateSurplusFields();
    $this->resetErrorBag(['cPieceSurplus', 'cCrimpSurplus']);
}

private function hydrateSurplusFields(): void
{
    $cl = $this->confirmCrimpLotId ? CrimpLot::find($this->confirmCrimpLotId) : null;
    $this->cPieceSurplus = $cl?->surplus_pieces;
    $this->cCrimpSurplus = $cl?->surplus_crimps;
}
```

> ⚠️ **Trampa verificada:** el selector de Lote de CRIMP **no usa `wire:model`** sino `wire:click="$set('confirmCrimpLotId', {{ $cl->id }})"` (`modal-confirm-empaque.blade.php:56`). **`$set` SÍ dispara el hook `updatedConfirmCrimpLotId`** en Livewire 3, por lo que el hook funciona. Aun así conviene verificarlo explícitamente en un test (`T-04`, §13), porque es un supuesto de framework, no una lectura de código del proyecto.

### 8.3 Métodos de guardado

Dos métodos separados (uno por bloque), simétricos a `addConfirmPieceWeighing` / `addConfirmCrimpWeighing`:

```php
public function saveConfirmPieceSurplus(): void
{
    if (!$this->guardDepartment('packaging')) return;   // ← en SentListPackagingView: ensureCanEditDepartment()

    $this->validate([
        'confirmCrimpLotId' => 'required|exists:crimp_lots,id',
        'cPieceSurplus'     => 'required|integer|min:0',
    ], [
        'confirmCrimpLotId.required' => 'Selecciona primero el lote de CRIMP.',
        'cPieceSurplus.required'     => 'Captura el sobrante de manguitas (usa 0 si no hubo).',
        'cPieceSurplus.integer'      => 'El sobrante debe ser un número entero.',
        'cPieceSurplus.min'          => 'El sobrante no puede ser negativo.',
    ]);

    $crimpLot = CrimpLot::find($this->confirmCrimpLotId);
    if (!$crimpLot || $crimpLot->lot_id !== (int) $this->confirmLotId) {
        session()->flash('error', 'El lote de CRIMP ya no existe. Actualiza la página.');
        return;
    }

    // Guardia de inmutabilidad — ver §10.3
    if ($reason = $this->surplusLockedReason($crimpLot)) {
        session()->flash('error', $reason);
        return;
    }

    $crimpLot->update([
        'surplus_pieces'      => (int) $this->cPieceSurplus,
        'surplus_captured_at' => now(),
        'surplus_captured_by' => Auth::id(),
    ]);

    $this->confirmDone = false;            // ← ver §7.3 / P-07
    $this->dispatch('refresh-display');
    session()->flash('message', 'Sobrante de manguitas guardado.');
}

// saveConfirmCrimpSurplus(): idéntico sobre 'surplus_crimps' / 'cCrimpSurplus'
```

### 8.4 Transacciones

**No se requiere `DB::transaction()`** en el diseño recomendado: cada método escribe **una sola fila** de `crimp_lots`, operación atómica por definición. El proyecto tampoco las usa en `addConfirmPieceWeighing` (`ShippingListDisplay.php:1114-1121`), así que envolver aquí introduciría una inconsistencia de estilo sin beneficio.

**Sí se requeriría** si el cliente elige un guardado conjunto de ambos sobrantes con validación cruzada 1:1 (opción B de P-03, §14), porque entonces dos filas/columnas deben quedar consistentes entre sí.

### 8.5 Autorización

Se reutiliza **exactamente** el mecanismo existente, sin permisos nuevos:

| Componente | Guardia | Efecto |
|---|---|---|
| `ShippingListDisplay` | `guardDepartment('packaging')` (`:62-69`) → `canAccessDepartment()` (`:44-57`) | Permite rol `admin` o rol `Empaques` (mapa `ROLE_DEPARTMENT_MAP` `:34-39`) |
| `SentListPackagingView` | `ensureCanEditDepartment()` | Idem, con la validación adicional del `sentList` en scope |

**Motivo de no crear permiso nuevo:** el sobrante es un dato de Empaque capturado en el modal de Empaque; segregarlo obligaría a un usuario a tener dos permisos para completar un solo formulario. Si el cliente quiere restringirlo a supervisor, ver P-08 (§14).

### 8.6 Auditoría

`surplus_captured_at` + `surplus_captured_by` cubren "quién y cuándo" del **último** guardado. Es el mismo nivel de auditoría que el proyecto aplica a `viajero_received_by` / `closure_decided_by` / `surplus_received_by` (`Lot.php:968-987`).

**Si se requiere historial completo de correcciones**, se necesita la Opción 3 de §6.1 (tabla dedicada) — escalado explícito en P-06.

**Recomendación adicional:** incluir el sobrante declarado en el correo "Empaque Terminado" (`EmpaqueTerminadoCrimpViajero`, disparado en `ShippingListDisplay.php:1295-1300`), ya que Materiales es destinatario (`:1282`) y es quien recibirá físicamente el sobrante.

---

## 9. Diseño Frontend

### 9.1 Ubicación exacta en el Blade

**Archivo:** `resources/views/livewire/admin/sent-lists/partials/modal-confirm-empaque.blade.php`
**Punto de inserción:** entre la línea **171** (cierre del div "Total capturado") y la línea **172** (cierre del div del bloque).

```
:152-166   Alta rápida (input + botón Agregar)
:168-171   ── "Total capturado: N" ──────────────────────
                            ▼▼▼  AQUÍ VA EL NUEVO BLOQUE  ▼▼▼
:172       </div>   (cierre del bloque)
```

Esto satisface literalmente el requerimiento: *"después del total de las pesadas… dentro de cada bloque"*.

### 9.2 Extensión del array de configuración

El bloque se genera por `@foreach` sobre un array de 2 configs (`:78-113`). Se añaden 3 claves a cada elemento:

```php
// bloque manguitas (:79-95) — añadir:
'surplusProp'  => 'cPieceSurplus',
'surplusSave'  => 'saveConfirmPieceSurplus',
'surplusLabel' => 'Manguitas sobrantes',

// bloque CRIMP (:96-112) — añadir:
'surplusProp'  => 'cCrimpSurplus',
'surplusSave'  => 'saveConfirmCrimpSurplus',
'surplusLabel' => 'CRIMP sobrantes',
```

### 9.3 Markup propuesto (usa componentes ya existentes del proyecto)

```blade
{{-- Sobrante declarado — captura manual por Lote de CRIMP --}}
<div wire:key="cf-surplus-{{ $t['key'] }}-{{ $confirmCrimpLotId }}"
     class="border-t border-slate-200 bg-amber-50/50 px-3 py-3 dark:border-slate-700 dark:bg-amber-900/10">
    <x-ui.field :label="$t['surplusLabel']" optional
        hint="Cantidad realmente sobrante de ESTE lote de CRIMP. Usa 0 si no sobró nada."
        :error="$errors->first($t['surplusProp'])">
        <div class="flex gap-2">
            <input type="number" min="0" step="1"
                wire:model="{{ $t['surplusProp'] }}" placeholder="—"
                aria-label="{{ $t['surplusLabel'] }}"
                @disabled($cfSurplusLocked)
                class="min-w-0 flex-1 text-right text-base font-bold tabular-nums">
            <x-ui.btn variant="warning" wire:click="{{ $t['surplusSave'] }}"
                wire:loading.attr="disabled" wire:target="{{ $t['surplusSave'] }}"
                :disabled="$cfSurplusLocked">
                Guardar
            </x-ui.btn>
        </div>
    </x-ui.field>

    @if ($cfSel?->surplus_captured_at)
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
            Capturado el {{ \Carbon\Carbon::parse($cfSel->surplus_captured_at)->format('d/m/Y H:i') }}
            {{ $cfSel->surplusCapturedByUser?->name ? 'por '.$cfSel->surplusCapturedByUser->name : '' }}.
        </p>
    @endif
</div>
```

`x-ui.field` (`resources/views/components/ui/field.blade.php`) ya provee label + hint + error con el espaciado estándar; `x-ui.btn` y el `variant="warning"` ya se usan en el footer del propio modal (`:262`).

### 9.4 ⚠️ `wire:key` — el punto de fallo más probable

**El proyecto ya tuvo un bug de este tipo** (memoria de proyecto: *fix `wire:key` en modal "Cargar desde WOs"*). Aquí el riesgo es concreto y verificable:

El contenedor del bloque hoy es:

```blade
{{-- modal-confirm-empaque.blade.php:114 --}}
<div wire:key="cf-table-{{ $t['key'] }}" …>
```

La clave es **`cf-table-pw` / `cf-table-cw`** — **constante respecto al Lote de CRIMP seleccionado**. Hoy no importa, porque todo el contenido interno se re-renderiza desde el servidor. Pero el `<input wire:model="cPieceSurplus">` es un elemento **con estado propio en el DOM**: al cambiar de Lote de CRIMP, Livewire hará *morph* del mismo nodo y **puede conservar el valor del lote anterior** en pantalla mientras el servidor ya tiene otro.

**Mitigación obligatoria (dos capas):**

1. El nuevo div lleva `wire:key="cf-surplus-{{ $t['key'] }}-{{ $confirmCrimpLotId }}"` — al cambiar de lote la clave cambia y Livewire **destruye y recrea** el nodo.
2. **No** cambiar el `wire:key` de la línea 114 (`cf-table-…`) a uno dependiente del lote: eso forzaría a recrear también las listas de pesadas, con costo de render innecesario y riesgo de perder el foco en la edición inline (`:126`).

Adicionalmente, las filas de pesadas ya usan claves correctas por id (`:124` `wire:key="cf-{{ $t['key'] }}-{{ $w->id }}"`) y las opciones del Paso 1 también (`:50`). **No tocar ninguna de las dos.**

### 9.5 Comportamiento: tiempo real vs. al guardar

**Recomendado: `wire:model` diferido (por defecto en Livewire 3) + botón "Guardar" explícito.**

| Alternativa | Veredicto |
|---|---|
| `wire:model.live` con guardado automático | ❌ Escribe en BD en cada tecla; en un modal de piso con lector de báscula genera decenas de escrituras y hace imposible distinguir "corrigiendo" de "confirmado" |
| `wire:model.blur` con guardado en `updated` | ⚠️ Guardado implícito: el operador no sabe si quedó guardado |
| **`wire:model` + botón "Guardar"** | ✅ Consistente con el patrón "Agregar" del propio modal (`:157`); acción explícita y auditable; un solo `UPDATE` por captura |

### 9.6 Estados deshabilitados

| Condición | Efecto en la UI |
|---|---|
| El viajero no tiene Lotes de CRIMP | El bloque entero no se renderiza (ya cubierto por `@if ($cfCrimpLots->isEmpty())` `:36-43`) |
| `$confirmCrimpLotId === null` | Input y botón `disabled` (no hay dónde guardar) |
| Lote ya cerrado / en Packing Slip / facturado | Input y botón `disabled` + nota explicativa (§10.3) |
| Guardado en curso | `wire:loading.attr="disabled" wire:target="{{ $t['surplusSave'] }}"` — patrón idéntico a `:158` |
| Parte **NO-CRIMP** | **Nunca se llega aquí**: el modal completo sólo se abre para `is_crimp` (`shipping-list-display.blade.php:963`) |

### 9.7 La regla 1:1 — ¿validar coherencia manguitas vs. CRIMP?

La regla de negocio confirmada es **1 pieza = 1 manguita + 1 CRIMP**. Aplicada al sobrante, la lectura estricta sería `surplus_pieces == surplus_crimps`. **Esto es casi seguro incorrecto como validación dura**, y la evidencia está en el propio código del Paso 6:

```php
// app/Livewire/Admin/SentLists/ShippingListDisplay.php:1975
$this->decCompletarCrimp = $isCrimp ? max(0, $this->decSurplus - $this->decCrimpSurplus) : 0;
```

Esta fórmula (`piezas sobrantes − CRIMP sobrante`) **sólo tiene sentido si los dos sobrantes PUEDEN diferir**. Si fueran forzosamente iguales, `decCompletarCrimp` sería siempre 0 y la decisión D2a "Completar CRIMP" no existiría. **Por tanto: la asimetría entre sobrante de manguitas y sobrante de CRIMP es un hecho de negocio del sistema actual, no un error.**

**Recomendación:** **advertencia visual, nunca bloqueo.** Si `surplus_pieces !== surplus_crimps`, mostrar una `x-ui.note tone="warn"` del tipo *"Los sobrantes de manguitas (X) y de CRIMP (Y) no coinciden. Verifica que sea correcto."* Ver P-03 (§14).

### 9.8 Coexistencia con los stats del Paso 3

Los 4 stats actuales (`:180-187`) muestran los sobrantes **calculados a nivel viajero**. Con el nuevo campo habrá dos números de "sobrante" en el mismo modal, potencialmente distintos, y esto **confundirá al usuario** si no se resuelve.

Tres opciones (P-02, §14):

| Opción | Descripción | Recomendación |
|---|---|---|
| **A** | Dejar los stats como están y etiquetar el nuevo campo como **"declarado"** vs. "calculado" | ✅ Menor riesgo; requiere renombrar las etiquetas del Paso 3 a *"Piezas sobrantes (calculado)"* |
| **B** | Añadir 2 stats más (calculado + declarado, 6 en total) | ⚠️ `x-ui.stats cols="4"` habría que subir a 6; saturación visual |
| **C** | Sustituir los stats calculados por los declarados cuando existan | ❌ Interpretación C de §5 — riesgo de cascada alto sobre el Paso 6 |

---

## 10. Reglas de Validación y Casos Borde

### 10.1 Matriz de validación propuesta

| # | Regla | Implementación | Severidad |
|---|---|---|---|
| V-01 | `confirmCrimpLotId` obligatorio y existente | `required\|exists:crimp_lots,id` | **Bloqueo** |
| V-02 | El Lote de CRIMP debe pertenecer al viajero abierto | Chequeo explícito `$crimpLot->lot_id === $this->confirmLotId` | **Bloqueo** (IDOR: un método público de Livewire es invocable por petición directa — el propio proyecto documenta esta clase de riesgo en `ShippingListDisplay.php:1337-1342`) |
| V-03 | Sobrante entero | `integer` | **Bloqueo** |
| V-04 | Sobrante **≥ 0** | `min:0` + `unsignedInteger` en BD | **Bloqueo** |
| V-05 | Sobrante **≤ cota superior** | Ver §10.2 | **Ver §10.2** |
| V-06 | Coherencia 1:1 manguitas vs. CRIMP | Comparación en el render | **Advertencia** (§9.7) |
| V-07 | Lote no bloqueado por cierre/PS/factura | `surplusLockedReason()` | **Bloqueo** (§10.3) |
| V-08 | Permiso de departamento `packaging` | `guardDepartment` / `ensureCanEditDepartment` | **Bloqueo** |

### 10.2 Caso borde: **sobrante > total pesado** — no es un error

Este es el caso borde peor entendido y hay que dejarlo explícito.

**El sobrante NO está acotado por el total pesado.** El total pesado (manguitas empacadas) y el sobrante (manguitas no empacadas) son **conjuntos disjuntos** que suman lo disponible:

```
disponible (Calidad)  =  empacado (pesadas)  +  sobrante  +  merma
```

Un caso perfectamente válido: se pesaron **50** manguitas y sobraron **450**. `sobrante > total pesado` y es correcto.

**Cotas superiores candidatas:**

| Candidata | Fórmula | Veredicto |
|---|---|---|
| Total pesado del lote de CRIMP | `≤ $cfPiecesTotal` | ❌ **Incorrecta** — ver arriba |
| Cantidad del Lote de CRIMP | `≤ $cfSel->quantity` | ⚠️ Razonable para CRIMP (el objetivo del lote) pero no para manguitas |
| Disponible de Calidad − empacado (el sobrante calculado) | `≤ Lot::getPackagedPiecesSurplus()` | ⚠️ Es a **nivel viajero**, no de Lote de CRIMP → no comparable directamente |
| **Sin cota superior dura, con advertencia** | — | ✅ **Recomendada para v1** |

**Recomendación:** **no imponer cota superior dura**; mostrar advertencia (no bloqueo) si `surplus_pieces + total_pesado_del_lote > crimp_lot.quantity`. Motivo: la cota "correcta" depende de la semántica que el cliente aún no confirmó (§5), y una cota mal elegida **impediría capturar un dato real** en piso. Escalado en P-04 (§14).

### 10.3 Caso borde: lote ya cerrado / en Packing Slip / facturado

Predicado propuesto (**a implementar en el componente, no en el modelo**, para no ensanchar `CrimpLot`):

```php
private function surplusLockedReason(CrimpLot $crimpLot): ?string
{
    $lot = $crimpLot->lot;

    // 1. Ya facturado o con Packing Slip — el más duro. Precedente verificado:
    //    ShippingListDisplay.php:1850 usa exactamente este chequeo para bloquear
    //    la reversión de la entrega de viajero (decisión D-12).
    //    Existe ya el predicado Lot::isInPackingSlip() (Lot.php:270-273) — usarlo.
    if ($lot?->isInPackingSlip()) {
        return 'No se puede capturar el sobrante: el viajero ya tiene un Packing Slip generado.';
    }

    // 2. Ya en cola de despacho
    if ($lot?->ready_for_shipping) {
        return 'No se puede capturar el sobrante: el viajero ya está listo para embarque.';
    }

    // 3. Decisión de cierre tomada (Paso 6)  ← ¿bloqueo o sólo advertencia? P-09
    if ($lot?->hasClosureDecision()) {
        return 'No se puede capturar el sobrante: Materiales ya tomó la decisión de cierre (Paso 6).';
    }

    return null;
}
```

| Estado | Bloqueo propuesto | Justificación |
|---|---|---|
| Con Packing Slip (`Lot::isInPackingSlip()`, `Lot.php:270-273`) | **Duro** | Precedente D-12 del proyecto (`ShippingListDisplay.php:1849-1853`) |
| `ready_for_shipping = true` | **Duro** | Ya salió del alcance de Empaque |
| `closure_decision != null` | **Duro** (revisable) | El Paso 6 ya consumió los números. **Requiere confirmación** → P-09 |
| `viajero_received = true` (Paso 7) | **Sin bloqueo** | El sobrante se entrega en el Paso 8, **después** de la recepción del viajero |
| `confirmDone = true` | **Sin bloqueo**, pero invalida `confirmDone` | §7.3 / P-07 |

### 10.4 Resto de casos borde

| Caso | Comportamiento propuesto |
|---|---|
| **Sobrante negativo** | Bloqueado en 3 capas: `min:0` (Livewire), `min="0"` (HTML), `unsignedInteger` (BD) |
| **Campo vacío** | `required` → error *"Captura el sobrante (usa 0 si no hubo)"*. Alternativa: permitir `nullable` para "borrar" la captura. **Recomendado: `required`**; para borrar, poner `0` |
| **Edición posterior** | Permitida mientras no aplique §10.3. Sobrescribe valor + `_at` + `_by` (D-03) |
| **Concurrencia** (dos usuarios en el mismo Lote de CRIMP) | *Last-write-wins*. Es una sola columna, no hay estado compuesto que corromper. La marca `surplus_captured_by/_at` deja rastro de quién ganó. **No se requiere lock optimista** — sería más complejidad de la que el caso justifica |
| **Se borra el Lote de CRIMP** (soft delete) | El sobrante se va con él (misma fila). Correcto |
| **Se borran todas las pesadas del lote** | El sobrante **permanece** — es un dato independiente, no derivado. Correcto por diseño |
| **Partes NO-CRIMP** | El input **nunca aparece**: el modal Paso 5 sólo se abre con `is_crimp = true` (`shipping-list-display.blade.php:963`). Las NO-CRIMP usan `openPackagingModal`, otro modal, y su sobrante vive en `packaging_records.surplus_pieces` (`Lot.php:1022-1029`) — **flujo intacto** |
| **Viajero sin Lotes de CRIMP** | El modal muestra la advertencia de `:36-42` y no renderiza el Paso 2 → el input no existe. Correcto |
| **Livewire 4 / 404 en `wire:click`** | Si al probar aparece un 404 en el `Guardar`, es la pestaña vieja con endpoint ofuscado antiguo → **hard refresh** (issue conocido del proyecto) |

---

## 11. Riesgos y Consideraciones

| ID | Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|---|
| **R-01** | Añadir propiedades sólo a `ShippingListDisplay` y no a `SentListPackagingView` → modal roto en la vista de Empaque | **Alta** | Alto | Checklist de Fase 2; test T-09 que ejercita el modal desde **ambos** componentes |
| **R-02** | `wire:key` estático hace que el input muestre el sobrante del Lote de CRIMP anterior | **Alta** | Alto | §9.4 — clave que incluye `$confirmCrimpLotId`; test T-04 |
| **R-03** | Alguien "mejora" restando el sobrante de `getPackagedPiecesTotal()` → revive BUG-A/BUG-B, factura incorrecta | Media | **Crítico** | RP-01 documentada en §7.1; comentario `// RP-01 — NO restar` en el modelo; test de regresión T-08 |
| **R-04** | Nombrar el helper nuevo `getPackagedPiecesSurplus()`-parecido y que un consumidor lea el equivocado | Media | Alto | Nomenclatura `getDeclared*` obligatoria (§6.5) |
| **R-05** | El cliente esperaba semántica B o C y el dato capturado queda inservible | Media | Alto | Resolver P-01 **antes** de la Fase 3; el campo es reutilizable en las 3 semánticas |
| **R-06** | `TypeError` al vaciar el input en `SentListPackagingView` (propiedades tipadas `int`) | Media | Medio | Declarar `public $cPieceSurplus = null;` sin tipo (§8.1) |
| **R-07** | Imposibilidad de validar visualmente (no hay partes CRIMP en Listas de Envío) | **Certeza** | Medio | Seeder de prueba (§13.4) en entorno aislado; validación con el cliente en piso antes de cerrar |
| **R-08** | Ejecutar `php artisan test` con config cacheada **vacía `flexcon_db`** | Media | **Crítico** | `php artisan config:clear` **siempre** antes de testear. Documentado en §13.1 |
| **R-09** | Solapamiento con el hueco D2 CRIMP → shipping, asignado a otro desarrollador | Baja | Medio | Este trabajo **no toca** `ready_for_shipping` ni `complete_crimp/pieces/both`. Coordinar antes de mergear |
| **R-10** | `parts.is_crimp` tiene `default(true)` → una parte nueva creada sin el campo activa el flujo CRIMP inesperadamente | Baja | Medio | Fuera de alcance, pero **reportar al cliente** (§4.1) |

---

## 12. Plan de Implementación por Fases

> Estimación en **puntos relativos** (1 pt ≈ tarea trivial de una sola capa). No son horas.

| Fase | Alcance | Entregables | Pts | Depende de |
|---|---|---|---|---|
| **F0 · Decisión** | Resolver **P-01** (semántica), **P-02** (coexistencia con stats) y **P-09** (bloqueo tras Paso 6) | Respuestas registradas en este documento | — | Cliente |
| **F1 · Datos** | Migración `add_surplus_capture_to_crimp_lots` + `$fillable`/`$casts`/relación/`hasSurplusCaptured()` en `CrimpLot` | 1 migración, 1 modelo modificado | **3** | F0 |
| **F2 · Backend** | 2 propiedades + `hydrateSurplusFields()` + `updatedConfirmCrimpLotId()` + `saveConfirmPieceSurplus()` + `saveConfirmCrimpSurplus()` + `surplusLockedReason()` — **DUPLICADO en los 2 componentes** | `ShippingListDisplay.php`, `SentListPackagingView.php` | **8** | F1 |
| **F3 · Frontend** | 3 claves nuevas en el array de config + bloque de markup + `wire:key` + estados disabled + nota de auditoría | `modal-confirm-empaque.blade.php` | **5** | F2 |
| **F4 · Coherencia UI** | Renombrar stats del Paso 3 a *"(calculado)"*, añadir advertencia 1:1, incluir sobrante en el resumen del Paso 4 | mismo partial | **3** | F3 + P-02/P-03 |
| **F5 · Tests** | Suite `CrimpSurplusCaptureTest` (§13) + regresión RP-01 | `tests/Feature/…` | **5** | F3 |
| **F6 · Verificación en piso** | Sembrar parte CRIMP de prueba, recorrer el modal desde **ambas** pantallas, validar con el cliente | Evidencia (capturas) | **3** | F5 |
| **F7 · Opcionales** (fuera de alcance base) | Conectar el sobrante al Paso 8 (columna "Pz Sobrantes" + botón "Entregar") · incluirlo en el correo "Empaque Terminado" · agregado a nivel viajero | — | **8** | P-05, P-10 |

**Total del alcance base (F1–F6): 27 pts.** Con F7: 35 pts.

**Ruta crítica:** F0 → F1 → F2 → F3 → F5. **F2 es la fase de mayor riesgo** por la duplicación entre componentes (R-01).

---

## 13. Plan de Pruebas

> **Sólo descripción. No se ejecutó ningún test durante este análisis.**

### 13.1 ⚠️ Precaución obligatoria antes de ejecutar cualquier test

```bash
php artisan config:clear      # ← SIN ESTO, RefreshDatabase CORRE SOBRE flexcon_db Y LA VACÍA
php artisan test --filter=CrimpSurplusCaptureTest
```

Con la configuración cacheada, `RefreshDatabase` apunta a la base **real** `flexcon_db` y la borra. **`config:clear` primero, siempre.**

### 13.2 Suite propuesta: `tests/Feature/CrimpSurplusCaptureTest.php`

| ID | Caso | Aserción principal |
|---|---|---|
| **T-01** | Guardar sobrante de manguitas en un Lote de CRIMP | `crimp_lots.surplus_pieces === 40`, `surplus_captured_by === $packer->id`, `surplus_captured_at` no nulo |
| **T-02** | Guardar sobrante de CRIMP | `crimp_lots.surplus_crimps === 35` |
| **T-03** | Los sobrantes quedan **aislados por Lote de CRIMP** | Guardar en CL-1 no altera `surplus_pieces` de CL-2 |
| **T-04** | **Rehidratación al cambiar de Lote de CRIMP** (R-02) | Tras `$set('confirmCrimpLotId', $cl2->id)`, `assertSet('cPieceSurplus', <valor de CL-2>)` |
| **T-05** | Sobrante negativo rechazado | `assertHasErrors(['cPieceSurplus' => 'min'])` |
| **T-06** | Sobrante no numérico rechazado | `assertHasErrors(['cPieceSurplus' => 'integer'])` |
| **T-07** | **Sobrante > total pesado se ACEPTA** (§10.2) | Pesar 50, guardar sobrante 450 → `assertHasNoErrors()` |
| **T-08** | 🔴 **REGRESIÓN RP-01** | Con sobrantes capturados: `getPackagedPiecesTotal()`, `getPackagedCrimpTotal()`, `getTotalCompletedPieces()` y (tras `close_as_is`) `quantity_packed_final` **idénticos** a antes de capturar |
| **T-09** | El modal funciona desde **los DOS componentes** (R-01) | Mismo escenario ejercitado con `ShippingListDisplay` y con `SentListPackagingView` |
| **T-10** | Bloqueo con Packing Slip generado | `assertSee`/flash de error; `surplus_pieces` **sin cambio** |
| **T-11** | Bloqueo por permisos | Usuario con rol `Calidad` → error de permiso; sin escritura |
| **T-12** | Guardar sobrante invalida `confirmDone` (si se aprueba P-07) | `assertSet('confirmDone', false)` |
| **T-13** | Sobrevive al borrado de todas las pesadas | Borrar pesadas → `surplus_pieces` intacto |
| **T-14** | 🔴 **REGRESIÓN NO-CRIMP** | Un lote con `is_crimp = false` sigue usando `openPackagingModal`, `packaging_records` y `getPackagingTotalSurplus()` sin cambio alguno |
| **T-15** | Propagación **nula** a Packing Slip e Invoice | Con sobrante capturado, `packing_slip_items.quantity_packed` e `invoice_items.quantity` idénticos al caso sin sobrante |

### 13.3 Suites existentes que deben seguir en verde (regresión)

| Archivo | Motivo |
|---|---|
| `tests/Feature/CrimpQtyEmpacadaTest.php` | **Cubre BUG-A y BUG-B.** Si esta suite se rompe, RP-01 fue violada |
| `tests/Feature/CrimpConfirmModalTest.php` | Contrato del modal Paso 5 |
| `tests/Feature/CrimpDecisionTest.php` | Paso 6 y `quantity_packed_final` (`:168`, `:195`) |
| `tests/Feature/CrimpWeighingsTest.php` | Pesadas de piezas y CRIMP |
| `tests/Feature/CrimpPackagingNotifyTest.php` | Correo "Empaque Terminado" |
| `tests/Feature/PackingSlipCrimpPdfTest.php` / `PackingSlipCrimpScreenTest.php` | FPL-10 |
| `tests/Feature/SentListPdfExportTest.php` | FPL-02 |
| `tests/Feature/ShippingQueueTest.php` | Cola de despacho |
| `tests/Feature/SentListDepartmentGuardTest.php` | Guardias de permiso |

### 13.4 Cómo sembrar una parte CRIMP de prueba (sin tocar datos reales)

**Patrón ya existente y verificado** — `tests/Feature/CrimpConfirmModalTest.php:30-63` (`makeViajero()`):

```php
$part = Part::factory()->create(['is_crimp' => true]);
$po   = PurchaseOrder::factory()->approved()->create(['part_id' => $part->id, 'quantity' => 1000]);
$sentList = SentList::create([
    'po_id' => $po->id, 'status' => SentList::STATUS_PENDING,
    'current_department' => SentList::DEPT_SHIPPING,
    'shift_ids' => [], 'num_persons' => 1,
    'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
    'total_available_hours' => 0, 'used_hours' => 0, 'remaining_hours' => 0,
]);
$wo = WorkOrder::factory()->create([
    'purchase_order_id' => $po->id, 'status_id' => StatusWO::factory(),
    'sent_pieces' => 0, 'sent_list_id' => $sentList->id,
]);
$viajero = Lot::create([
    'work_order_id' => $wo->id, 'lot_number' => 'V-5',
    'quantity' => 1000, 'status' => Lot::STATUS_PENDING,
]);
CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-1', 'quantity' => 600]);
CrimpLot::create(['lot_id' => $viajero->id, 'crimp_lot_number' => 'CL-2', 'quantity' => 400]);
```

**Dos Lotes de CRIMP es el escenario mínimo correcto** para este requerimiento: con uno solo, los bugs de aislamiento (T-03) y de rehidratación (T-04) son invisibles.

**Para verificación visual en navegador (F6), en entorno de desarrollo aislado:**

1. **Nunca** con `php artisan test` ni `migrate:fresh` sobre `flexcon_db`.
2. Usar `php artisan tinker` con el snippet de arriba, sobre una **copia** de la base (existe `backup_antes_de_reset.sql` como referencia de estructura).
3. Añadir un rol `Empaques` al usuario de prueba, o usar `admin`.
4. Para que el botón de Empaque sea clicable hace falta que `Lot::canBePackaged()` sea `true` → se necesitan pesadas de Producción y aprobación de Calidad (`getQualityGoodPieces() > 0`). Sin esto, la celda de Empaque queda en `idle` (`shipping-list-display.blade.php:940-946`).
5. **Cabo suelto a limpiar:** anotar los ids del `Part`, `PurchaseOrder`, `SentList` y `Lot` sembrados y **borrarlos al terminar** (el proyecto ya arrastra datos de prueba `WIRETEST` sin limpiar).

---

## 14. Preguntas Abiertas para el Cliente

| ID | Estado | Bloquea |
|---|---|---|
| P-01 | **ABIERTA — CRÍTICA** | F0 → todo |
| P-02 | ABIERTA | F4 |
| P-03 | ABIERTA | F4 |
| P-04 | ABIERTA | F2 |
| P-05 | ABIERTA | F7 |
| P-06 | ABIERTA | F1 (elección de opción) |
| P-07 | ABIERTA | F2 |
| P-08 | ABIERTA | F2 |
| P-09 | ABIERTA | F2 |
| P-10 | ABIERTA | F7 |

---

### 🔴 P-01 — ¿Qué significa exactamente "pieza sobrante"? (LA PREGUNTA QUE DECIDE TODO)

- **(A)** **Material bueno que sobró y se devuelve a Materiales.** No se empacó, existe físicamente, hay que regresarlo. *(Coincide con el texto que ya está en pantalla; riesgo de cascada nulo.)* ← **recomendada**
- **(B)** **Merma / scrap.** Se dañó o se perdió; no se empaca y no se devuelve. *(Afectaría el cálculo de faltantes del Paso 6.)*
- **(C)** **Corrección del sobrante que el sistema ya calcula.** El operador cuenta y el número manual sustituye al calculado. *(Cambiaría la cantidad a completar del siguiente ciclo — riesgo alto.)*
- **(D)** Más de uno a la vez → entonces hacen falta **más de dos campos**.

### P-02 — El modal ya muestra "Piezas sobrantes" y "CRIMP sobrante" calculados. ¿Qué hacemos con ellos?

- **(A)** Se quedan, y el nuevo campo se etiqueta como **"declarado"** frente a los **"calculados"**. ← recomendada
- **(B)** Se muestran los cuatro (2 calculados + 2 declarados).
- **(C)** El declarado **reemplaza** al calculado cuando exista. *(Implica P-01 = C.)*

### P-03 — Regla 1:1: ¿el sobrante de manguitas debe ser igual al de CRIMP?

- **(A)** **No necesariamente**; sólo mostrar advertencia si difieren. ← recomendada (la fórmula `decCompletarCrimp = decSurplus − decCrimpSurplus`, `ShippingListDisplay.php:1975`, **exige** que puedan diferir)
- **(B)** Sí, deben ser iguales — validación dura que bloquea el guardado.
- **(C)** Un solo campo de sobrante para ambos, con la regla 1:1 implícita. *(Contradice el requerimiento, que pide dos inputs.)*

### P-04 — ¿Hay un máximo para el sobrante?

- **(A)** **Sin máximo duro**; sólo advertencia si `sobrante + empacado > cantidad del Lote de CRIMP`. ← recomendada
- **(B)** Máximo = cantidad del Lote de CRIMP (`crimp_lots.quantity`), bloqueo duro.
- **(C)** Máximo = piezas aprobadas por Calidad menos lo empacado (el sobrante calculado), bloqueo duro.

### P-05 — ¿El sobrante capturado debe alimentar la columna "Pz Sobrantes" del tablero y el botón "Entregar N pz sobrantes" (Paso 8)?

> **Contexto que el cliente debe conocer:** hoy **un viajero CRIMP nunca muestra sobrantes en esa columna ni dispara ese botón**, porque ambos leen `packaging_records`, tabla que el flujo CRIMP no escribe (`shipping-list-display.blade.php:312-317`, `:921`). Es un hueco **preexistente**, independiente de este requerimiento.

- **(A)** No por ahora; el sobrante es sólo informativo dentro del modal. ← recomendada para v1
- **(B)** Sí, conectar ambos → **alcance adicional (F7, ~8 pts)**.
- **(C)** Sí, pero en una entrega posterior.

### P-06 — ¿Se necesita historial de correcciones del sobrante?

- **(A)** No; basta saber **el último valor + quién y cuándo**. ← recomendada (Opción 1 de §6.1)
- **(B)** Sí, historial completo de cada captura → tabla dedicada (Opción 3, +5 pts).

### P-07 — Al guardar un sobrante, ¿debe invalidarse la confirmación del Paso 3?

- **(A)** **Sí**, igual que al agregar/editar/borrar una pesada; hay que volver a confirmar. ← recomendada (consistencia)
- **(B)** No; el sobrante se puede corregir sin rehacer la confirmación.

### P-08 — ¿Quién puede capturar el sobrante?

- **(A)** Cualquiera con acceso a Empaque (rol `Empaques` o `admin`), igual que las pesadas. ← recomendada
- **(B)** Sólo supervisor / rol específico → requiere permiso nuevo.

### P-09 — ¿Se puede corregir el sobrante después de que Materiales tomó la decisión del Paso 6?

- **(A)** **No**; después del Paso 6 queda bloqueado. ← recomendada (el Paso 6 ya consumió los números)
- **(B)** Sí, hasta que se genere el Packing Slip.
- **(C)** Sí siempre, con registro de la corrección.

### P-10 — ¿El sobrante declarado debe aparecer en el correo "Empaque Terminado" y en el resumen del Paso 4?

- **(A)** **Sí en ambos** — Materiales es destinatario del correo (`ShippingListDisplay.php:1282`) y es quien recibirá el material. ← recomendada
- **(B)** Sólo en el resumen del Paso 4, no en el correo.
- **(C)** En ninguno.

---

## Anexo A · Índice de referencias verificadas

| Concepto | Archivo:línea |
|---|---|
| Modal Paso 5 (vista) | `resources/views/livewire/admin/sent-lists/partials/modal-confirm-empaque.blade.php` (271 líneas) |
| — filtrado por Lote de CRIMP | `:19-20` |
| — totales por Lote de CRIMP | `:21-22` |
| — sobrantes calculados (nivel viajero) | `:23-24` |
| — selector de Lote de CRIMP (Paso 1) | `:46-71` |
| — array de config de los 2 bloques (Paso 2) | `:78-113` |
| — `wire:key` del bloque (⚠️ R-02) | `:114` |
| — **punto de inserción del nuevo input** | entre `:171` y `:172` |
| — stats del Paso 3 | `:180-187` |
| — resumen Paso 4 | `:205-223` |
| Include desde el tablero | `shipping-list-display.blade.php:1933` |
| Include desde Empaque | `packaging-view.blade.php:452` |
| Apertura condicionada a `is_crimp` (2 puntos) | `shipping-list-display.blade.php:963` y `:1372` |
| `Lot::isInPackingSlip()` | `app/Models/Lot.php:270-273` |
| Componente 1 — Paso 5 | `app/Livewire/Admin/SentLists/ShippingListDisplay.php:1062-1332` |
| Componente 2 — Paso 5 | `app/Livewire/Admin/SentLists/SentListPackagingView.php:875-…` |
| Guardia de permiso (comp. 1) | `ShippingListDisplay.php:62-69` + `ROLE_DEPARTMENT_MAP` |
| `Lot::isViajero()` | `app/Models/Lot.php:184-187` |
| `Lot::getPackagedPiecesTotal()` | `app/Models/Lot.php:1062-1065` |
| `Lot::getPackagedCrimpTotal()` | `app/Models/Lot.php:1070-1073` |
| `Lot::getCrimpTargetTotal()` | `app/Models/Lot.php:1078-1081` |
| `Lot::getPackagedPiecesSurplus()` | `app/Models/Lot.php:1086-1089` |
| `Lot::getPackagedCrimpSurplus()` | `app/Models/Lot.php:1094-1097` |
| `Lot::getCompletionCycles()` (rama CRIMP) | `app/Models/Lot.php:945-947` |
| `Lot::getTotalCompletedPieces()` | `app/Models/Lot.php:960-963` |
| `Lot::getPackagingTotalSurplus()` (NO-CRIMP) | `app/Models/Lot.php:1022-1029` |
| `Lot::getPostQualityLifecycle()` | `app/Models/Lot.php:1208-1292` |
| `CrimpLot` (modelo completo) | `app/Models/CrimpLot.php` (79 líneas) |
| `LotPackagingObserver` | `app/Observers/LotPackagingObserver.php:75-92` |
| `quantity_packed_final` en ruta D2 | `ShippingListDisplay.php:1824` |
| Paso 6 — `decSurplus` / `decCrimpSurplus` | `ShippingListDisplay.php:1960, 1973` |
| Paso 6 — `decCompletarCrimp` | `ShippingListDisplay.php:1975` |
| Bloqueo por Packing Slip (precedente D-12) | `ShippingListDisplay.php:1849-1853` |
| Packing Slip — ruta 1 | `app/Livewire/Admin/Shipping/ShippingQueue.php:284` |
| Packing Slip — ruta 2 | `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php:104` |
| Packing Slip — show | `app/Livewire/Admin/PackingSlips/PackingSlipShow.php:317` |
| Invoice FPL-12 | `app/Services/InvoiceFromPackingSlipService.php:158, 182` |
| PDF FPL-02 (sin cantidades empacadas) | `resources/views/sent-lists/pdf/shipping-list.blade.php:133, 150, 167, 217` |
| Migración `crimp_lots` | `database/migrations/2026_06_13_120000_create_crimp_lots_table.php` |
| Migración pesadas manguitas | `database/migrations/2026_06_16_120000_create_packaging_piece_weighings_table.php` |
| Migración pesadas CRIMP | `database/migrations/2026_06_16_120100_create_packaging_crimp_weighings_table.php` |
| `parts.is_crimp` (⚠️ `default(true)`) | `database/migrations/2025_12_10_051116_create_parts_table.php:21` |
| Patrón de seeding CRIMP en tests | `tests/Feature/CrimpConfirmModalTest.php:30-63` |
| Regresión BUG-A / BUG-B | `tests/Feature/CrimpQtyEmpacadaTest.php` · commit `2d3351f` |
| Componente `x-ui.field` | `resources/views/components/ui/field.blade.php` |
