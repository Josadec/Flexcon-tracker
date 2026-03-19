# Analisis Tecnico: WO Listos para PS vs Packing Slip — Son lo mismo?

**Fecha:** 2026-03-17
**Elaborado por:** Arquitecto de Software - FlexCon Tracker
**Version:** 1.0
**Proposito:** Determinar si "WO Listos para PS" y "Packing Slip" son la misma pantalla/funcionalidad o si son conceptos distintos, y documentar la relacion arquitectural entre ambos modulos.

---

## Resumen Ejecutivo

**Son conceptos distintos y complementarios.** No son la misma pantalla, no apuntan a la misma URL, no usan el mismo componente Livewire, y no muestran los mismos datos. Sin embargo, estan profundamente relacionados: "WO Listos para PS" es la antesala obligatoria de "Packing Slip". Uno es la cola de materia prima (lotes empacados sin PS asignado), el otro es el documento formal de despacho ya creado.

La analogia mas precisa: "WO Listos para PS" es la **sala de espera**, y "Packing Slip" es el **documento de salida**.

---

## 1. Son lo mismo?

**No.** Son dos pantallas distintas con URLs, componentes Livewire, datos y propositos completamente diferentes.

### Comparacion directa de identidad tecnica

| Caracteristica | WO Listos para PS | Packing Slip (lista) |
|---|---|---|
| URL | `/admin/shipping-queue` | `/admin/packing-slips` |
| Nombre de ruta | `admin.shipping.queue` | `admin.packing-slips.index` |
| Componente Livewire | `App\Livewire\Admin\Shipping\ShippingQueue` | `App\Livewire\Admin\PackingSlips\PackingSlipList` |
| Archivo PHP | `app/Livewire/Admin/Shipping/ShippingQueue.php` | `app/Livewire/Admin/PackingSlips/PackingSlipList.php` |
| Vista Blade | `shipping/shipping-queue.blade.php` | `packing-slips/packing-slip-list.blade.php` |
| Modelo principal | `Lot` | `PackingSlip` |
| Tabla principal en BD | `lots` (filtered) | `packing_slips` |
| Estado del dato | Lotes SIN PS asignado | PS YA CREADOS |

---

## 2. Que hace cada uno?

### 2.1 WO Listos para PS (`/admin/shipping-queue`)

Es la **cola de despacho**. Muestra todos los lotes que ya completaron el ciclo de Empaque y estan esperando ser incluidos en un Packing Slip.

**Condicion de aparicion en la cola (scope `scopeReadyForShipping`):**
```
lots.ready_for_shipping = true
AND NOT EXISTS (
    SELECT * FROM packing_slip_items WHERE packing_slip_items.lot_id = lots.id
)
```

Un lote entra a esta cola cuando el area de Empaque registra la decision de cierre (`closure_decision = 'complete_lot' | 'new_lot' | 'close_as_is'`) y el `LotPackagingObserver` activa automaticamente `ready_for_shipping = true`.

Un lote **sale** de esta cola cuando se crea un `PackingSlipItem` que lo referencia, lo que ocurre al crear un PS con ese lote incluido.

**Funciones disponibles en esta pantalla:**
- Ver lotes empacados pendientes de despacho con sus datos de WO, parte, cantidad y tipo de cierre
- Seleccionar uno o varios lotes (checkbox) para agruparlos en un nuevo PS
- Ingresar el `label_spec` por lote al momento de crear el PS
- Agregar notas al PS que se va a crear
- Detectar lotes bloqueados (sin `external_wo_number` en su WO) con badge naranja
- Devolver un lote bloqueado al area de Empaque (con motivo y auditoria)
- Filtrar por tipo de cierre y buscar por lote, parte o WO externo

**Lo que NO hace esta pantalla:**
- No muestra PS ya creados
- No permite editar un PS existente
- No cambia el estado de un PS
- No genera el PDF FPL-10

### 2.2 Packing Slip — Lista (`/admin/packing-slips`)

Es el **registro historico y gestion de Packing Slips ya creados**. Muestra todos los documentos PS generados, con su numero correlativo (formato `PS-YYYY-NNNN`), estado, cantidad de items y fechas.

**Datos que muestra:**
- PS Number (ej: `PS-2026-0001`)
- Estado del PS: `pending` (Pendiente), `shipped` (Despachado), `cancelled` (Cancelado)
- Numero de items (lotes) incluidos en el PS
- Usuario que lo creo
- Fecha de creacion
- Fecha de despacho (si ya fue despachado)

**Funciones disponibles:**
- Buscar por numero de PS
- Filtrar por estado (Pendiente / Despachado / Cancelado)
- Ver el detalle de un PS especifico (`/admin/packing-slips/{ps_number}`)
- Eliminar un PS en estado borrador (libera los lotes para que regresen a la cola)
- Acceder a la pantalla de creacion de PS (`/admin/packing-slips/create`)

**Diferencia critica:** Esta pantalla muestra PS que YA EXISTEN. La cola de Shipping muestra lotes que AUN NO tienen PS.

### 2.3 Packing Slip — Creacion (`/admin/packing-slips/create`)

Existe una tercera pantalla relacionada: el formulario de creacion standalone. Es una ruta alternativa para crear PS que muestra los mismos lotes disponibles que la ShippingQueue, pero en una pagina dedicada (en vez del modal). Esta pantalla es tecnicamente redundante con el flujo de la ShippingQueue.

### 2.4 Packing Slip — Detalle (`/admin/packing-slips/{ps_number}`)

El componente `PackingSlipShow` permite ver todos los detalles de un PS: lotes incluidos con sus snapshots (WO code, cantidad, label_spec, date code), cambiar el estado, editar lotes asignados, y acceder al PDF FPL-10.

---

## 3. Flujo de trabajo entre ambos

El orden de uso es secuencial: primero se usa la cola de Shipping, luego se gestionan los PS creados.

### Diagrama de flujo ASCII

```
PROCESO DE PRODUCCION
=====================

[Empaque cierra lote]
  closure_decision establecida por operador
        |
        v
[LotPackagingObserver]
  lots.ready_for_shipping = true
  lots.closed_by_type = <tipo>
  lots.quantity_packed_final = <suma piezas>
        |
        v
+---------------------------------------+
|   WO LISTOS PARA PS                   |
|   URL: /admin/shipping-queue          |
|   Componente: ShippingQueue           |
|                                       |
|   Muestra: Lotes con                  |
|     ready_for_shipping = true         |
|     AND sin PackingSlipItem           |
|                                       |
|   [Lote sin WO externo]               |
|     -> Badge naranja (bloqueado)      |
|     -> Opcion "Devolver a Empaque"    |
|                                       |
|   [Lote con WO externo]               |
|     -> Checkbox habilitado            |
|     -> Usuario selecciona lotes       |
|     -> Ingresa label_spec por lote    |
|     -> Clic "Crear Packing Slip"      |
|     -> Modal de confirmacion          |
|     -> Confirmar                      |
+---------------------------------------+
        |
        | createPackingSlip()
        | (transaccion BD)
        v
  PackingSlip creado (status = 'pending')
  PackingSlipItems creados (uno por lote)
  Los lotes SALEN de la cola automaticamente
  (whereDoesntHave('packingSlipItem'))
        |
        v
+---------------------------------------+
|   PACKING SLIP — LISTA                |
|   URL: /admin/packing-slips           |
|   Componente: PackingSlipList         |
|                                       |
|   Muestra: Todos los PS creados       |
|     - PS-2026-0001 (Pendiente)        |
|     - PS-2026-0002 (Despachado)       |
|     - PS-2026-0003 (Cancelado)        |
|                                       |
|   Acciones disponibles:               |
|     -> Ver detalle del PS             |
|     -> Eliminar PS borrador           |
+---------------------------------------+
        |
        v
+---------------------------------------+
|   PACKING SLIP — DETALLE             |
|   URL: /admin/packing-slips/{ps}     |
|   Componente: PackingSlipShow        |
|                                       |
|   - Ver items del PS con snapshots   |
|   - Cambiar estado (pending/shipped) |
|   - Editar lotes asignados           |
|   - Descargar PDF FPL-10             |
+---------------------------------------+
        |
        | status = 'shipped'
        v
  [Despacho completado]
  (Fase futura: generar Invoice FPL-12)
```

---

## 4. Diferencias en datos

### 4.1 Tablas consultadas por cada pantalla

| Concepto | WO Listos para PS | Packing Slip (lista) |
|---|---|---|
| Tabla principal | `lots` | `packing_slips` |
| Join clave | `lots LEFT JOIN packing_slip_items` (whereDoesntHave) | `packing_slips LEFT JOIN packing_slip_items` |
| Condicion de filtro | `ready_for_shipping = true AND no tiene PSItem` | Ninguna (todos los PS visibles) |
| Datos de parte | `work_orders -> purchase_orders -> parts` | A traves de `packing_slip_items -> lots -> work_orders -> parts` |

### 4.2 Campos mostrados por cada pantalla

**WO Listos para PS muestra (por lote):**
- Numero de lote (`lot_number`)
- Codigo de WO FPL-10 (`W0` + external_wo_number + lot_seq)
- Numero de parte
- Cantidad empacada final (`quantity_packed_final`)
- Tipo de cierre (complete_lot / new_lot / close_as_is)
- Fecha de cierre (ready_for_shipping_at)
- Advertencia si falta `external_wo_number`

**Packing Slip — Lista muestra (por PS):**
- Numero de PS (`ps_number`: PS-YYYY-NNNN)
- Estado (pending / shipped / cancelled)
- Numero de items (lotes incluidos)
- Usuario creador
- Fecha de creacion
- Fecha de despacho

### 4.3 Modelos de dominio involucrados

```
WO Listos para PS          |    Packing Slip
---------------------------|---------------------------
Lot (modelo central)       |    PackingSlip (modelo central)
WorkOrder                  |    PackingSlipItem
PurchaseOrder              |    Lot (referencia via item)
Part                       |    WorkOrder (referencia via lote)
                           |    User (creator, shipper)
```

---

## 5. Duplicidad o complementariedad

### 5.1 Existe duplicidad parcial: PackingSlipCreate vs ShippingQueue

Se detecta **una duplicidad real** entre:

- `ShippingQueue` (modal de creacion integrado en `/admin/shipping-queue`)
- `PackingSlipCreate` (pagina standalone en `/admin/packing-slips/create`)

Ambos hacen lo mismo: mostrar los lotes con `ready_for_shipping = true` y permitir crear un PS. Las diferencias tecnicas son:

| Aspecto | ShippingQueue (modal) | PackingSlipCreate (pagina) |
|---|---|---|
| Flujo de UI | Modal dentro de la cola | Pagina dedicada separada |
| Campo label_spec | Ingreso manual por lote | Muestra el de `parts.label_spec` (readonly) |
| Campo Date | No aparece en el modal | Si aparece como campo editable |
| Campo Notas | Si (textarea en modal) | Si (textarea en pagina) |
| Campo document_date | No (se asigna automaticamente) | Si (campo obligatorio) |
| Validacion WO externo | Si (bloquea checkbox) | Si (valida al guardar) |
| Destino tras crear | Mensaje en misma pagina, lotes salen de cola | Redirige a PackingSlipShow |
| Manejo de label_spec | Ingreso manual (D-06-02) | Lee de `parts.label_spec` (comportamiento diferente) |

**Conclusion sobre la duplicidad:** La existencia de dos caminos para crear PS genera inconsistencia: el campo `label_spec` se trata de forma diferente en cada uno. `PackingSlipCreate` lee automaticamente el `label_spec` de la tabla `parts`, mientras que `ShippingQueue` requiere ingreso manual. Esto puede resultar en PS creados con datos distintos dependiendo del camino usado.

### 5.2 Las pantallas principales NO son duplicadas

`ShippingQueue` y `PackingSlipList` no son redundantes entre si porque:
1. Muestran datos de modelos completamente distintos (`lots` vs `packing_slips`)
2. Tienen estados mutuamente excluyentes: la cola muestra lotes SIN PS, la lista muestra PS YA CREADOS
3. Sirven a momentos distintos del proceso

---

## 6. Oportunidades de mejora

### 6.1 Oportunidad ALTA: Unificar el flujo de creacion de PS

**Problema:** Existen dos rutas para crear un PS:
- Via `ShippingQueue` (modal integrado) — ruta recomendada por el diseno
- Via `PackingSlipCreate` (pagina standalone) — ruta heredada, inconsistente

**Recomendacion:** Elegir una de las dos y deprecar la otra.

El criterio de decision es el siguiente:

| Factor | Favorece ShippingQueue | Favorece PackingSlipCreate |
|---|---|---|
| Consistencia con el flujo | La cola es el punto de entrada natural | La pagina standalone puede accederse por error sin haber revisado la cola |
| Completitud de datos | Muestra el estado de bloqueo de cada lote claramente | No muestra advertencias de bloqueo tan claramente |
| Campos disponibles | Falta `document_date` en el modal | Tiene todos los campos FPL-10 visibles |
| label_spec | Ingreso manual (correcto segun D-06-02) | Lee de `parts.label_spec` (puede sobrescribir datos incorrectamente) |

**Recomendacion concreta:** Mantener `ShippingQueue` como el flujo oficial. Agregar al modal el campo `document_date` que actualmente falta. Redirigir desde `PackingSlipCreate` a `ShippingQueue` o eliminar la ruta `/admin/packing-slips/create` del menu de navegacion (dejandola solo como ruta tecnica de respaldo).

### 6.2 Oportunidad MEDIA: Agregar enlace directo desde la cola al PS recien creado

**Problema actual:** Al crear un PS desde `ShippingQueue`, el mensaje de exito dice "Packing Slip PS-2026-0001 creado exitosamente en estado Borrador" pero no ofrece un enlace para ir directamente al PS. El usuario debe navegar a `/admin/packing-slips` y buscarlo manualmente.

**Recomendacion:** Incluir en el mensaje de exito un enlace directo: "Ver Packing Slip PS-2026-0001" que redirija a `PackingSlipShow`.

### 6.3 Oportunidad MEDIA: Agregar contadores en el sidebar/menu

**Situacion actual:** El usuario no sabe cuantos lotes estan esperando en la cola sin ver la pantalla.

**Recomendacion:** Agregar un badge numerico en la entrada del menu "WO Listos para PS" que muestre la cantidad de lotes en cola (`Lot::readyForShipping()->count()`). Esto permite al equipo de Shipping detectar trabajo pendiente sin necesitar entrar a la pantalla.

### 6.4 Oportunidad BAJA: Unificar manejo de label_spec

**Problema:** `ShippingQueue` maneja `label_spec` como campo de texto libre manual, mientras que `PackingSlipCreate` lo lee desde `parts.label_spec`. El documento `08_label_spec_en_parts_analisis_impacto.md` ya contempla la migracion de `label_spec` a la tabla `parts` como mejora futura.

**Recomendacion:** Cuando se implemente el campo `parts.label_spec` de forma definitiva, actualizar el modal de `ShippingQueue` para que pre-llene el campo con el valor de `parts.label_spec` (si existe), permitiendo sobreescritura manual. Esto unifica el comportamiento de ambos formularios.

---

## 7. Tabla comparativa completa

| Dimension | WO Listos para PS | Packing Slip (lista/detalle) |
|---|---|---|
| **Proposito** | Cola de lotes esperando ser despachados | Registro de documentos de despacho ya creados |
| **Pregunta que responde** | "Que lotes estan listos para despachar hoy?" | "Que PS hemos generado? En que estado estan?" |
| **URL** | `/admin/shipping-queue` | `/admin/packing-slips` y `/admin/packing-slips/{ps}` |
| **Ruta nombrada** | `admin.shipping.queue` | `admin.packing-slips.index` y `.show` |
| **Componente PHP** | `ShippingQueue` | `PackingSlipList`, `PackingSlipShow` |
| **Datos fuente** | Tabla `lots` (filtrada) | Tabla `packing_slips` |
| **Estado del dato** | Lotes SIN PS | PS YA CREADOS |
| **Accion principal** | CREAR un nuevo PS | VER / GESTIONAR / DESPACHAR un PS existente |
| **Rol de usuario** | Actor activo: elige lotes y crea el PS | Actor activo: gestiona el ciclo de vida del PS |
| **Ciclo de vida** | Entrada al flujo PS | Gestion post-creacion del PS |
| **Permisos** | Admin + Shipping (crear); Empaque (solo lectura) | Admin + Shipping + Empaque |
| **Relacion con lotes** | Muestra lotes sin PS (N lotes -> 0 PS) | Muestra PS con sus lotes (1 PS -> N lotes) |
| **Acceso al PDF FPL-10** | No | Si (via PackingSlipShow) |
| **Posicion en el proceso** | Paso previo (trigger de creacion) | Paso posterior (gestion del documento) |

---

## 8. Conclusion

### 8.1 Conclusion principal

**Son pantallas distintas y complementarias.** Ninguna puede reemplazar a la otra porque cubren momentos distintos del mismo proceso:

```
ANTES (en cola)              DURANTE (creacion)           DESPUES (gestion)
WO Listos para PS     --->   Modal de creacion    --->   Packing Slip (lista/detalle)
/admin/shipping-queue         (dentro de la cola)         /admin/packing-slips
```

La relacion entre ambas es de **pipeline**: los datos fluyen de una pantalla a la otra. Un lote que aparece en la cola de Shipping desaparece de ella en el momento exacto en que se crea el `PackingSlipItem` correspondiente, y a partir de ese momento el lote solo es visible dentro del PS en la pantalla de Packing Slips.

### 8.2 Lo que SI es duplicado

La pantalla `/admin/packing-slips/create` (`PackingSlipCreate`) es la unica funcionalidad que se superpone con la `ShippingQueue`. Ambas permiten crear un PS, pero con diferencias en el manejo de datos. Esta es la unica duplicidad arquitectural identificada.

### 8.3 Respuesta directa a las preguntas planteadas

| Pregunta | Respuesta |
|---|---|
| Son la misma URL? | No. Son `/admin/shipping-queue` vs `/admin/packing-slips` |
| Son el mismo componente? | No. Son `ShippingQueue` vs `PackingSlipList` |
| Usan los mismos datos? | No. Una trabaja con `lots`, la otra con `packing_slips` |
| Cual va primero? | La cola siempre va primero. Es el punto de entrada obligatorio. |
| Hay funcionalidad duplicada? | Si, pero solo la pantalla `/packing-slips/create` duplica parte de la funcionalidad de la cola. |
| Se puede eliminar alguna? | Solo consideraria deprecar `/packing-slips/create` a favor del modal en la cola. Las dos pantallas principales son indispensables. |

---

## 9. Plan de accion recomendado

### Prioridad 1 (corto plazo): Resolver la inconsistencia de label_spec

El campo `label_spec` se comporta diferente en `ShippingQueue` (manual) vs `PackingSlipCreate` (de `parts`). Hasta que se defina la implementacion definitiva de `parts.label_spec` (ver `08_label_spec_en_parts_analisis_impacto.md`), documentar claramente en el menu cual es el flujo oficial.

**Accion inmediata:** Designar `ShippingQueue` como el unico flujo oficial para crear PS. Agregar una nota informativa en `/packing-slips/create` que indique que el flujo recomendado es via la cola de Shipping.

### Prioridad 2 (mediano plazo): Mejorar el modal de creacion en ShippingQueue

Agregar el campo `document_date` al modal de creacion de PS en `ShippingQueue` para que el formulario quede a paridad con `PackingSlipCreate`. Agregar enlace directo al PS recien creado en el mensaje de exito.

### Prioridad 3 (mediano plazo): Badge numerico en el menu

Implementar un contador en tiempo real en el enlace del menu "WO Listos para PS" para que el equipo de Shipping sepa cuantos lotes estan esperando sin entrar a la pantalla.

### Prioridad 4 (largo plazo): Evaluar deprecar PackingSlipCreate

Una vez que el flujo via `ShippingQueue` sea completo (con `document_date`), evaluar si `/admin/packing-slips/create` debe ser removido del menu de navegacion o eliminado por completo.

---

## 10. Referencias de archivos analizados

| Archivo | Relevancia |
|---|---|
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | Componente principal de la cola |
| `resources/views/livewire/admin/shipping/shipping-queue.blade.php` | Vista de la cola |
| `app/Livewire/Admin/PackingSlips/PackingSlipList.php` | Lista de PS creados |
| `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php` | Creacion standalone de PS (duplicidad) |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | Detalle y gestion del PS |
| `resources/views/livewire/admin/packing-slips/packing-slip-list.blade.php` | Vista de lista de PS |
| `resources/views/livewire/admin/packing-slips/packing-slip-create.blade.php` | Vista de creacion standalone |
| `app/Models/PackingSlip.php` | Modelo PS con estados y ciclo de vida |
| `app/Models/PackingSlipItem.php` | Modelo del item (vinculo PS-Lote) |
| `app/Models/Lot.php` | Modelo del Lote con scopeReadyForShipping |
| `app/Models/WorkOrder.php` | Metodos `buildWoCode` y `hasExternalWoNumber` |
| `routes/admin.php` | Definicion de rutas (lineas 215-226) |
| `01_shipping_list_analysis.md` | Origen del modulo PS (documento seminal) |
| `05_decisiones_confirmadas_y_plan_implementacion.md` | Plan de implementacion con decisiones D-01 a D-12 |
| `06_impacto_respuestas_pendientes_y_ajustes.md` | Decisiones D-06-01 a D-06-05 |
| `10_lote_sin_wo_retorno_empaque_analisis.md` | Mecanismo de retorno a Empaque |

---

*Documento generado como parte de la serie de analisis tecnico del modulo Packing Slip (FPL-10) del sistema FlexCon Tracker.*
