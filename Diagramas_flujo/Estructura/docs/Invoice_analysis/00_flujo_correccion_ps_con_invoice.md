# Analisis Tecnico: Correccion de Packing Slip Despachado con Invoice Vinculado

**Fecha:** 2026-03-20
**Autor:** Arquitecto de Software - FlexCon Tracker
**Version:** 1.0
**Contexto del codigo explorado:** rama `main_jos`, commit `1ae7406`

---

## Resumen Ejecutivo

Un empleado del departamento de empaque puede cometer errores en un Packing Slip (PS) en cualquier
momento del ciclo de vida del documento: numero incorrecto, lotes equivocados, cantidades incorrectas,
etc. El problema critico surge cuando el error es descubierto **despues** de que el PS ya fue marcado
como "Despachado" (shipped), porque en ese momento el sistema **puede tener un Invoice vinculado** a
ese PS (generado manualmente mediante la accion "Crear Invoice" desde la vista del PS).

El sistema actual no tiene ningun mecanismo de correccion para este escenario. Todos los guards de
edicion en `PackingSlipShow.php` bloquean explicitamente modificaciones cuando el estado es `shipped`.
El `InvoiceDeleteService` permite eliminar el Invoice solo si esta en estado `draft` o `cancelled`,
pero no existe un flujo integrado que coordine la reversion del PS junto con la cancelacion del Invoice.

Este documento analiza el estado actual del codigo, mapea las restricciones tecnicas existentes, y
recomienda la estrategia de implementacion optima.

---

## 1. Mapa del Flujo de Estados Actual

### 1.1 Ciclo de vida del Packing Slip

```
                  [Creacion]
                      |
                      v
              +---------------+
              |     DRAFT     |  <-- Edicion libre: ps_number, lotes,
              +---------------+      document_date, notas
                      |
                      | updateStatus() en PackingSlipShow
                      v
              +---------------+
              |    PENDING    |  <-- Solo lectura. Lotes y fecha bloqueados.
              +---------------+      Aun no genera Invoice.
                      |
                      | updateStatus() en PackingSlipShow
                      v
              +---------------+
              |    SHIPPED    |  <-- Todo bloqueado. shipped_at + shipped_by
              +-------+-------+      registrados automaticamente.
                      |
                      | [accion manual: POST create-invoice]
                      v
              +---------------+
              |  INVOICE      |  <-- Invoice vinculado (invoice_id != null)
              |  VINCULADO    |
              +---------------+
                      |
                      | ??? <-- NO EXISTE flujo de correccion
                      v
              +---------------+
              |   CANCELLED   |  <-- Estado terminal. shipped_at/shipped_by
              +---------------+      NO se limpian en la cancelacion actual.
```

**Transiciones permitidas actualmente en `updateStatus()`:**
- `draft` -> `pending` -> `shipped` -> `cancelled`  (cualquier transicion es posible via el selector)
- El metodo `updateStatus()` NO valida si existe un Invoice vinculado antes de cambiar el estado.
- Si se cambia DESDE `shipped` a otro estado, se limpian `shipped_at` y `shipped_by`, pero NO se
  toca el Invoice vinculado.

### 1.2 Ciclo de vida del Invoice

```
              [Creacion desde PS shipped]
                      |
                      v
              +---------------+
              |     DRAFT     |  <-- Editable: lot_no, cargos fijos,
              +---------------+      lot_number por item, invoice_number.
                      |                 InvoiceDeleteService: SI se puede eliminar.
                      |
              +-------+-------+
              |               |
              v               v
      +------------+   +-------------+
      |   ISSUED   |   |  CANCELLED  |
      +------------+   +-------------+
      PDF disponible.  Solo desde draft.
      InvoiceDelete:   InvoiceDelete:
      NO se puede      SI se puede
      eliminar.        eliminar.
```

**Restriccion critica detectada:**
El `InvoiceDeleteService::delete()` lanza `RuntimeException` si el Invoice esta en estado `issued`.
No existe ninguna ruta de "re-apertura" (issued -> draft) para el Invoice.

### 1.3 Flujo bidireccional PS <-> Invoice (estructura actual)

```
packing_slips
+----+------------+---------+------------+
| id | ps_number  | status  | invoice_id |  <-- FK nullable a invoices.id (SET NULL on delete)
+----+------------+---------+------------+
| 1  | PS-2026-001| shipped | 1          |
+----+------------+---------+------------+

invoices
+----+----------------+---------+------------------+
| id | invoice_number | status  | packing_slip_id  |  <-- FK a packing_slips.id (RESTRICT on delete)
+----+----------------+---------+------------------+
| 1  | 00001          | issued  | 1                |
+----+----------------+---------+------------------+
```

La referencia es **bidireccional**:
- `packing_slips.invoice_id` -> `invoices.id` (ON DELETE SET NULL)
- `invoices.packing_slip_id` -> `packing_slips.id` (ON DELETE RESTRICT)

---

## 2. Estructura de Datos Relevante

### 2.1 Tabla `packing_slips` (campos clave)

| Campo          | Tipo          | Notas                                              |
|----------------|---------------|----------------------------------------------------|
| `id`           | bigint PK     |                                                    |
| `ps_number`    | varchar(30)   | UNIQUE. Usado como slug de ruta. Inmutable en shipped. |
| `status`       | enum          | `draft`, `pending`, `shipped`, `cancelled`         |
| `invoice_id`   | bigint FK     | Nullable. SET NULL si Invoice se elimina.          |
| `document_date`| date          | Nullable. Bloqueado en pending/shipped/cancelled.  |
| `shipped_at`   | timestamp     | Se llena al marcar shipped. Se limpia si se revierte. |
| `shipped_by`   | bigint FK     | Usuario que despachó. Se limpia si se revierte.    |
| `deleted_at`   | timestamp     | SoftDeletes activo.                                |

### 2.2 Tabla `invoices` (campos clave)

| Campo             | Tipo          | Notas                                              |
|-------------------|---------------|----------------------------------------------------|
| `id`              | bigint PK     |                                                    |
| `invoice_number`  | varchar(10)   | UNIQUE. Slug de ruta. Secuencial global.           |
| `status`          | enum          | `draft`, `issued`, `cancelled`                     |
| `type`            | enum          | `product` (desde PS), `standalone`                 |
| `packing_slip_id` | bigint FK     | RESTRICT on delete. Vincula con el PS origen.      |
| `lot_no`          | varchar(20)   | Mutable. Calculado desde shipped_at del PS.        |
| `grand_total`     | decimal(14,2) | Calculado por `calculateTotals()`.                 |
| `issued_at`       | timestamp     | Se llena al emitir. No se limpia al cancelar.      |
| `deleted_at`      | timestamp     | SoftDeletes activo.                                |

### 2.3 Tabla `invoice_items` (campos clave)

| Campo                | Tipo          | Notas                                             |
|----------------------|---------------|---------------------------------------------------|
| `invoice_id`         | bigint FK     | CASCADE on delete. Hijo del Invoice.              |
| `packing_slip_item_id`| bigint FK    | SET NULL on delete. Referencia al PSItem origen.  |
| `description`        | varchar(255)  | Snapshot inmutable del part number al crear.      |
| `quantity`           | int unsigned  | Snapshot de quantity_packed al crear.             |
| `unit_cost`          | decimal(10,4) | Snapshot del precio al crear.                     |
| `line_total`         | decimal(12,2) | Snapshot del calculo al crear.                    |
| `is_fixed_charge`    | boolean       | TRUE para cargos fijos. FALSE para items PS.      |

**Observacion critica:** Los `invoice_items` son un **snapshot inmutable** del estado del PS en
el momento de crear el Invoice. Si se corrige el PS despues, los items del Invoice reflejan los
datos incorrectos originales, no los datos corregidos.

### 2.4 Tabla `packing_slip_items` (campos clave)

| Campo            | Tipo          | Notas                                              |
|------------------|--------------|----------------------------------------------------|
| `packing_slip_id`| bigint FK     | Relacion con el PS padre.                          |
| `lot_id`         | bigint FK     | withTrashed() para auditoria historica.            |
| `quantity_packed`| int          | Cantidad empacada real.                            |
| `unit_price`     | decimal(10,4) | Se llena durante la creacion del Invoice.          |
| `price_tier_id`  | bigint FK     | FK al tier de precio aplicado.                     |
| `price_source`   | string        | `tier`, `sample`, `manual`.                        |

---

## 3. Restricciones Tecnicas Identificadas

A partir de la exploracion del codigo, se documentan las siguientes restricciones que condicionan
cualquier estrategia de correccion:

### R1 - Bloqueo total de edicion en estado `shipped`
`PackingSlipShow.php` bloquea **todos** los metodos de edicion cuando `isShipped()` es verdadero:
`updateDocumentDate()`, `updatePsNumber()`, `updateNotes()`, `toggleEditingLots()`, `updateLots()`.

### R2 - `updateStatus()` no valida la existencia del Invoice
El metodo `updateStatus()` en `PackingSlipShow.php` permite cambiar el estado del PS (incluso
desde `shipped`) sin verificar si `invoice_id` esta lleno. Esto podria dejar el Invoice en un
estado inconsistente (vinculado a un PS que ya no esta en `shipped`).

### R3 - `InvoiceDeleteService` bloquea la eliminacion de Invoices emitidos
Un Invoice en estado `issued` no puede ser eliminado ni modificado. La unica salida lateral desde
`issued` no existe actualmente (no hay transicion `issued -> draft`).

### R4 - Snapshot inmutable en `invoice_items`
Los items del Invoice son copias punto-en-el-tiempo del PS. Corregir los `packing_slip_items` no
actualiza automaticamente los `invoice_items`. Ambas tablas deben actualizarse de forma coordinada.

### R5 - Constraint RESTRICT en `invoices.packing_slip_id`
`invoices.packing_slip_id` tiene `ON DELETE RESTRICT`. No se puede eliminar un PS mientras tenga
un Invoice vivo (no soft-deleted) que lo referencie.

### R6 - La secuencia de `invoice_number` es global e irrecuperable
El metodo `generateInvoiceNumber()` usa `withTrashed()` para evitar colisiones. Un Invoice
soft-deleted preserva su numero en la secuencia. Esto significa que "cancelar y recrear" genera
un gap visible en la numeracion (ej: 00001 cancelado, 00002 es el corregido).

### R7 - `AuditTrailService` existe pero no esta integrado con PS ni Invoice
El servicio `AuditTrailService` esta implementado (con metodos `recordCreate`, `recordUpdate`,
`recordStatusChange`, etc.) pero la revision del codigo no muestra su uso en los flujos de
`PackingSlipShow` ni `InvoiceShow`. La auditoria actual se realiza solo mediante `Log::info()`.

---

## 4. Analisis de Opciones

### Opcion A: Revertir a Borrador (shipped -> draft)

**Descripcion:** El PS vuelve al estado `draft`. El Invoice asociado se cancela o elimina.
El empleado corrige los datos y vuelve a despachar.

**Impacto en el codigo:**

- Modificar `updateStatus()` en `PackingSlipShow` para detectar cuando se revierte desde
  `shipped` y el PS tiene `invoice_id != null`.
- Invocar `InvoiceDeleteService` (si el Invoice esta en `draft`) o bloquear la reversion
  (si el Invoice ya esta en `issued`).
- Limpiar `packing_slips.invoice_id = null` y `shipped_at/shipped_by`.
- El empleado corrige los datos del PS (lotes, ps_number, etc.).
- El empleado vuelve a despachar: `draft -> pending -> shipped`.
- El empleado crea el Invoice nuevamente.

**Ventajas:**
- Reutiliza la logica de creacion de Invoice ya existente (`InvoiceFromPackingSlipService`).
- El PS corregido mantiene su `ps_number` original: no hay gaps en la numeracion del PS.
- Flujo familiar para el usuario: mismos pasos que la primera vez.
- Minima adicion de tablas o columnas nuevas.

**Desventajas:**
- Si el Invoice ya esta en `issued`, la reversion es imposible sin agregar la transicion
  `issued -> draft` al Invoice, lo cual viola el principio de inmutabilidad del documento emitido.
- Se pierde el historial de quien creo el Invoice original y cuando se emitio, a menos que
  se agregue auditoria explicita.
- El nuevo Invoice tendra un `invoice_number` diferente (el siguiente en la secuencia),
  creando un gap en la numeracion del Invoice original si este fue eliminado.
- Riesgo de que el empleado corrija incorrectamente o olvide recrear el Invoice.
- No hay trazabilidad del error original: se borra el registro del PS incorrecto.

**Riesgos:**
- Un Invoice emitido y ya enviado al cliente no puede simplemente "desaparecer" sin consecuencias
  legales/contables. Esta opcion es aceptable solo si el Invoice sigue en `draft`.
- `invoices.packing_slip_id` tiene ON DELETE RESTRICT: si el PS no se elimina (solo revierte),
  esto no es problema. Pero si se intenta eliminar el PS, la FK bloqueara la operacion.

**Calificacion:** Viable unicamente si el Invoice esta en `draft`. Inaceptable si el Invoice
ya fue emitido (`issued`).

---

### Opcion B: Crear PS Correccion/Enmienda

**Descripcion:** Se crea un nuevo PS como "enmienda" del anterior. El PS original queda en
estado `cancelled` con una referencia al nuevo PS. El Invoice original se cancela y se genera
uno nuevo desde el PS corregido.

**Impacto en el codigo:**

- Agregar columna `amended_by_ps_id` (nullable FK) en `packing_slips` para registrar que
  este PS fue reemplazado por uno nuevo. Nueva migracion requerida.
- Crear un nuevo estado `amended` (o reutilizar `cancelled` con una nota) en el ENUM de
  `packing_slips`. Nueva migracion requerida.
- Crear un servicio `PackingSlipAmendService` que:
  1. Cancela el PS original (status = `amended` o `cancelled`).
  2. Cancela el Invoice original (si esta en `draft`; si esta en `issued`, requiere anulacion).
  3. Crea un nuevo PS copiando los datos del original (ps_number nuevo, mismos lotes como punto
     de partida).
  4. Vincula `ps_original.amended_by_ps_id = ps_nuevo.id`.
- El usuario edita el nuevo PS (en estado `draft`) y lo despacha normalmente.

**Ventajas:**
- Trazabilidad completa: el PS original queda como registro historico, no se borra.
- El nuevo PS tiene su propio `ps_number` y ciclo de vida limpio.
- Patron documentado en sistemas ERP (numero de enmienda).
- Separacion clara entre el documento incorrecto y el corregido.

**Desventajas:**
- Requiere cambios de esquema no triviales: nueva columna, nuevo estado en ENUM.
- La UI debe mostrar la cadena de enmiendas para no confundir al usuario.
- El `ps_number` del documento original se "pierde" como referencia activa; si el cliente
  recibio un Shipping List con el numero antiguo, hay que comunicar el cambio.
- Complejidad adicional: la logica de creacion de Invoice debe ignorar los PS en estado
  `amended` para los listados.
- Si el Invoice ya esta en `issued`, se necesita una ruta `issued -> cancelled` en el Invoice,
  lo cual introduce complejidad contable.

**Riesgos:**
- La proliferacion de PS en estado `amended` puede ensuciar los listados si no se filtra
  correctamente.
- Doble numero de Invoice activo durante el periodo de transicion si el flujo no es atomico.

**Calificacion:** Arquitecturalmente correcta para sistemas con alto volumen de correcciones,
pero tiene costo de implementacion significativo para el estado actual del proyecto.

---

### Opcion C: Edicion Directa con Log de Auditoria

**Descripcion:** Se permite editar campos especificos del PS en estado `shipped` mediante
una accion protegida por rol (solo Admin). El Invoice se recalcula automaticamente si esta
en `draft`, o se requiere confirmacion del usuario si ya esta en `issued`.

**Impacto en el codigo:**

- Agregar metodos de edicion en `PackingSlipShow` que no validen `isShipped()` sino el rol
  del usuario (`hasPermissionTo('packing-slip.edit-shipped')`).
- Agregar un metodo `recalculateInvoice()` en `InvoiceFromPackingSlipService` que destruya
  los `invoice_items` actuales y los recree desde el PS actualizado (sin tocar el `invoice_number`).
- Registrar cada edicion en `AuditTrail` (el servicio ya existe pero no se usa en este flujo).
- Para Invoices en `issued`: bloquear la edicion directa del PS y requerir que el Admin
  primero revierta el Invoice a `draft` (nueva transicion `issued -> draft` con restriccion de rol).

**Ventajas:**
- El `ps_number` y el `invoice_number` no cambian: el cliente ve el mismo numero de documento.
- Auditoria granular: se sabe exactamente que campo cambio, quien lo cambio y cuando.
- Minimo impacto en la UI existente: se agrega una seccion de "edicion en shipped" protegida.
- El `AuditTrailService` ya existe y puede usarse directamente.
- Menor friccion para el usuario Admin: no necesita recrear documentos.

**Desventajas:**
- Viola el principio de inmutabilidad de un documento despachado: un PS `shipped` deberia
  ser un registro historico fiel del despacho fisico.
- El recalculo del Invoice modifica los `invoice_items` (snapshot), que por diseno son
  "inmutables al emitir" segun los comentarios del codigo.
- Si el Invoice ya esta en `issued` y se edita el PS, la integridad del PDF ya generado
  y posiblemente ya enviado al cliente queda comprometida.
- Requiere implementar la transicion `issued -> draft` en el Invoice, con toda la logica
  de seguridad asociada.
- Mayor superficie de ataque: una edicion directa mal implementada puede corromper datos
  financieros sin dejar rastro suficiente.

**Riesgos:**
- Si el Invoice emitido ya fue enviado al cliente y luego se modifica en el sistema, hay
  una discrepancia entre el documento fisico y el registro digital. Riesgo legal/contable alto.
- La edicion directa puede enmascarar errores sistemicos: si el empleado comete el mismo
  error repetidamente, la facilidad de correccion reduce la presion para mejorar el proceso.

**Calificacion:** Viable para correcciones menores en Invoices en `draft`. Inaceptable
para Invoices ya en `issued` sin controles adicionales muy robustos.

---

### Opcion D: Cancelacion y Recreacion (Recomendada con matices)

**Descripcion:** El PS incorrecto se cancela (no se elimina fisicamente). El Invoice
vinculado se cancela tambien. Se crea un nuevo PS desde cero con los datos correctos.
El nuevo PS genera su propio Invoice nuevo. Ambos documentos cancelados quedan como
registro historico con la referencia al PS de reemplazo en las notas.

**Impacto en el codigo:**

Esta opcion puede implementarse con el codigo **actual** sin cambios de esquema, aprovechando:

1. El estado `cancelled` ya existe en `packing_slips`.
2. El `InvoiceDeleteService` ya puede eliminar (soft-delete) Invoices en `draft` o `cancelled`.
3. `InvoiceShow::cancelInvoice()` ya cancela un Invoice en `draft`.
4. `PackingSlipCreate` ya permite crear nuevos PS.

**Flujo de correccion (paso a paso con el codigo actual):**

```
Paso 1: Admin va al Invoice vinculado (si esta en issued, este paso es el bloqueante).
        -> Si Invoice en draft: InvoiceShow::cancelInvoice() -> Invoice pasa a cancelled.
        -> Si Invoice en issued: NO HAY RUTA. Bloqueante critico.

Paso 2: Admin va al PS y cambia el estado a cancelled via updateStatus().
        [NOTA: updateStatus() actual NO valida invoice_id antes de cancelar el PS.
         Esto puede dejar el Invoice en draft sin PS activo. Es un bug latente.]

Paso 3: Admin crea un nuevo PS via PackingSlipCreate con los datos correctos.

Paso 4: El nuevo PS se despacha y se genera su Invoice.

Paso 5 (manual): Admin agrega en las notas del PS cancelado la referencia al nuevo PS.
```

**Ventajas:**
- Maxima trazabilidad: el PS y el Invoice incorrectos quedan como registros historicos
  soft-deleted o en estado `cancelled`. No se pierde nada.
- No requiere cambios de esquema para el caso basico (Invoice en `draft`).
- Separa claramente el documento incorrecto del correcto.
- Es la unica opcion que funciona con el codigo existente sin modificaciones en el caso
  en que el Invoice aun esta en `draft`.
- El `invoice_number` del Invoice cancelado queda como gap visible, lo que actua como
  alerta de auditoria natural.

**Desventajas:**
- Para Invoices en `issued`: la opcion D tampoco tiene solucion directa en el codigo actual.
  Se necesita agregar la transicion `issued -> cancelled` con restriccion de rol Admin.
- El nuevo PS tiene un `ps_number` diferente. Si el Shipping List fisico ya salio con el
  numero anterior, hay que emitir una nota de correccion.
- La numeracion de Invoices tiene un gap visible (el numero del Invoice cancelado).
- La coordinacion manual de los pasos (especialmente el Paso 5 de las notas) depende del
  proceso del usuario.

**Riesgos:**
- El bug latente en `updateStatus()` (R2): si el Admin cancela el PS sin primero cancelar
  el Invoice, el Invoice queda en estado inconsistente (vinculado a un PS cancelado).

---

## 5. Tabla Comparativa

| Criterio                          | Opcion A     | Opcion B     | Opcion C     | Opcion D     |
|-----------------------------------|:------------:|:------------:|:------------:|:------------:|
| Funciona sin cambios de esquema   | Parcial      | No           | No           | Si (draft)   |
| Funciona con Invoice en issued    | No           | Con cambios  | Con cambios  | Con 1 cambio |
| Trazabilidad / Auditoria          | Baja         | Alta         | Media        | Alta         |
| Complejidad de implementacion     | Media        | Alta         | Media-Alta   | Baja-Media   |
| Impacto en numeracion de Invoice  | Gap en num.  | Gap en num.  | Sin cambio   | Gap en num.  |
| Impacto en ps_number              | Sin cambio   | Nuevo numero | Sin cambio   | Nuevo numero |
| Integridad del documento emitido  | Comprometida | Preservada   | Comprometida | Preservada   |
| Experiencia de usuario (UX)       | Media        | Compleja     | Simple       | Media        |

---

## 6. Recomendacion Final

### Estrategia recomendada: Opcion D mejorada (Cancelacion y Recreacion con coordinacion atomica)

Se recomienda implementar la Opcion D como estrategia base, con las siguientes mejoras puntuales
que minimizan el esfuerzo de implementacion y maximizan la integridad:

**Justificacion tecnica:**

1. **Menor impacto arquitectural:** No requiere nuevas tablas ni columnas. Solo se necesita
   agregar logica de coordinacion entre estados existentes y una nueva transicion en el Invoice.

2. **Preserva la integridad del documento emitido:** El PS y el Invoice incorrectos quedan como
   registros historicos inmutables. El documento correcto tiene su propio ciclo de vida limpio.

3. **Aprovecha infraestructura existente:** `InvoiceDeleteService`, `cancelInvoice()` en
   `InvoiceShow`, `PackingSlipCreate`, y `AuditTrailService` ya estan implementados.

4. **Alineacion con el flujo de negocio:** En documentos de despacho y facturacion, la practica
   estandar es anular el documento incorrecto y emitir uno nuevo (no modificar el original).

### Unica adicion de esquema requerida: transicion `issued -> cancelled` en Invoice

El unico bloqueante real es la ausencia de una ruta `issued -> cancelled` en el Invoice.
Actualmente `cancelInvoice()` en `InvoiceShow` bloquea esta transicion con:
```php
if (! $this->invoice->isDraft()) { ... }
```
Se necesita agregar una nueva accion restringida a Admin que permita esta transicion con registro
de auditoria obligatorio. Esto es un cambio de comportamiento, no un cambio de esquema.

---

## 7. Proximos Pasos Sugeridos

Los siguientes pasos estan ordenados por dependencia logica. No representan un compromiso de
implementacion, sino la secuencia optima si se decide proceder.

### Paso 1: Corregir el bug latente en `updateStatus()` (prioritario, bajo riesgo)

**Archivo:** `app/Livewire/Admin/PackingSlips/PackingSlipShow.php`
**Cambio:** Antes de permitir una transicion DESDE `shipped`, verificar si `invoice_id != null`
y emitir una advertencia (o bloquear si el Invoice esta en `issued`).

Esto evita el estado inconsistente donde un PS se cancela pero su Invoice queda activo.

### Paso 2: Agregar transicion `issued -> cancelled` en Invoice con restriccion de rol Admin

**Archivo:** `app/Livewire/Admin/Invoices/InvoiceShow.php`
**Cambio:** Agregar metodo `voidInvoice()` (distinto de `cancelInvoice()`) que:
- Solo este disponible para usuarios con permiso `invoice.void`.
- Cambie el estado de `issued` a `cancelled`.
- Registre la accion en `AuditTrailService::recordStatusChange()`.
- Registre la razon de la anulacion (campo `notes` del Invoice o nuevo campo `void_reason`).

### Paso 3: Crear servicio `PackingSlipCorrectionService`

**Archivo nuevo:** `app/Services/PackingSlipCorrectionService.php`
**Responsabilidad:** Coordinar atomicamente en una transaccion DB:
1. Cancelar el Invoice vinculado (si existe y no esta ya cancelado).
2. Cancelar el PS original.
3. Agregar en `notes` del PS original la referencia al motivo de la cancelacion.
4. Retornar el PS original cancelado para que el usuario sepa que puede proceder a crear uno nuevo.

Esto evita los pasos manuales inconsistentes del flujo actual.

### Paso 4: Agregar boton "Corregir este PS" en la vista del PS despachado

**Archivo:** `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php`
**Cambio:** Cuando el PS esta en `shipped` y el usuario tiene el permiso adecuado, mostrar
un boton "Corregir / Anular PS" que invoca `PackingSlipCorrectionService` y luego redirige
a la pantalla de creacion de nuevo PS con los datos pre-llenados del PS cancelado.

### Paso 5: Integrar `AuditTrailService` en los flujos de PS e Invoice

**Archivos:** `PackingSlipShow.php`, `InvoiceShow.php`
**Cambio:** Utilizar `AuditTrailService::recordStatusChange()` y `recordUpdate()` en todas
las transiciones de estado y cambios de datos sensibles. El servicio ya existe y tiene la
interfaz correcta; solo falta invocarlo.

---

## 8. Notas de Implementacion Criticas

- **Atomicidad:** Cualquier mecanismo de correccion debe ejecutarse dentro de `DB::transaction()`
  para evitar estados intermedios inconsistentes entre `packing_slips` e `invoices`.

- **Restriccion de rol:** Las acciones de correccion deben estar protegidas por un permiso
  granular (ej: `packing-slip.correct-shipped`) usando Spatie Permissions, no simplemente
  verificando si el usuario es Admin.

- **Numeracion de Invoice:** Aceptar que el `invoice_number` del Invoice cancelado crea un gap
  en la secuencia es la decision correcta. El gap es evidencia de auditoria, no un problema.
  La alternativa (reutilizar el mismo numero) es peor desde el punto de vista contable.

- **PDF ya enviado al cliente:** Si el Invoice ya estaba en `issued` y el PDF fue descargado
  o enviado, la correccion en el sistema no invalida automaticamente el documento fisico.
  El proceso de negocio debe incluir la notificacion al cliente del numero de Invoice anulado.

- **`lot_no` del nuevo Invoice:** Al crear el nuevo Invoice desde el PS corregido,
  `InvoiceFromPackingSlipService::calculateLotNo()` recalculara el `lot_no` usando el nuevo
  `shipped_at`. Si la fecha de despacho correcta es diferente a la original, el `lot_no`
  tambien sera diferente. El Admin puede ajustarlo manualmente en estado `draft`.

---

## 9. Archivos Relevantes

| Archivo                                                            | Rol en el flujo                                   |
|--------------------------------------------------------------------|---------------------------------------------------|
| `app/Models/PackingSlip.php`                                       | Estados, relaciones, helpers                      |
| `app/Models/Invoice.php`                                           | Estados, relaciones, `calculateTotals()`          |
| `app/Models/InvoiceItem.php`                                       | Snapshot del item; `recalculateLineTotal()`       |
| `app/Models/PackingSlipItem.php`                                   | Items del PS; campos de auditoria de precio       |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php`              | Guards de edicion; `updateStatus()` (bug R2)      |
| `app/Livewire/Admin/Invoices/InvoiceShow.php`                      | `cancelInvoice()` solo desde draft (restriccion)  |
| `app/Services/InvoiceFromPackingSlipService.php`                   | Creacion del Invoice desde PS shipped             |
| `app/Services/InvoiceDeleteService.php`                            | Eliminacion de Invoice (bloquea si issued)        |
| `app/Services/AuditTrailService.php`                               | Auditoria disponible, sin uso en PS/Invoice aun   |
| `app/Http/Controllers/InvoiceController.php`                       | Endpoint POST create-invoice; PDF download/stream |
| `database/migrations/2026_03_18_100003_add_invoice_id_to_ps.php`  | FK bidireccional PS <-> Invoice                   |
