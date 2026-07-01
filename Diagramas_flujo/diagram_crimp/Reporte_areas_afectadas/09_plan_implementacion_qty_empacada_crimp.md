# 09 — Plan de implementación: "Qty Empacada" para partes CRIMP (Opción A)

> **Dueño:** Josadec · **Módulo:** Shipping List / Cola de envío (`ShippingQueue`, M-Empaque→Shipping).
> **Objetivo:** que la columna **"Qty Empacada"** y el snapshot que alimenta Packing Slip (FPL-10) e
> Invoice (FPL-12) muestren la cantidad real empacada de un **viajero CRIMP**, hoy siempre en **0**.
> **Base (verificada en código 2026-06-29):** el cálculo lee `packaging_records` (vacío en CRIMP); el empaque
> CRIMP vive en `packaging_piece_weighings` / `packaging_crimp_weighings`. Relaciones y helpers
> (`Lot::getPackagedPiecesTotal()`, `Lot::isViajero()`, `CrimpLot`) **ya existen**.
> **Fecha:** 2026-06-29.
> **Regla de oro:** el cambio aplica **solo a partes con CRIMP** (`parts.is_crimp = true`). NO-CRIMP conserva
> **exactamente** el flujo actual.
> **Leyenda:** ✅ = verificado en código · 🟡 = decisión / dato de negocio a confirmar.

---

## 0. Resumen del cambio

La columna "Qty Empacada" de la cola de envío usa `Lot::getTotalCompletedPieces()`, que solo suma
`packaging_records.packed_pieces`. El flujo CRIMP **nunca** escribe en esa tabla (registra en
`packaging_piece_weighings` y `packaging_crimp_weighings`), por lo que **todo lote CRIMP muestra 0** y ese 0
se propaga al snapshot `packing_slip_items.quantity_packed` y de ahí al Invoice y a los PDFs.

**Opción A (la de este plan):** hacer el cálculo de "piezas completadas" **consciente de CRIMP** en una sola
fuente del modelo `Lot`, de modo que:

- para un **viajero CRIMP**, "piezas completadas" = **manguitas empacadas** (`getPackagedPiecesTotal()`),
- para NO-CRIMP, **sin cambios** (`packaging_records`).

Esto corrige la columna, el snapshot del Packing Slip y, por la cadena de snapshots ya existente, el Invoice y
los PDFs — todo desde un único punto.

### Fundamento de negocio (confirmado por el usuario, 2026-06-29)

- **1 pieza de 189-10492 = 1 manguita + 1 CRIMP** (relación **1:1**). Empaque junta manguita + crimp en una sola
  caja y se cuentan como **un solo número de parte**.
- La "Cantidad" que ve el cliente (FPL-10 / FPL-02) = la cantidad del **Lote de CRIMP** abierto. Ej.: Viajero
  WO 2040090 = 60,000 = 4 lotes de CRIMP (2,900 + 44,700 + 10,600 + 1,800).
- Por la relación 1:1, **manguitas = piezas = crimps** en un lote completo. Por eso `getPackagedPiecesTotal()`
  (manguitas) es la fuente correcta para "Qty Empacada".

---

## 1. Estado actual del código (evidencia ✅)

### 1.1 El cálculo afectado — `app/Models/Lot.php`

| Elemento | Línea | Qué hace hoy |
| --- | --- | --- |
| `isViajero()` | **180-183** | `(bool) part->is_crimp` — discriminador a reutilizar |
| `getCompletionCycles()` | **758-780** | Arma los ciclos; ciclo final = `packagingRecords->sum('packed_pieces')` (**772**) |
| `getTotalCompletedPieces()` | **785-788** | Suma las `pieces` de los ciclos → **lo que pinta la columna y el snapshot** |
| `getPackagedPiecesTotal()` | **887-890** | `packagingPieceWeighings()->sum('quantity')` — **manguitas del viajero** ⭐ fuente correcta |
| `getPackagedCrimpTotal()` | **895-898** | `packagingCrimpWeighings()->sum('quantity')` — crimps (métrica paralela) |
| `getCrimpTargetTotal()` | **903-906** | `crimpLots()->sum('quantity')` — **objetivo planeado**, NO empacado |
| Consts cierre NO-CRIMP | **994-998** | `complete_lot`, `new_lot`, `close_as_is` |
| Consts cierre CRIMP | **1001-1005** | `complete_crimp`, `complete_pieces`, `complete_both` |

### 1.2 Puntos que consumen el valor (cadena de snapshots)

| Capa | Archivo:línea | Hoy |
| --- | --- | --- |
| Columna "Qty Empacada" | `resources/views/livewire/admin/shipping/shipping-queue.blade.php:133` (header) / **:212** (valor) | `number_format($lot->getTotalCompletedPieces())` |
| Snapshot Packing Slip | `app/Livewire/Admin/Shipping/ShippingQueue.php:231` | `'quantity_packed' => $lot->getTotalCompletedPieces()` |
| `quantity_packed_final` | `app/Observers/LotPackagingObserver.php:72` | `(int) $lot->packagingRecords()->sum('packed_pieces')` — **mismo bug** 🟡 |
| Invoice desde PS | `app/Services/InvoiceFromPackingSlipService.php:127, 158, 182` | usa `psItem->quantity_packed` |
| PDF FPL-10 (orden) | `app/Http/Controllers/PackingSlipPdfController.php:26` | ordena por `quantity_packed` |

### 1.3 Datos por lote de CRIMP (ya existen ✅)

`app/Models/CrimpLot.php`: cada lote de CRIMP tiene sus **propias** pesadas:
- `getPackagedPiecesTotal()` **66-69** (manguitas del lote), `getPackagedCrimpTotal()` **74-77** (crimps del lote).

Por tanto `Lot::getPackagedPiecesTotal()` (nivel viajero) = suma de las manguitas de **todos** sus lotes de
CRIMP = manguitas totales del viajero. Es exactamente la fila que se muestra en la cola.

### 1.4 Evidencia en BD (parte 189-10492, 2026-06-29)

Los 10 lotes dan `getTotalCompletedPieces() = 0`, incluso uno con **40,000 manguitas / 40,000 crimps**
realmente pesados. El lote de prueba #01 tiene `crimpLots target = 60,000` pero solo 100 manguitas / 200 crimps
(dato de prueba; la relación real es 1:1).

---

## 2. Cambio principal (fuente única de verdad) — `Lot::getCompletionCycles()`

En el bloque del ciclo final (`Lot.php:771-775`), elegir la fuente según CRIMP:

```php
// Antes (línea 772):
$finalPacked = (int) $this->packagingRecords->sum('packed_pieces');

// Después:
$finalPacked = $this->isViajero()
    ? $this->getPackagedPiecesTotal()                       // CRIMP: manguitas (= piezas, relación 1:1)
    : (int) $this->packagingRecords->sum('packed_pieces');  // NO-CRIMP: sin cambios
```

- `getTotalCompletedPieces()` (785-788) **no se toca**: hereda el arreglo automáticamente.
- Con esto, columna + snapshot del Packing Slip + (vía cadena) Invoice y PDFs quedan correctos para CRIMP, sin
  tocar la rama NO-CRIMP.

### 2.1 Consideración: el bloque de `completionLogs` (762-764) 🟡

El primer bloque suma `lot_completion_logs.packed_pieces` (ciclos de "Completar Lote", multi-ciclo). Para CRIMP,
confirmar si ese flujo genera `completionLogs`:
- Si **no** los genera (caso típico CRIMP: un solo cierre), basta el cambio de §2.
- Si **sí** los genera con cantidades de `packaging_records`, habría que decidir si esos logs también deben
  leer manguitas. **Verificar antes de cerrar** (ver §6, decisión D-2).

> Alternativa de menor superficie si el bloque `completionLogs` no aplica a CRIMP: dejar §2 tal cual; es el 99%
> de los casos CRIMP observados (un único ciclo de cierre).

---

## 3. Cambio secundario — `LotPackagingObserver` (`quantity_packed_final`)

`LotPackagingObserver::updated()` calcula `quantity_packed_final` con el mismo patrón roto (`:72`) y marca
`ready_for_shipping = true`. Dos hechos a tener en cuenta:

1. ✅ **El observer solo dispara para cierres NO-CRIMP** (`$validClosureTypes` = `complete_lot` / `new_lot` /
   `close_as_is`, líneas **51-55**). Los cierres **propios de CRIMP** (`complete_crimp`, `complete_pieces`,
   `complete_both`) **no entran** → para esos lotes el `ready_for_shipping` y el snapshot los pone la ruta de
   cierre CRIMP (`ShippingListDisplay`), no este observer. 🟡 **Verificar** dónde se setea `ready_for_shipping`
   y `quantity_packed_final` para un cierre CRIMP y que ahí también se use la fuente de manguitas.
2. Los lotes CRIMP que en la BD tienen `close_as_is` / `new_lot` **sí** pasaron por este observer y por eso
   quedaron con `quantity_packed_final = 0`. Conviene alinear:

```php
// LotPackagingObserver.php:72 — alinear a la fuente única:
$quantityPackedFinal = (int) $lot->getTotalCompletedPieces();
```

Así `quantity_packed_final` queda consistente con la columna y el snapshot. (La columna y el snapshot del PS hoy
usan `getTotalCompletedPieces()`, no `quantity_packed_final`; este ajuste evita un valor incoherente guardado.)

> 🟡 **Decisión D-3:** ¿la cola/PS deben preferir `quantity_packed_final` sobre `getTotalCompletedPieces()` en
> algún punto? Hoy la columna (212) y el snapshot (231) usan el método; el observer solo persiste el campo. Si
> se confirma que el campo es secundario/informativo, el cambio de §3 es "por consistencia", no funcional.

---

## 4. Rendimiento — evitar N+1

La columna y `getPackagedPiecesTotal()` recorren `packagingPieceWeighings`. Añadir eager-load donde se arma la
cola para no consultar por fila:

- `app/Livewire/Admin/Shipping/ShippingQueue.php` (método `render()` / query de `lotsInQueue`): agregar
  `with(['packagingPieceWeighings'])` (y `packagingCrimpWeighings` si se usa en la vista) a la carga de lotes.
- Igual en `ShippingQueue::createPackingSlip()` si itera lotes para el snapshot.

> Sin esto, el arreglo es correcto pero dispara una consulta por lote en cola.

---

## 5. Checklist de archivos a tocar

- [ ] `app/Models/Lot.php` — `getCompletionCycles()` (772): fuente CRIMP-aware (§2). **Cambio núcleo.**
- [ ] `app/Observers/LotPackagingObserver.php` — (72) alinear `quantity_packed_final` a `getTotalCompletedPieces()` (§3).
- [ ] `app/Livewire/Admin/Shipping/ShippingQueue.php` — eager-load `packagingPieceWeighings` (§4); snapshot (231) **no cambia** (hereda).
- [ ] (Verificación) ruta de cierre CRIMP en `app/Livewire/Admin/SentLists/ShippingListDisplay.php` — confirmar dónde setea `ready_for_shipping` / `quantity_packed_final` para `complete_*` CRIMP (§3.1).
- [ ] **Sin** migraciones nuevas, **sin** rutas nuevas, **sin** cambios en `shipping-queue.blade.php` (212 hereda).

---

## 6. Decisiones de negocio / puntos a confirmar

| # | Decisión | Estado |
| - | -------- | ------ |
| D-1 | "Qty Empacada" CRIMP = **manguitas** (`getPackagedPiecesTotal`) | ✅ **Confirmado** (1 pieza = 1 manguita + 1 crimp, 1:1). |
| D-2 | ¿El flujo CRIMP genera `lot_completion_logs`? Si sí, ¿deben leer manguitas? | 🟡 Verificar (§2.1). |
| D-3 | ¿Algún consumidor prefiere `quantity_packed_final` sobre el método? | 🟡 Verificar (§3). |
| D-4 | ¿Validar en M6 que manguitas == crimps (relación 1:1) al confirmar empaque? | 🟡 Opcional; hoy el modal NO valida la relación. Mejora aparte. |
| D-5 | "Total de cajas" del FPL-10 (múltiplos de 100) | 🟡 Fuera de alcance: hoy es llenado **manual** en el PDF; no hay campo en BD. |

---

## 7. Pruebas (TDD recomendado)

- [ ] **CRIMP feliz:** parte `is_crimp=true` con N manguitas pesadas en sus `crimp_lots` y cierre → la columna y
      `getTotalCompletedPieces()` devuelven N (no 0); snapshot `quantity_packed = N`.
- [ ] **Propagación:** crear Packing Slip de ese viajero → `packing_slip_items.quantity_packed = N`; generar
      Invoice → `invoice_items.quantity = N`.
- [ ] **Relación 1:1:** con manguitas == crimps, el resultado es el mismo por cualquiera de los dos; documentar
      que se toma manguitas.
- [ ] **Regresión NO-CRIMP:** parte `is_crimp=false` → `getTotalCompletedPieces()` idéntico a hoy (lee
      `packaging_records`). Snapshot y PDF sin cambios.
- [ ] **Observer:** alinear no rompe el flujo NO-CRIMP (`quantity_packed_final` sigue = piezas empacadas).
- [ ] ⚠️ **Cuidado con la BD real:** correr `php artisan config:clear` antes de `php artisan test` (RefreshDatabase
      puede vaciar `flexcon_db` si la config está cacheada).

---

## 8. Riesgos y orden de trabajo

| # | Riesgo | Mitigación |
| - | ------ | ---------- |
| R1 | **Romper NO-CRIMP** en el cálculo de piezas | El cambio vive dentro de `if ($this->isViajero())`; rama `else` idéntica a hoy. Test de regresión NO-CRIMP. |
| R2 | **Cierre CRIMP no pasa por el observer** y setea el snapshot en otra ruta | Verificar `ShippingListDisplay` (cierre `complete_*`) y aplicar la misma fuente de manguitas (§3.1). |
| R3 | **N+1** al sumar pesadas por fila | Eager-load en la query de la cola (§4). |
| R4 | **Datos de prueba inconsistentes** (lote 100/200) ensucian la validación | Usar datos 1:1 reales para probar; el 100/200 es prueba vieja. |
| R5 | **`completionLogs` multi-ciclo** en CRIMP con cantidades de `packaging_records` | Resolver D-2 antes de cerrar; si no aplica, sin impacto. |

**Orden sugerido para Josadec:**
1. Test rojo: viajero CRIMP con manguitas pesadas espera `getTotalCompletedPieces() = N`.
2. Cambio núcleo en `Lot::getCompletionCycles()` (§2) → test verde.
3. Verificar ruta de cierre CRIMP y `quantity_packed_final` (§3 + R2); alinear observer.
4. Eager-load en la cola (§4).
5. Pruebas de propagación PS → Invoice → PDF.
6. Regresión NO-CRIMP completa.

---

## 9. Referencias

- Análisis previo: [`08_shipping_list_crimp_analisis.md`](08_shipping_list_crimp_analisis.md) (FPL-10 / cantidad empacada CRIMP, §4 y §6).
- Documento cliente: `FPL-10 Shipping List SL0001305 Rev2.pdf` (esta carpeta) y
  `Diagramas_flujo/Estructura/docs/FPL-02 Lista de envio junio-10-2026 FINAL.pdf` (WO 2040090 / 189-10492 = 60,000 en 4 lotes de CRIMP).
- Código:
  - `app/Models/Lot.php` — `getCompletionCycles()` (758-780), `getTotalCompletedPieces()` (785-788),
    `isViajero()` (180-183), `getPackagedPiecesTotal()` (887-890), `getPackagedCrimpTotal()` (895-898),
    `getCrimpTargetTotal()` (903-906).
  - `app/Models/CrimpLot.php` — pesadas por lote (66-77).
  - `app/Livewire/Admin/Shipping/ShippingQueue.php` — `createPackingSlip()` (167), snapshot (231).
  - `app/Observers/LotPackagingObserver.php` — `quantity_packed_final` (72), tipos de cierre (51-55).
  - `app/Services/InvoiceFromPackingSlipService.php` — `quantity` desde `quantity_packed` (127, 158, 182).
  - `resources/views/livewire/admin/shipping/shipping-queue.blade.php` — header (133), valor (212).
  - `app/Livewire/Admin/SentLists/ShippingListDisplay.php` — decisión de cierre Paso 6 usa `getPackagedPiecesTotal()` (1898) [precedente del criterio "manguitas = empacado" en CRIMP].
