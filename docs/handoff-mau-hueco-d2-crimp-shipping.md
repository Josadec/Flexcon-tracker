# Handoff para Mau — Hueco D2 CRIMP → Cola de Shipping

> **Autor del análisis:** Josadec (diagnóstico verificado contra el código actual el 2026-07-02).
> **Para:** Mau (implementación asignada a tu lado).
> **Alcance:** Backend Livewire + Model. **Sin cambios de esquema ni migraciones.**

---

## 1. El problema

Un viajero CRIMP cuya **decisión final del Paso 6** sea **D2a/b/c** (`complete_crimp` / `complete_pieces` / `complete_both`) **nunca obtiene `ready_for_shipping = true`**, por lo que **jamás aparece en la cola de embarque** (Shipping Queue).

- Es una **omisión silenciosa**: no hay crash ni corrupción de datos.
- D1 (`close_as_is`) y D3 (`new_lot`) **sí entran** normal, porque el observer los reconoce.
- Solo se manifiesta si en producción un viajero queda en D2 como estado final.

---

## 2. Causa raíz (confirmada punto por punto en el código actual)

1. **`ShippingListDisplay::recordCrimpCompletion()`** (~L2062) escribe `closure_decision` con las constantes `Lot::CLOSURE_COMPLETE_CRIMP/PIECES/BOTH` vía `decisionCompleteCrimp()` (L2078), `decisionCompletePieces()` (L2092), `decisionCompleteBoth()` (L2106). Valores string: `complete_crimp` / `complete_pieces` / `complete_both` (`Lot.php` L1006-1010).
2. **`LotPackagingObserver::updated()`** (`app/Observers/LotPackagingObserver.php`, L41-98). El array `$validClosureTypes` (L51-55) **SOLO** contiene `CLOSURE_COMPLETE_LOT`, `CLOSURE_NEW_LOT`, `CLOSURE_CLOSE_AS_IS`. El `if (!in_array(...))` de L57 **retorna temprano** para las tres decisiones `complete_*`.
3. **`ready_for_shipping = true`** se escribe en **UN SOLO lugar**: el `updateQuietly()` del observer (L87-92). Los demás sitios (`ShippingQueue.php` L444/464) solo lo ponen en `false`.
4. **La cola** filtra en `ShippingQueue.php` L238-239: `->where('ready_for_shipping', true)->whereDoesntHave('packingSlipItem')`. Idéntico al scope `Lot::scopeReadyForShipping()` (`Lot.php` L227-231).

**Resultado:** el viajero D2 queda con `ready_for_shipping = false` para siempre.

### Hallazgo adicional importante para el diseño
El botón "Marcar viajero como recibido" (`markViajeroReceived`, L1777 = Paso 7) se renderiza en el modal "Entrega de Viajero" (blade L2106-2110) siempre que `!$vjReceived`. **Ese modal puede abrirse aunque `closure_decision` sea `null`** ("Sin decisión todavía", blade L2035). Por eso la marca de shipping **DEBE gatearse por el tipo de decisión** — ver Paso 2. Además `markViajeroReceived` no toca `closure_decision`, así que un `update()` normal ahí **no** re-dispara el observer (no hay riesgo de doble ruta).

---

## 3. Decisión de diseño: gancho en `markViajeroReceived` (Opción A) ✅

**Opción A (recomendada)** — marcar `ready_for_shipping` en el **Paso 7** (cuando Empaque confirma la recepción del viajero), con reversión simétrica en `revertViajeroReceived`.

**Opción B (descartada)** — ampliar `$validClosureTypes` del observer con las constantes `complete_*` (3 líneas). Es **semánticamente incorrecta**: el observer dispara en el **Paso 6**, pero D2 significa "queda trabajo pendiente" (Materiales envía material / Empaque completa piezas o CRIMP). Marcarlo ahí metería a la cola un viajero que aún no se recibió físicamente, rompiendo el flujo de 2 pasos acordado y el diagrama (todas las decisiones terminan en el Paso 7).

---

## 4. Plan de implementación (paso a paso)

### Paso 1 — Helper de dominio en `app/Models/Lot.php` (~L999-1010)
Cerca de las constantes añadir:
- Un array/const con los tres tipos de completar: `[CLOSURE_COMPLETE_CRIMP, CLOSURE_COMPLETE_PIECES, CLOSURE_COMPLETE_BOTH]`.
- Un método `isCompletionClosure(): bool` → `in_array($this->closure_decision, [...], true)`.

*Motivo:* centralizar el criterio del gate y evitar strings mágicos dispersos.

### Paso 2 — `ShippingListDisplay::markViajeroReceived($lotId)` (~L1777)
Después de fijar `viajero_received = true` (bloque L1787-1791), en el mismo `update()` (o uno segundo), **solo si `$lot->isCompletionClosure()`**:
- `ready_for_shipping => true`
- `ready_for_shipping_at => now()`
- `quantity_packed_final => $lot->getPackagedPiecesTotal()`
- `closed_by_type => $lot->closure_decision`

**Gate obligatorio** con `isCompletionClosure()`: los viajeros sin decisión (o pre-decisión) que abran el modal Paso 7 NO entran a la cola, y D1/D3 no se tocan aquí (ya los marcó el observer). Usar `update()` normal (no cambia `closure_decision`, no re-dispara el observer).

### Paso 3 — `ShippingListDisplay::revertViajeroReceived($lotId)` (~L1801)
Al inicio, antes de revertir, aplicar la regla **D-12** replicando el patrón existente de L390:
- Si `$lot->packingSlipItem()->exists()` ⇒ `session()->flash('error', ...)` y `return` (no revertir).

Luego, en el `update()` de reversión (L1811-1815), añadir (solo si `$lot->isCompletionClosure()`, para simetría):
- `ready_for_shipping => false`
- `ready_for_shipping_at => null`
- `quantity_packed_final => null`
- `closed_by_type => null`

Esto saca al viajero de la cola al des-recibirlo.

### Paso 4 — Endurecer `reopenLot()` (~L2153) *[recomendado, cierra cabo suelto]*
`reopenLot` limpia `closure_decision = null` pero NO toca `ready_for_shipping` ni `viajero_received`. Un viajero D2 ya recibido (ready=true) que se reabra quedaría **fantasma** en la cola. Añadir al `update()` (L2164-2176), condicionado a que **NO** exista `packingSlipItem`:
- `ready_for_shipping => false`, `ready_for_shipping_at => null`, `quantity_packed_final => null`, `viajero_received => false` (+ `_at`/`_by` null).

Si prefieres alcance mínimo, este paso es opcional pero déjalo documentado como riesgo.

### Paso 5 — Verificación manual (Playwright)
Flujo end-to-end con una parte `is_crimp`: capturar manguitas → decisión D2a → confirmar que **NO** está en cola → `markViajeroReceived` → confirmar que aparece con `quantity_packed_final` correcto → `revertViajeroReceived` → confirmar que sale.

### Orden de ejecución
**1 → 2 → 3 → (4) → tests → verificación manual.**

---

## 5. Riesgos y casos borde

- **Doble marcado / idempotencia:** el botón cambia a "Revertir" cuando `viajero_received`. El gate `isCompletionClosure()` + valores deterministas lo hacen idempotente. No re-dispara el observer.
- **Viajero ya con `packingSlipItem` (D-12):** cubierto por el guard del Paso 3. Sin él, revertir dejaría inconsistente un lote ya facturado en PS.
- **Viajero sin decisión final que abre Paso 7:** el gate `isCompletionClosure()` evita marcarlo (preserva comportamiento actual).
- **D1/D3 (`close_as_is` / `new_lot`):** intactos; los marca el observer en Paso 6. `markViajeroReceived` no los toca porque el gate es solo `complete_*`. Verificar que no haya regresión.
- **`reopenLot` (Paso 4):** si se omite, un D2 recibido y luego reabierto queda fantasma `ready_for_shipping=true`.
- **`quantity_packed_final` para viajero:** usar `getPackagedPiecesTotal()` (suma de `packaging_piece_weighings.quantity` = manguitas), consistente con la rama viajero del observer (L75-77). **NO** usar `packagingRecords` (los viajeros CRIMP no los escriben — ese fue un bug previo).

---

## 6. Tests a correr

Extender `tests/Feature/CrimpDecisionTest.php` (ya tiene helpers para construir viajeros y disparar decisiones vía Livewire):
1. `test_d2a_completar_crimp_not_ready_until_received`: tras `decisionCompleteCrimp`, `ready_for_shipping === false`; tras `markViajeroReceived`, `=== true`, `quantity_packed_final === getPackagedPiecesTotal()`, y aparece en `ShippingQueue`.
2. Ídem D2b (`complete_pieces`) y D2c (`complete_both`).
3. `revertViajeroReceived` ⇒ `ready_for_shipping === false` y desaparece de la cola.
4. Reversión bloqueada cuando existe `packingSlipItem` (D-12).
5. Regresión: D1 (`decisionCloseAsIs`) y D3 (`new_lot`) siguen entrando a la cola vía observer.
6. (Si se hace Paso 4) reabrir un D2 recibido lo saca de la cola.

> **⚠️ ANTES de testear:** `php artisan config:clear`.
> Con config cacheada, `php artisan test` corre `RefreshDatabase` sobre la `flexcon_db` **real** y la vacía.
> Correr solo la suite CRIMP: `php artisan test --filter=Crimp`.

---

## 7. Archivos exactos a tocar

- `app/Models/Lot.php` — Paso 1 (helper `isCompletionClosure()` ~L999-1010).
- `app/Livewire/Admin/SentLists/ShippingListDisplay.php` — Paso 2 (`markViajeroReceived` ~L1777), Paso 3 (`revertViajeroReceived` ~L1801), Paso 4 opcional (`reopenLot` ~L2153).
- `tests/Feature/CrimpDecisionTest.php` — tests.

**Sin cambios en:** `LotPackagingObserver.php`, `ShippingQueue.php`, ni el blade (los botones del Paso 7 ya existen en `shipping-list-display.blade.php` L2094-2113).
