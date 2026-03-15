# Analisis Tecnico: Lote Sin WO Externo — Mecanismo de Retorno a Empaque

**Fecha:** 2026-03-13
**Elaborado por:** Arquitecto de Software - FlexCon Tracker
**Version:** 1.0
**Proposito:** Diagnosticar el escenario en que un lote llega a la cola "WO Listos para PS" sin `external_wo_number` en su Work Order, documentar el impacto actual (lote bloqueado, incapaz de avanzar al Packing Slip) y proponer un mecanismo de retorno controlado a Empaque para que el equipo resuelva la condicion de bloqueo.

**Documentos previos:**
- `01_shipping_list_analysis.md` — Estructura del Packing Slip FPL-10
- `02_invoice_analysis.md` — Invoice FPL-12 y relacion 1:1 con el PS
- `03_field_mapping_lista_envio_to_packing_slip.md` — Mapeo de campos y reglas de negocio
- `04_empaque_to_shipping_list_transition.md` — Opciones de diseno y preguntas abiertas
- `05_decisiones_confirmadas_y_plan_implementacion.md` — Plan de implementacion Packing Slip v1.0
- `06_impacto_respuestas_pendientes_y_ajustes.md` — Ajustes al plan
- `07_fpl10_cumplimiento_vs_implementacion.md` — Cumplimiento FPL-10 vs codigo
- `08_label_spec_en_parts_analisis_impacto.md` — Agregar `label_spec` a tabla `parts`
- `09_fpl12_invoice_analisis_implementacion.md` — Plan de implementacion del Invoice

---

## 1. Diagnostico del Escenario

### 1.1 Como Ocurre la Condicion de Bloqueo

El campo `work_orders.external_wo_number` es el numero externo de siete digitos que S.E.I.P., Inc. asigna a cada Work Order del cliente y que se usa para construir el codigo de WO en el Packing Slip FPL-10 (formato `W0` + `external_wo_number` + `lot_seq_padded`).

Segun la decision D-06-05 (documentada en `05_decisiones_confirmadas_y_plan_implementacion.md`) y la migracion `2026_03_08_100001_add_external_wo_number_to_work_orders_table.php`:

> Solo se pobla para WOs nuevos creados a partir de esta fase. Los WOs historicos quedan con `external_wo_number = NULL`.

Esto significa que existen dos vectores legitimos por los que un lote puede llegar a la cola sin WO externo:

**Vector A — WO historica (pre-implementacion):**
La WO fue creada antes de que el campo `external_wo_number` existiera en la base de datos. Cuando ese lote pasa por Empaque y se cierra, el `LotPackagingObserver` activa `ready_for_shipping = true` sin verificar si la WO tiene numero externo. El lote aterriza en la cola con la advertencia "Sin WO externo".

**Vector B — WO nueva sin numero externo asignado:**
La WO fue creada despues de la migracion pero el usuario no lleno el campo `external_wo_number` al momento de crearla (el campo es `nullable`). Operativamente esto puede ocurrir si la comunicacion con S.E.I.P., Inc. no ha llegado todavia cuando se crea la WO internamente.

### 1.2 Flujo Tecnico Que Activa el Bloqueo

```
[Empaque cierra lote]
  closure_decision = 'complete_lot' | 'new_lot' | 'close_as_is'
        |
        v
[LotPackagingObserver.updated()]          <- app/Observers/LotPackagingObserver.php
  - Verifica: closure_decision cambio a valor valido
  - Verifica: ready_for_shipping == false (idempotencia)
  - NO verifica: workOrder->hasExternalWoNumber()
  - Ejecuta: lot.updateQuietly({
        ready_for_shipping: true,
        ready_for_shipping_at: now(),
        closed_by_type: closure_decision,
        quantity_packed_final: SUM(packaging_records.packed_pieces)
    })
        |
        v
[Lote aparece en ShippingQueue]           <- app/Livewire/Admin/Shipping/ShippingQueue.php
  scopeReadyForShipping():
    WHERE ready_for_shipping = true
    AND NOT EXISTS (SELECT * FROM packing_slip_items WHERE lot_id = lots.id)
        |
        v
[Vista muestra badge naranja]             <- resources/views/livewire/admin/shipping/shipping-queue.blade.php
  "Sin WO externo — no puede incluirse en PS"
  checkbox deshabilitado (@disabled(!$hasExternalWo))
```

### 1.3 Por Que el Observer No Valida el WO Externo

El `LotPackagingObserver` fue disenado con una sola responsabilidad: detectar el cierre de Empaque y activar la flag de shipping. La validacion del WO externo es una responsabilidad de la capa de Shipping (componente `ShippingQueue` y metodo `createPackingSlip`). Este diseno es correcto en terminos de separacion de responsabilidades; el problema no es arquitectural sino operativo: no existe ningun mecanismo para desbloquear el lote una vez que esta en esa situacion.

---

## 2. Impacto Actual

### 2.1 Estado del Lote Bloqueado

Cuando un lote llega a "WO Listos para PS" sin WO externo, la situacion actual es:

| Campo | Valor | Consecuencia |
|---|---|---|
| `lots.ready_for_shipping` | `true` | El lote aparece en la cola de Shipping |
| `lots.closed_by_type` | `complete_lot` / `new_lot` / `close_as_is` | El cierre de Empaque es irreversible por diseno |
| `work_orders.external_wo_number` | `NULL` | El lote no puede incluirse en ningun PS |
| `packing_slip_items.lot_id` | No existe | El lote no sale de la cola |

### 2.2 Consecuencias Operativas

1. **El lote queda atascado indefinidamente.** No puede avanzar al PS porque `hasExternalWoNumber()` retorna `false`. Tampoco puede retroceder porque no existe ningun mecanismo de retorno.

2. **La cola de Shipping se contamina.** Cada vez que Shipping revisa la cola ve lotes que no puede procesar, generando ruido operativo y confusion.

3. **El equipo de Empaque pierde visibilidad.** Despues de cerrar el lote, Empaque ya no lo ve en su modulo. No sabe que existe un problema pendiente de resolucion.

4. **La unica salida actual es manual a nivel de base de datos.** Un administrador tendria que editar directamente `work_orders.external_wo_number` o resetear `lots.ready_for_shipping = false`, ambas acciones sin interfaz de usuario y sin auditoria.

### 2.3 Frecuencia Esperada del Escenario

Dado que el campo `external_wo_number` es nuevo (migracion de 2026-03-08) y los WOs historicos lo tienen en NULL, se espera que este escenario ocurra con **frecuencia alta en el corto plazo** (mientras se normaliza el proceso de llenado del campo al crear WOs). A mediano plazo la frecuencia deberia reducirse, pero no llegar a cero porque siempre puede haber demora en recibir el numero externo de S.E.I.P., Inc.

---

## 3. Flujo Propuesto de Retorno a Empaque

### 3.1 Concepto de Diseno

La solucion propuesta introduce un **mecanismo de devolucion controlado** que:

1. Solo esta disponible para lotes que esten en la cola de Shipping **sin WO externo** (la condicion exacta del bloqueo).
2. Permite a un usuario autorizado (Admin o Shipping) **revertir el estado del lote** para que reaparezca en el modulo de Empaque.
3. Deja **trazabilidad completa** del evento de retorno (quien, cuando, por que).
4. **No destruye datos** de Empaque: los `packaging_records` y la `closure_decision` anterior se conservan.

### 3.2 Flujo Operativo Propuesto

```
[ShippingQueue — lote con badge "Sin WO externo"]
  Usuario hace clic en "Devolver a Empaque"
        |
        v
[Modal de confirmacion]
  Muestra: nombre del lote, WO interna, numero de parte
  Campo obligatorio: "Motivo de devolucion" (texto libre, max 255)
  Boton: "Confirmar devolucion"
        |
        v
[ShippingQueue.returnLotToPackaging(lotId, reason)]
  Validaciones:
    - El lote existe y tiene ready_for_shipping = true
    - El lote NO tiene packingSlipItem (no esta en un PS)
    - El WO del lote NO tiene external_wo_number (condicion del bloqueo)
    - El motivo de devolucion no esta vacio
  Transaccion:
    - lot.update({
          ready_for_shipping: false,
          ready_for_shipping_at: null,
          closed_by_type: null,
          closure_decision: null,
          closure_decided_by: null,
          closure_decided_at: null,
          returned_to_packaging_at: now(),
          returned_to_packaging_by: Auth::id(),
          returned_to_packaging_reason: reason
      })
    - AuditTrail.create({...})
        |
        v
[El lote desaparece de ShippingQueue]
  scopeReadyForShipping() ya no lo incluye (ready_for_shipping = false)
        |
        v
[El lote reaparece en Empaque]
  PackagingDashboard ve el lote porque:
    - viajero_received = true (sigue siendo true)
    - closure_decision = null (condicion que PackagingDashboard usa para "pendiente de decision")
    - El equipo de Empaque puede ver el motivo de devolucion y tomar accion
        |
        v
[Empaque toma una de dos acciones]
  Opcion A: Coordina con administracion para que llenen external_wo_number en la WO
            -> Admin edita la WO, llena external_wo_number
            -> Empaque vuelve a cerrar el lote (nueva closure_decision)
            -> LotPackagingObserver activa ready_for_shipping = true nuevamente
            -> Lote reaparece en ShippingQueue, ahora con WO externo
  Opcion B: El lote tiene un problema mayor (no debio haberse cerrado)
            -> Empaque toma las acciones correctivas que correspondan
```

### 3.3 Diagrama de Estado del Lote

```
[Empaque activo]
  closure_decision = NULL
  ready_for_shipping = false
        |
        | LotPackagingObserver (closure_decision se establece)
        v
[En cola de Shipping]
  closure_decision = 'complete_lot' | 'new_lot' | 'close_as_is'
  ready_for_shipping = true
        |
        |--- SI workOrder.hasExternalWoNumber() ----> [Incluible en PS] ---> [En PS]
        |
        |--- SI NOT hasExternalWoNumber() ----------> [BLOQUEADO]
                                                            |
                                                            | returnLotToPackaging() [NUEVO]
                                                            v
                                                      [Empaque activo - retornado]
                                                        closure_decision = NULL
                                                        ready_for_shipping = false
                                                        returned_to_packaging_at = timestamp
                                                        returned_to_packaging_by = user_id
                                                        returned_to_packaging_reason = texto
                                                            |
                                                            | (despues de corregir WO)
                                                            | LotPackagingObserver
                                                            v
                                                      [En cola de Shipping - desbloqueado]
                                                        ready_for_shipping = true
                                                        workOrder.external_wo_number != NULL
```

---

## 4. Analisis de Impacto Tecnico

### 4.1 Base de Datos — Nuevos Campos en `lots`

Se requiere una nueva migracion para agregar tres campos de trazabilidad del retorno:

**Archivo sugerido:** `database/migrations/2026_03_XX_XXXXXX_add_return_to_packaging_fields_to_lots_table.php`

| Campo nuevo | Tipo | Restriccion | Proposito |
|---|---|---|---|
| `returned_to_packaging_at` | `timestamp` | nullable | Momento en que el lote fue devuelto a Empaque |
| `returned_to_packaging_by` | `unsignedBigInteger` (FK -> users) | nullable | Usuario que ejecuto el retorno |
| `returned_to_packaging_reason` | `string(255)` | nullable | Motivo obligatorio al ejecutar el retorno |

Estos campos son complementarios y no reemplazan a `ready_for_shipping_at` ni a `closed_by_type`. Sirven como registro del evento de retorno independiente del estado actual del lote.

**Nota sobre re-apertura:** Si el lote es retornado y luego Empaque lo vuelve a cerrar, los campos `returned_to_packaging_*` quedan con los valores del ultimo retorno. Si se necesita historial de multiples retornos, se requeriria una tabla `lot_return_events` (ver seccion 7.2 — edge cases).

**Indice sugerido:** No se requiere indice adicional. El acceso a estos campos sera puntual (vista de detalle del lote), no en queries de lista.

### 4.2 Modelo `Lot` — Cambios Requeridos

**Archivo:** `app/Models/Lot.php`

**a) Agregar campos a `$fillable`:**
```
'returned_to_packaging_at',
'returned_to_packaging_by',
'returned_to_packaging_reason',
```

**b) Agregar cast para el timestamp:**
```
'returned_to_packaging_at' => 'datetime',
```

**c) Agregar relacion con el usuario que ejecuto el retorno:**
```php
public function returnedToPackagingByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'returned_to_packaging_by');
}
```

**d) Agregar metodo de consulta de estado:**
```php
public function wasReturnedToPackaging(): bool
{
    return !is_null($this->returned_to_packaging_at);
}
```

**e) Considerar actualizacion del `LotPackagingObserver` para soporte de re-cierre:**
Cuando el lote es retornado y luego vuelto a cerrar, el Observer tiene una guarda de idempotencia:
```php
if ($lot->ready_for_shipping === true) {
    // salta la logica
    return;
}
```
Dado que el retorno pone `ready_for_shipping = false`, esta guarda no bloquea el re-cierre. El Observer funcionara correctamente en el segundo ciclo sin modificaciones.

### 4.3 Componente `ShippingQueue` — Cambios Requeridos

**Archivo:** `app/Livewire/Admin/Shipping/ShippingQueue.php`

**a) Nueva propiedad para el modal de retorno:**
```php
public bool $showReturnModal = false;
public ?int $returningLotId = null;
public string $returnReason = '';
```

**b) Nuevo metodo `openReturnModal(int $lotId)`:**
Valida que el lote existe, esta en la cola, y no tiene WO externo. Abre el modal.

**c) Nuevo metodo `confirmReturnLot()`:**
Ejecuta la devolucion dentro de una transaccion de base de datos:
- Valida nuevamente las condiciones (defensivo).
- Ejecuta `lot->update()` con los campos de retorno y el reset de shipping.
- Registra en `AuditTrail`.
- Emite mensaje de exito.

**d) Nuevo metodo `cancelReturnLot()`:**
Cierra el modal y limpia el estado.

**e) Validacion del motivo de retorno:**
El campo `returnReason` debe ser obligatorio (no vacio) y con longitud maxima de 255 caracteres. Esto garantiza que siempre quede documentado por que se devolvio el lote.

### 4.4 Vista `shipping-queue.blade.php` — Cambios Requeridos

**Archivo:** `resources/views/livewire/admin/shipping/shipping-queue.blade.php`

**a) Agregar boton "Devolver a Empaque" en la fila del lote bloqueado:**
El boton debe aparecer unicamente cuando `!$hasExternalWo`. Debe estar condicionado al permiso del usuario (`$canCreatePs` o un permiso especifico). Ejemplo de ubicacion: en la celda de la columna "Work Order", debajo del badge naranja "Sin WO externo".

**b) Nuevo modal de confirmacion de retorno:**
Similar en estructura al modal de creacion de PS. Debe mostrar:
- Informacion del lote (numero, WO interna, parte, cantidad empacada)
- Campo de texto "Motivo de devolucion" (obligatorio)
- Botones "Cancelar" y "Confirmar devolucion"

**c) Mensaje de exito tras el retorno:**
Usar la propiedad `$successMessage` existente para informar al usuario que el lote fue devuelto correctamente.

### 4.5 `PackagingDashboard` — Visibilidad del Lote Retornado

**Archivo:** `app/Livewire/Admin/Packaging/PackagingDashboard.php`

El `PackagingDashboard` actualmente cuenta los lotes pendientes de decision con:
```php
$lotsPendingDecision = Lot::where('viajero_received', true)
    ->whereNull('closure_decision')
    ->count();
```

Un lote retornado tendra `viajero_received = true` y `closure_decision = NULL` (se limpia en el retorno), por lo que **reaparecera automaticamente en este contador sin modificaciones**.

Sin embargo, para que Empaque entienda el contexto del retorno (por que fue devuelto), se recomienda agregar en la vista del Dashboard o en la vista de detalle del lote la informacion de `returned_to_packaging_reason` y `returned_to_packaging_at`. Esto es un cambio de UI, no de logica.

### 4.6 `LotPackagingObserver` — Impacto

**Archivo:** `app/Observers/LotPackagingObserver.php`

No se requieren modificaciones. La guarda de idempotencia existente:
```php
if ($lot->ready_for_shipping === true) { return; }
```
garantiza que si el Observer se dispara sobre un lote ya marcado, no hace nada. El retorno pone `ready_for_shipping = false`, lo que permite que el Observer actue correctamente en el segundo ciclo de cierre.

El unico riesgo es si el retorno no limpia `closure_decision` correctamente (ver seccion 7.1).

### 4.7 Auditoria

El sistema cuenta con el modelo `AuditTrail` (polimorfismo via `auditable_type` / `auditable_id`). Cada evento de retorno debe registrarse con:

```php
AuditTrail::create([
    'user_id'        => Auth::id(),
    'auditable_type' => Lot::class,
    'auditable_id'   => $lot->id,
    'action'         => 'returned_to_packaging',
    'old_values'     => [
        'ready_for_shipping'    => true,
        'closed_by_type'        => $lot->closed_by_type,
        'closure_decision'      => $lot->closure_decision,
    ],
    'new_values'     => [
        'ready_for_shipping'            => false,
        'closed_by_type'                => null,
        'closure_decision'              => null,
        'returned_to_packaging_reason'  => $reason,
    ],
    'ip_address'     => request()->ip(),
    'user_agent'     => request()->userAgent(),
    'created_at'     => now(),
]);
```

---

## 5. Decisiones de Diseno Abiertas

Antes de implementar, el equipo debe responder las siguientes preguntas:

### P-10-01: Quien puede ejecutar el retorno

**Pregunta:** El boton "Devolver a Empaque" — quienes deben poder verlo y ejecutarlo?

**Opciones:**
- Solo Admins
- Admins y usuarios con rol Shipping
- Admins, Shipping y Empaque

**Impacto:** Define la condicion de visibilidad del boton en la vista y la validacion de permisos en el metodo del componente.

**Recomendacion del arquitecto:** Restringir a Admin y Shipping. El equipo de Empaque no deberia poder devolverse lotes a si mismo sin supervision.

---

### P-10-02: Se debe limpiar `closure_decision` al retornar

**Pregunta:** Al retornar el lote a Empaque, se debe limpiar el campo `closure_decision` (ademas de `ready_for_shipping`)?

**Contexto:** El `LotPackagingObserver` actua cuando `closure_decision` cambia de NULL a un valor valido. Si `closure_decision` queda con el valor anterior (por ejemplo `complete_lot`) y luego el lote es "vuelto a cerrar" con el mismo valor, el Observer no lo detectara como un cambio (`wasChanged()` retorna false porque el valor es identico).

**Opciones:**
- Limpiar `closure_decision = null` en el retorno (recomendado para garantizar que el Observer dispare en el segundo ciclo)
- No limpiar `closure_decision` y agregar logica adicional al Observer para detectar el re-cierre

**Impacto arquitectural:** Si se elige limpiar, el campo `closed_by_type` tambien debe limpiarse para consistencia. Ambos son valores derivados del cierre.

**Recomendacion del arquitecto:** Limpiar ambos campos (`closure_decision` y `closed_by_type`) al retornar. Esto garantiza que el Observer funcione correctamente en el segundo ciclo sin modificaciones y mantiene el principio de que `ready_for_shipping = false` implica que el lote no ha completado el proceso de cierre.

---

### P-10-03: Que pasa con los packaging_records al retornar

**Pregunta:** Al devolver el lote a Empaque, los `packaging_records` existentes deben mantenerse o eliminarse?

**Contexto:** Los `packaging_records` representan el trabajo fisico de empaque ya realizado (piezas empacadas, sobrantes, etc.). Eliminarlos implicaria que Empaque tendria que re-registrar todo ese trabajo.

**Opciones:**
- Mantener los `packaging_records` (recomendado): Empaque ve el trabajo previo y puede agregar registros adicionales o corregir.
- Eliminar los `packaging_records`: Empaque comienza desde cero, lo que puede ser confuso si las piezas ya estan fisicamente empacadas.

**Recomendacion del arquitecto:** Mantener todos los `packaging_records`. El retorno es un evento administrativo (falta el numero externo de WO), no un evento de rechazo de calidad del empaque.

---

### P-10-04: Comunicacion a Empaque

**Pregunta:** Ademas de que el lote reaparezca en el dashboard, debe existir algun mecanismo de notificacion activa al equipo de Empaque?

**Opciones:**
- Solo visibilidad en dashboard (el lote reaparece en el contador de `$lotsPendingDecision`)
- Notificacion en-app (Laravel Notifications + Livewire events)
- Notificacion por email

**Impacto:** La notificacion activa es una funcionalidad adicional que agrega complejidad. Para la primera version, la visibilidad en dashboard puede ser suficiente si el equipo revisa el dashboard regularmente.

**Recomendacion del arquitecto:** Para la fase 1 de esta funcionalidad, implementar solo la visibilidad en dashboard con un indicador visual claro de que el lote fue "retornado" (badge o color diferente). Las notificaciones activas pueden agregarse en una fase posterior.

---

### P-10-05: Limite de retornos

**Pregunta:** Debe existir un limite en cuantas veces un lote puede ser retornado a Empaque?

**Opciones:**
- Sin limite (cualquier usuario autorizado puede retornar cuantas veces sea necesario)
- Limite de 1 retorno (despues del primer retorno, si vuelve a quedar bloqueado, requiere intervencion de Admin)
- Sin limite pero con alerta visible si el lote tiene mas de N retornos

**Impacto:** Un limite previene abusos o errores en cascada. La ausencia de limite es mas simple de implementar.

**Recomendacion del arquitecto:** Sin limite en la primera version, pero registrando cada retorno en AuditTrail. Si en la practica se detecta que hay lotes con multiples retornos, se puede agregar la restriccion en una iteracion posterior.

---

## 6. Plan de Implementacion

Los pasos estan ordenados de manera que cada uno puede revisarse independientemente antes de continuar con el siguiente.

### Paso 1: Migracion de base de datos

**Archivo a crear:** `database/migrations/YYYY_MM_DD_HHMMSS_add_return_to_packaging_fields_to_lots_table.php`

Agregar a la tabla `lots`:
- `returned_to_packaging_at` (timestamp, nullable)
- `returned_to_packaging_by` (unsignedBigInteger, nullable, FK -> users)
- `returned_to_packaging_reason` (string 255, nullable)

No agregar indices: el acceso a estos campos es puntual.

Ejecutar: `php artisan migrate`

---

### Paso 2: Actualizar el modelo `Lot`

**Archivo:** `app/Models/Lot.php`

1. Agregar los tres campos nuevos a `$fillable`.
2. Agregar `'returned_to_packaging_at' => 'datetime'` a `$casts`.
3. Agregar relacion `returnedToPackagingByUser(): BelongsTo`.
4. Agregar metodo auxiliar `wasReturnedToPackaging(): bool`.

---

### Paso 3: Agregar logica al componente `ShippingQueue`

**Archivo:** `app/Livewire/Admin/Shipping/ShippingQueue.php`

1. Declarar propiedades: `$showReturnModal`, `$returningLotId`, `$returnReason`.
2. Implementar `openReturnModal(int $lotId)`:
   - Cargar el lote con `workOrder`.
   - Validar que `ready_for_shipping = true`.
   - Validar que `!isInPackingSlip()`.
   - Validar que `!workOrder->hasExternalWoNumber()`.
   - Asignar `$this->returningLotId = $lotId` y abrir modal.
3. Implementar `confirmReturnLot()`:
   - Validar `$returnReason` no vacio, max 255 chars.
   - Dentro de `DB::transaction()`:
     - Recargar el lote con bloqueo (`lockForUpdate()`).
     - Re-validar condiciones.
     - Ejecutar `$lot->update([...])` con los campos de retorno y el reset de shipping/closure.
     - Crear entrada en `AuditTrail`.
   - Cerrar modal y emitir `$successMessage`.
4. Implementar `cancelReturnLot()`.

---

### Paso 4: Actualizar la vista `shipping-queue.blade.php`

**Archivo:** `resources/views/livewire/admin/shipping/shipping-queue.blade.php`

1. En la celda de la columna "Work Order", para lotes sin WO externo, agregar debajo del badge naranja:
   ```html
   <button wire:click="openReturnModal({{ $lot->id }})">
       Devolver a Empaque
   </button>
   ```
   El boton debe estar condicionado a `$canCreatePs` (o el permiso que se defina en P-10-01).

2. Agregar el modal de retorno al final del componente, con estructura similar al modal de creacion de PS:
   - Header: "Devolver lote a Empaque"
   - Cuerpo: informacion del lote, campo textarea "Motivo de devolucion" (obligatorio)
   - Footer: botones "Cancelar" y "Confirmar devolucion"

---

### Paso 5: Mejorar visibilidad en `PackagingDashboard`

**Archivo:** `resources/views/livewire/admin/packaging/packaging-dashboard.blade.php`

En la seccion donde se muestran los lotes pendientes de decision o en progreso, agregar un indicador visual para lotes con `returned_to_packaging_at != null`:

- Badge o etiqueta: "Retornado desde Shipping"
- Mostrar `returned_to_packaging_reason` como tooltip o texto secundario
- Mostrar `returned_to_packaging_at` formateado

El componente PHP `PackagingDashboard.php` no requiere cambios de logica, solo la vista necesita renderizar los campos nuevos.

---

### Paso 6: Pruebas

1. **Test unitario del modelo:** Verificar que `wasReturnedToPackaging()` retorna el valor correcto segun el estado del campo.

2. **Test funcional del flujo completo:**
   - Crear WO sin `external_wo_number`.
   - Cerrar el lote de Empaque.
   - Verificar que el lote aparece en ShippingQueue con badge naranja.
   - Verificar que el checkbox esta deshabilitado.
   - Ejecutar el retorno via `returnLotToPackaging()`.
   - Verificar que el lote desaparece de ShippingQueue.
   - Verificar que el lote reaparece en PackagingDashboard.
   - Verificar que AuditTrail tiene el registro correcto.
   - Agregar `external_wo_number` a la WO.
   - Cerrar el lote nuevamente.
   - Verificar que el lote reaparece en ShippingQueue con WO externo y checkbox habilitado.

3. **Test de guardia:** Intentar retornar un lote que ya esta en un PS. Verificar que el metodo rechaza la operacion.

4. **Test de idempotencia del Observer:** Verificar que el Observer dispara correctamente en el segundo cierre del lote (despues del retorno).

---

## 7. Riesgos y Consideraciones

### 7.1 Riesgo Critico: Inconsistencia si no se limpia `closure_decision`

Si al retornar el lote no se limpia el campo `closure_decision`, el `LotPackagingObserver` no disparara en el segundo cierre cuando Empaque vuelva a establecer el mismo valor de `closure_decision` (porque `wasChanged('closure_decision')` retornara `false`).

**Mitigacion:** Limpiar explicitamente `closure_decision = null` y `closed_by_type = null` como parte de la transaccion de retorno. Esto es un requisito de implementacion, no opcional.

### 7.2 Edge Case: Multiples Retornos

Si un lote es retornado multiples veces (por ejemplo, se corrige el WO, se vuelve a cerrar, pero habia otro error), los campos `returned_to_packaging_*` solo conservan el **ultimo** retorno. El historial completo existe solo en `AuditTrail`.

Si el equipo necesita acceder facilmente a todos los eventos de retorno desde la UI, se requeriria una tabla adicional `lot_return_events`:

```
lot_return_events
  id
  lot_id (FK -> lots)
  returned_by (FK -> users)
  returned_at (timestamp)
  reason (string 255)
```

Esta tabla no es necesaria para la primera version si AuditTrail cubre la necesidad de auditoria.

### 7.3 Edge Case: Lote Retornado y Cancelado

Si un lote es retornado a Empaque y luego cancelado (status = 'cancelled') por alguna razon operativa, los campos `returned_to_packaging_*` quedaran con valores aunque el lote este cancelado. Esto no es un error, pero la UI debe manejar este caso para no confundir al usuario.

### 7.4 Race Condition: Dos Usuarios en ShippingQueue Simultaneamente

Si dos usuarios de Shipping intentan retornar el mismo lote al mismo tiempo, el segundo usuario puede encontrar que el lote ya no cumple las condiciones (ya fue retornado). La implementacion debe usar `DB::transaction()` con `lockForUpdate()` en la recarga del lote para prevenir esta situacion.

### 7.5 Impacto en `quantity_packed_final`

El campo `quantity_packed_final` es un snapshot calculado por el Observer al momento del cierre. Al retornar el lote, este campo se puede mantener o limpiar:

- **Mantener:** El valor refleja el trabajo fisico de empaque que ya se hizo.
- **Limpiar:** El campo quedara desactualizado si Empaque agrega o quita registros durante el periodo de retorno.

**Recomendacion:** Limpiar `quantity_packed_final = null` al retornar. El Observer lo recalculara correctamente en el proximo cierre sumando los `packaging_records` vigentes al momento del segundo cierre.

### 7.6 Comunicacion al Equipo de Empaque

El mayor riesgo operativo (no tecnico) es que el equipo de Empaque no note que un lote fue retornado. Si no revisan el dashboard con frecuencia, el lote podria quedar pendiente durante dias. La implementacion del mecanismo de retorno debe ir acompanada de un proceso operativo claro que defina quien es responsable de revisar el dashboard de Empaque diariamente.

---

## 8. Resumen de Archivos Afectados

| Archivo | Tipo de Cambio | Descripcion |
|---|---|---|
| `database/migrations/YYYY_..._add_return_to_packaging_fields_to_lots_table.php` | NUEVO | Tres campos de trazabilidad del retorno |
| `app/Models/Lot.php` | MODIFICAR | `$fillable`, `$casts`, relacion, metodo auxiliar |
| `app/Livewire/Admin/Shipping/ShippingQueue.php` | MODIFICAR | Tres propiedades + cuatro metodos nuevos |
| `resources/views/livewire/admin/shipping/shipping-queue.blade.php` | MODIFICAR | Boton de retorno + modal de confirmacion |
| `resources/views/livewire/admin/packaging/packaging-dashboard.blade.php` | MODIFICAR | Indicador visual de lote retornado |
| `app/Observers/LotPackagingObserver.php` | SIN CAMBIOS | Funciona correctamente si se limpia `closure_decision` |
| `app/Livewire/Admin/Packaging/PackagingDashboard.php` | SIN CAMBIOS | El lote reaparece automaticamente en los contadores existentes |

---

## 9. Prerequisitos para Comenzar la Implementacion

Antes de escribir la primera linea de codigo, el equipo debe confirmar las respuestas a:

- **P-10-01:** Quien puede ejecutar el retorno (roles autorizados)
- **P-10-02:** Confirmacion de que se limpiara `closure_decision` (recomendado: si)
- **P-10-03:** Confirmacion de que los `packaging_records` se mantienen (recomendado: si)
- **P-10-04:** Si la primera version requiere solo visibilidad en dashboard o tambien notificaciones activas
- **P-10-05:** Si se requiere limite de retornos en la primera version

Con esas cinco respuestas, la implementacion puede iniciarse directamente en el Paso 1 del plan.
