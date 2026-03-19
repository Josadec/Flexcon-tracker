# Analisis Tecnico: Invoice FPL-12 — Implementacion Definitiva

**Fecha:** 2026-03-18
**Elaborado por:** Arquitecto de Software — FlexCon Tracker
**Version:** 1.1 — Actualizado con respuestas del cliente a P-12-02 y P-12-03
**Proposito:** Producir el analisis tecnico completo y el plan de implementacion definitivo del modulo Invoice (FPL-12), integrando la lectura directa del PDF `FPL-12 Invoice #01006.pdf` con el estado actual del sistema (Packing Slip implementado, campos de precio pre-existentes, modelos Price/PriceTier operativos) y la cadena de analisis previos 01–11.

**Documentos previos incorporados:**
- `01_shipping_list_analysis.md` — Estructura del Packing Slip FPL-10
- `05_decisiones_confirmadas_y_plan_implementacion.md` — Plan de implementacion v1.0 del PS
- `07_fpl10_cumplimiento_vs_implementacion.md` — Cumplimiento FPL-10 vs codigo
- `09_fpl12_invoice_analisis_implementacion.md` — Analisis previo del Invoice (pre-PDF)
- `11_wo_listos_ps_vs_packing_slip_analisis.md` — Relacion ShippingQueue vs PackingSlipShow

---

## 1. Resumen Ejecutivo

El Invoice (FPL-12) es el **documento financiero terminal** del ciclo de produccion de FlexCon. Se genera directamente desde un Packing Slip en estado `shipped` y representa la conversion del documento logistico en la factura comercial que cobra al cliente S.E.I.P., Inc.

El analisis directo del PDF `Invoice #01006` (fecha March-11-2026) confirma y actualiza varias hipotesis del analisis previo `09_fpl12_invoice_analisis_implementacion.md`. Los hallazgos mas importantes de esta revision son:

1. **El formato del LOT NO. ha cambiado**: El PDF muestra `030926x01` y `030926x20` — la fecha usa el formato `DDMMYY` (dia-mes-ano), NO `MMDDYY` como se habia asumido. El ejemplo `030926` corresponde al 9 de marzo de 2026 (09/03/26), donde `03` es el dia, `09` el mes y `26` el ano.
2. **Machine Maintenance cuesta $1,200** en este Invoice, no $800 como en el Excel 2025. Los cargos fijos son variables entre Invoices y deben ser editables al crear el Invoice.
3. **La numeracion llego a 5 digitos con cero inicial**: `Invoice#01006` — el formato es de 5 digitos con padding cero, comenzando desde el 00001 historico. El sistema debe continuar desde #01007.
4. **El Invoice no tiene subtotales por PO**: la tabla es una lista continua sin agrupaciones, a diferencia del Packing Slip que agrupa por PO.
5. **La infraestructura de precio ya esta completamente lista**: los campos `unit_price`, `price_tier_id`, `price_source` en `packing_slip_items`, y los modelos `Price`/`PriceTier` con `getPriceForQuantity()` estan operativos.
6. **No se requiere crear tabla `invoice_items` separada para los items de producto**: los datos de linea del Invoice son identicos a los items del PackingSlipItem. Sin embargo, los cargos fijos (Machine Maintenance, Admin Fee, Shipping Cost) SI requieren una tabla separada o columnas en `invoices`.

---

## 2. Estructura Visual del Documento FPL-12

### 2.1 Mapa ASCII del Invoice #01006 (basado en PDF real)

```
+===========================================================================+
|                         FLEXCON                                           |
|  [LOGO]        INVOICE              | Clave:    FPL-12                   |
|                                     | Revision: 01                       |
|         330 Rocky Woods Lane • Bigfork, Montana • 59911                  |
|  PH# 425-466-2184              frank@flexconinc.com                      |
+===========================================================================+
|  Sold to:               Shipped to:           Packing Slip #001292       |
|  S.E.I.P., Inc.         S.E.I.P., Inc.                                   |
|  915 Armorlite Dr.      915 Armorlite Dr.                                |
|  San Marcos, Ca. 92069  San Marcos, Ca. 92069  F.O.B: Tecate, Ca.       |
+===========================================================================+
|  Invoice#01006  (en rojo/destacado)            DATE: March-11-2026       |
+===========================================================================+
| DESCRIPTION | Item No.  | LOT NO.   | P.O No.| W.O No. | QTY   | UCOST | TOTAL   |
|-------------|-----------|-----------|--------|---------|-------|-------|---------|
| STS H-HC-2-0-H | 189-10635 | 030926x01 | 50073 | 2022465 | 108,000 | 0.1796 | 19,396.80 |
| STS H-M-3      | 189-10179 | 030926x20 | 50555 | 2046820 | 100,000 | 0.0935 |  9,350.00 |
| STS H-CR-436-36 CRIMP | 189-10491 | 030926x01 | 50460 | 2040073 | 50,000 | 0.1791 | 8,955.00 |
| STS H-HC-2-0-H | 189-10635 | 030926x01 | 50071 | 2022449 | 48,000 | 0.1796 | 8,620.80 |
| ...           | ...       | ...       | ...   | ...     | ...   | ...   | ...     |
| STS H-MB-5    | 189-10605 | 030926x01 | 50115 | 2023901 | 1,000 | 0.2712 | 271.20  |
|-------------|-----------|-----------|--------|---------|-------|-------|---------|
| Machine Maintenance                                        | 1,200 | 1,200.00  |
| Administration Fee                                         |   250 |   250.00  |
| SHIPPING COST                                              |   450 |   450.00  |
|-------------|-----------|-----------|--------|---------|-------|-------|---------|
|                                                 529,600   |       | 82,478.59 |
+===========================================================================+
|  1 de 1                                         FPL-12  Rev 01           |
+===========================================================================+
```

### 2.2 Desglose de Secciones

| Seccion | Contenido | Notas |
|---|---|---|
| **Banner superior** | Logo FLEXCON + titulo INVOICE + Clave/Rev | Titulo mas grande que en el PS |
| **Barra de direccion** | 330 Rocky Woods Lane, Bigfork, Montana 59911 | Identica al PS |
| **Barra de contacto** | PH# 425-466-2184 + frank@flexconinc.com | Email diferente al PS (`frank@flexconinc.com` vs `Frank@flexconinc.com`) |
| **Bloque cliente** | Sold to / Shipped to + Packing Slip # + F.O.B. | Agrega referencia al PS en esta seccion |
| **Identificacion** | Invoice#01006 en rojo + DATE: March-11-2026 | Numero y fecha del Invoice |
| **Tabla de items** | 8 columnas (ver seccion 2.3) | Lista continua SIN subtotales por PO |
| **Cargos fijos** | 3 filas sin Item/LOT/PO/WO | Al final de los items de producto |
| **Grand Total** | Fila con total piezas + total importe | Ultima fila de la tabla |
| **Pie de pagina** | "1 de 1" + "FPL-12  Rev 01" | Paginacion y referencia del documento |

---

## 3. Tabla Comparativa: FPL-10 (Packing Slip) vs FPL-12 (Invoice)

| Dimension | FPL-10 Packing Slip | FPL-12 Invoice |
|---|---|---|
| **Proposito** | Documento logistico de despacho | Documento financiero de facturacion |
| **Nombre empresa** | `ENSAMBLES FORMULA` | `FLEXCON` |
| **Titulo del documento** | `SHIPPING LIST` | `INVOICE` |
| **Clave** | `FPL-10` | `FPL-12` |
| **Revision** | `02` | `01` |
| **Email de contacto** | `Frank@flexconinc.com` | `frank@flexconinc.com` |
| **Numero de documento** | `PS-YYYY-NNNN` (ej: PS-2026-0001) | `Invoice#NNNNN` (ej: Invoice#01006) |
| **Formato de numero** | Anual con reset por año | Secuencial global sin reset anual |
| **Referencia cruzada** | No referencia al Invoice | Referencia al Packing Slip (`Packing Slip #001292`) |
| **Columnas de la tabla** | 7 col: WO, PO#, Item no, Description, Qty, Date, Label Spec | 8 col: Description, Item No., LOT NO., P.O No., W.O No., Qty, Unit Cost, Total |
| **Columna de precio** | No existe | `UNIT COST` (4 decimales) |
| **Columna de total por linea** | No existe | `TOTAL` (2 decimales) |
| **Columna LOT NO.** | `Date` (lot_date_code: valor libre) | `LOT NO.` (formato `DDMMYYxNN`) |
| **Columna Label Spec** | Si | No |
| **Columna W.O No.** | `Work Order` (formato W0+7dig+3dig) | `W.O No.` (solo 7 digitos del external_wo_number) |
| **Agrupacion de items** | Agrupados por PO con subtotales | Lista continua SIN agrupacion ni subtotales |
| **Cargos fijos** | No existen | Machine Maintenance, Administration Fee, SHIPPING COST |
| **Grand Total** | No existe | Total piezas + Total importe |
| **Pie de firma** | Si (con campos de firmas) | No — solo paginacion |
| **Genera el PDF** | Estado `shipped` del PS | Estado `issued` del Invoice |
| **Trigger de creacion** | Lotes `ready_for_shipping = true` | PS en estado `shipped` |
| **Ciclo de vida** | pending -> shipped -> cancelled | draft -> issued -> (paid) |

---

## 4. Hallazgos Criticos del PDF Invoice #01006

### 4.1 Formato del LOT NO. — Correccion del Analisis Previo

**Hallazgo:** El PDF muestra `030926x01` para la fecha 9 de marzo de 2026.

El analisis previo (`09_fpl12_invoice_analisis_implementacion.md`) habia asumido formato `MMDDYY`. El PDF confirma que el formato es **`DDMMYY`**:

```
030926 = 03 (dia 03) + 09 (mes septiembre? NO)
```

Revisando la fecha del Invoice (March-11-2026 = 11 de marzo de 2026) y que el LOT NO. es `030926`, el analisis correcto es:

```
030926 = 03 (dia) + 09 (mes) + 26 (ano)
         ^dia 03  ^mes 09 = septiembre? NO

Alternativa: 03/09/26 donde 03=dia, 09=mes, 26=año -> 3 de septiembre de 2026? No coincide con el Invoice de marzo.
```

**Interpretacion correcta tras analisis de los datos del Excel 2025:**
El formato observado en el Excel 2025 (`052625x01` = Invoice de 28 de mayo de 2025) indica `05` dia, `26` mes?... Eso tampoco tiene sentido.

**Reinterpretacion definitiva basada en ambos documentos:**
- Excel 2025: `052625x01`, fecha del Invoice = May-28-2025 → `05` podria ser mes(5), `26` dia(26), `25` año(25) → formato `MMDDYY`
- PDF 2026: `030926x01`, fecha del Invoice = March-11-2026 → si `MMDDYY` serian 03=mes(marzo), 09=dia(9), 26=año(2026)

**Conclusion:** El formato es `MMDDYY` pero la fecha del LOT NO. es la fecha de **despacho/produccion del lote**, NO necesariamente la fecha del Invoice. El Invoice #01006 fue creado el 11 de marzo de 2026, pero los lotes se despacharon/produjeron el 9 de marzo de 2026 (`03/09/26`). Esto confirma la hipotesis de que la fecha del LOT NO. es la fecha de `packing_slips.shipped_at` (la fecha en que se marco el PS como despachado), que puede ser anterior a la fecha en que se genera el Invoice.

**Logica de derivacion definitiva:**
```
lot_number = format(ps.shipped_at, 'mdY_2digit_MMDDYY') + 'x' + sufijo_tipo_estacion
           = Carbon::parse($ps->shipped_at)->format('m') .
             Carbon::parse($ps->shipped_at)->format('d') .
             Carbon::parse($ps->shipped_at)->format('y') .
             'x' . ($workstation_type === 'table' ? '01' : '20')
```

**DECISION P-12-01 — RESUELTA Y AMPLIADA:**

La fecha del LOT NO. NO es `shipped_at` del Packing Slip ni la fecha del Invoice. Se deriva del **primer lunes anterior a la fecha de envio**. El formato es `MMDDYY` donde `MM` = mes, `DD` = dia del primer lunes anterior al envio, `YY` = ano de dos digitos. Ejemplo: Invoice #01006 enviado el 11-Mar-2026 → el primer lunes anterior es el 09-Mar-2026 → `030926`.

**Logica de derivacion actualizada:**
```
$shippedAt = Carbon::parse($ps->shipped_at);
// Retroceder al lunes anterior (o el mismo dia si ya es lunes)
$lotMonday = $shippedAt->copy()->startOfWeek(Carbon::MONDAY);
if ($lotMonday->gt($shippedAt)) {
    $lotMonday->subWeek(); // si startOfWeek avanza, retroceder una semana
}
$dateComponent = $lotMonday->format('m') . $lotMonday->format('d') . $lotMonday->format('y');
$lotNumber = $dateComponent . 'x' . $suffix;
```

**Escenario de edicion manual:** Existen casos donde el dia del lote puede necesitar ajuste posterior a la creacion del Invoice (cambio de calendario, correccion de fecha de produccion, errores de captura). Por este motivo el campo `lot_no` en la tabla `invoices` **es editable por el Administrador**. Ver subseccion 4.1.1 para el diseno completo de la edicion manual del LOT NO.

### 4.1.1 Edicion Manual del LOT NO. — Escenario de Negocio y Diseno

#### Descripcion del escenario de negocio

En la practica existen situaciones donde el LOT NO. calculado automaticamente no coincide con el lote real que el cliente espera ver en el Invoice:

- El calendario de produccion se reprogramo despues de crear el PS y el Invoice.
- Se comete un error de captura en la fecha de envio del PS (`shipped_at` incorrecto).
- El cliente solicita que el Invoice refleje una semana de lote diferente por acuerdo comercial.
- Se corrige retroactivamente la fecha del primer lunes por ajuste de calendario (ej: semana con festivo).

En todos estos casos el sistema debe permitir que un **Administrador** corrija el LOT NO. directamente en el Invoice sin necesidad de cancelar y recrear el documento.

#### Decision de diseno: `lot_no` es un campo mutable en `invoices`

El LOT NO. del Invoice NO se almacena exclusivamente en `invoice_items.lot_number`. Ademas de ese snapshot por linea, se agrega el campo `lot_no` en la tabla `invoices` para representar el lote predominante del Invoice (que es compartido por la mayoria de los items del mismo PS, ya que todos vienen del mismo despacho).

**Decision tecnica D-12-21:** El campo `lot_no` en la tabla `invoices` es **mutable por diseno**. No es `readonly` ni se marca como `immutable` al emitir el Invoice. Puede ser editado por el Administrador en cualquier estado del Invoice (`draft`, `issued`), con registro de auditoria.

**Razon de no bloquear en estado `issued`:** El LOT NO. es un identificador de lote de produccion, no un dato financiero. A diferencia del `grand_total` o `unit_cost` (que deben estar bloqueados para integridad contable), el LOT NO. puede cambiar sin alterar los calculos monetarios del Invoice.

#### Campo en la base de datos

Agregar a la migracion `create_invoices_table` (seccion 7.1):

```sql
-- LOT NO. del Invoice (lote predominante del envio)
-- Calculado automaticamente desde el primer lunes anterior a packing_slips.shipped_at
-- Formato: MMDDYY + 'x' + sufijo (ej: '030926x01')
-- MUTABLE: el Administrador puede editarlo si la fecha del lote cambia despues de creado
lot_no  VARCHAR(20)  NULL,
```

El campo es `NULL` si el workstation_type no se puede determinar automaticamente y el usuario aun no lo ha ingresado.

#### Validacion del formato MMDDYY al editar

Al guardar una edicion manual del `lot_no`, aplicar la siguiente validacion en el metodo Livewire:

```
Regla: regex:/^\d{6}x(01|20)$/
Mensaje: 'El formato del LOT NO. debe ser MMDDYY seguido de x01 o x20 (ej: 030926x01)'
```

Si el lot_no no contiene sufijo (caso improbable pero posible en Invoices standalone futuros):

```
Regla alternativa: regex:/^\d{6}(x(01|20))?$/
```

#### Implementacion en el componente Livewire

En el componente `InvoiceShow` (Paso 7.2), agregar capacidad de edicion inline del LOT NO.:

**Propiedad Livewire:**
```php
public string $editingLotNo = '';
public bool $showLotNoEditor = false;
```

**Metodo Livewire:**
```
updateLotNo(): void
  - Verificar permiso: auth()->user()->hasRole('admin') o hasPermission('invoice.edit-lot-no')
  - Validar: ['editingLotNo' => 'required|regex:/^\d{6}x(01|20)$/']
  - Actualizar invoices.lot_no = $this->editingLotNo
  - Actualizar todos los invoice_items.lot_number del Invoice (is_fixed_charge = false)
    donde lot_number tenga el mismo prefijo de fecha (actualizar la fecha pero conservar el sufijo)
  - Registrar en logs: "LOT NO. actualizado de {anterior} a {nuevo} por usuario {id}"
  - $this->showLotNoEditor = false
  - dispatch('notify', ['type' => 'success', 'message' => 'LOT NO. actualizado correctamente'])
```

**En la vista Blade (invoice-show.blade.php):**

```
Si $invoice->isDraft() O auth()->user()->hasRole('admin'):
  Mostrar campo LOT NO. con icono de lapiz al lado
  Al hacer clic en el lapiz:
    Mostrar input text con el valor actual
    Botones: Guardar (verde) | Cancelar (gris)
    Alpine.js maneja el toggle del editor sin reload de pagina
  Si el usuario no es Admin y el Invoice esta issued:
    LOT NO. se muestra como texto readonly (sin lapiz)
```

#### Regeneracion del PDF al editar el LOT NO.

**Decision:** El PDF NO se regenera automaticamente al editar el LOT NO. El PDF se genera bajo demanda al hacer clic en "Descargar PDF". Esto garantiza que cada descarga refleja el estado actual del `lot_no` sin necesidad de invalidar caches ni almacenar multiples versiones del archivo.

**Implicacion practica:** Si el Invoice ya fue descargado por el cliente con un LOT NO. incorrecto y luego se corrige, el usuario debera descargar nuevamente el PDF y enviarlo al cliente. Esta responsabilidad es del Administrador.

#### Permisos

| Accion | Rol requerido | Estado permitido |
|---|---|---|
| Ver el LOT NO. | Todos los roles con acceso al Invoice | Cualquier estado |
| Editar el LOT NO. | Solo `admin` (o permiso `invoice.edit-lot-no`) | `draft` e `issued` |
| Editar el LOT NO. en estado `paid` | Solo `admin` | Requiere confirmacion adicional |

El permiso `invoice.edit-lot-no` puede crearse en Spatie Permissions para granularidad futura, pero en la implementacion inicial se controla con `hasRole('admin')` consistente con la decision P-12-05.

---

### 4.2 Machine Maintenance: $1,200 en el Invoice #01006 vs $800 en el Excel 2025

**Hallazgo critico:** El PDF muestra `Machine Maintenance = 1,200.00`, mientras que todos los 21 Invoices del Excel 2025 mostraban `Machine Maintenance = 800.00`.

Esto confirma que los **cargos fijos NO son invariables**. El valor de Machine Maintenance subio de $800 a $1,200 entre 2025 y 2026.

**Impacto arquitectural:** Los cargos fijos deben ser:
1. Almacenados como registros en la tabla `invoice_charge_types` (catalogo administrable)
2. **Editables al crear el Invoice** — el usuario puede cambiar el `unit_cost` del `InvoiceItem` en estado `draft`
3. Guardados como **snapshot inmutable** en `invoice_items` (via `unit_cost` del InvoiceItem) para preservar el valor historico de cada Invoice emitido

**DECISION P-12-02 — RESUELTA:** El cliente confirma que Machine Maintenance tiene un costo variable en el tiempo y solicita una tabla separada donde se pueda gestionar esta informacion, ya que el precio puede incrementar en el futuro.

**Decision adoptada:** Machine Maintenance NO se maneja como un valor fijo en `config/invoice.php` ni como un `DEFAULT` de columna fija en la tabla `invoices`. En cambio, se gestiona como un `invoice_charge_type` en la tabla `invoice_charge_types` (diseno completo en la Seccion 15). El campo `default_amount` de ese registro es el mecanismo oficial para controlar el precio vigente. El historial de precios queda preservado en los snapshots de `invoice_items.unit_cost`.

Esta decision valida explicitamente el diseno extensible propuesto en la Seccion 15. Ver decision tecnica D-12-22.

**Administration Fee ($250) y SHIPPING COST ($450):** Se mantienen con los valores del Invoice #01006. Tambien se gestionan como registros en `invoice_charge_types` con el mismo mecanismo de `default_amount` editable.

### 4.3 Numeracion del Invoice — Formato Confirmado

**Hallazgo:** El Invoice #01006 confirma que la numeracion usa 5 digitos con padding cero: `01006`.

El analisis previo habia propuesto continuar desde `00954` (basado en el Excel 2025 que terminaba en `00953`). Esto sugiere que entre mayo 2025 y marzo 2026 se emitieron aproximadamente 53 Invoices adicionales fuera del Excel.

**DECISION P-12-03 — RESUELTA:** El cliente indica que el numero de inicio no es critico en este punto y deja la decision a criterio del arquitecto.

**Decision del arquitecto: El sistema arranca desde #00001.**

Justificacion tecnica:
- Los Invoices anteriores (incluyendo el #01006 y todos los emitidos en Excel) son documentos externos generados fuera del sistema. Mezclar la numeracion crearia confusion de auditoria: ¿fue el Invoice #01007 del sistema o de Excel?
- Arrancar desde #00001 establece con claridad que todo numero emitido por el sistema fue generado por el sistema FlexCon Tracker.
- Si en el futuro se necesita referenciar un Invoice historico de Excel, se puede registrar en el campo `notes` del Invoice o en un campo de referencia externa adicional.
- El sistema tiene su propio contexto de auditoria: "Invoice del sistema FlexCon Tracker" es una categoria distinta de "Invoice Excel historico".

**Consecuencia:** En `config/invoice.php`, el valor `first_number` = 1, con formato `%05d` → genera `00001` como primer Invoice del sistema.

**Nota sobre Invoices historicos:** Los Invoices emitidos en Excel (#01006 y anteriores) son documentos externos y NO se migran al sistema. Si en el futuro se decide hacer una importacion historica (P-12-08), se implementaria como un comando Artisan de importacion con capacidad de insertar registros con `invoice_number` especifico, sin colisionar con la secuencia del sistema.

Ver decision tecnica D-12-23.

### 4.4 Items del Invoice #01006 — Datos Reales

El Invoice #01006 tiene 17 items de producto y 3 cargos fijos = 20 filas totales. Datos representativos:

| DESCRIPTION | Item No. | LOT NO. | P.O No. | W.O No. | QUANTITY | UNIT COST | TOTAL |
|---|---|---|---|---|---|---|---|
| STS H-HC-2-0-H | 189-10635 | 030926x01 | 50073 | 2022465 | 108,000 | 0.1796 | 19,396.80 |
| STS H-M-3 | 189-10179 | 030926x20 | 50555 | 2046820 | 100,000 | 0.0935 | 9,350.00 |
| STS H-CR-436-36 CRIMP | 189-10491 | 030926x01 | 50460 | 2040073 | 50,000 | 0.1791 | 8,955.00 |
| STS H-ML-8 | 189-10257 | 030926x20 | 50291 | 2031231 | 32,500 | 0.1415 | 4,598.75 |
| STS H-MB-5 | 189-10605 | 030926x01 | 50115 | 2023901 | 1,000 | 0.2712 | 271.20 |
| Machine Maintenance | — | — | — | — | — | 1,200 | 1,200.00 |
| Administration Fee | — | — | — | — | — | 250 | 250.00 |
| SHIPPING COST | — | — | — | — | — | 450 | 450.00 |
| **GRAND TOTAL** | | | | | **529,600** | | **82,478.59** |

**Observaciones del LOT NO.:**
- Sufijo `x01` = producido en mesa de trabajo (workstation_type = 'table')
- Sufijo `x20` = producido en maquina (workstation_type = 'machine' o 'semi_automatic')
- Ambos sufijos aparecen en el MISMO Invoice para partes diferentes — confirma que el sufijo es por parte/lote, no global

**Observaciones del W.O No.:**
- Los numeros son de 7 digitos puros: `2022465`, `2046820`, `2040073`
- Equivalen al campo `work_orders.external_wo_number` sin prefijo ni sufijo
- Diferente al formato del PS que usa `W0` + numero + `001`

---

## 5. Relacion Invoice / Packing Slip — Arquitectura Final

### 5.1 Reglas de Negocio Confirmadas

| Regla | Descripcion | Fuente |
|---|---|---|
| **1:1 estricta** | Un PS genera exactamente un Invoice de tipo `product` | Excel 2025 (21 hojas, 1 PS por Invoice) + PDF #01006 |
| **Trigger de creacion** | El PS debe estar en estado `shipped` | Analisis de flujo |
| **Mismo items** | Los items del Invoice provienen directamente del PS (no se pueden agregar/quitar items) | PDF #01006 confirma correspondencia exacta con PS |
| **Fecha del Invoice** | Puede ser diferente a la fecha de despacho del PS | PDF #01006: Invoice = 11-Mar, LOT NO. = 09-Mar |
| **Cargos fijos** | Siempre 3: Machine Maintenance + Administration Fee + SHIPPING COST | Todos los Invoices analizados |
| **No se puede crear Invoice sin PS** | El tipo `product` siempre requiere un PS en estado `shipped` | Regla de negocio |

### 5.2 Diagrama de Ciclo de Vida PS → Invoice

```
FLUJO COMPLETO: PO → Invoice
=================================================================

[PO Recibida]
     |
     v
[WO Creada]
     |
     v
[Lotes → Empaque → Inspeccion]
     |
     v (ready_for_shipping = true)
[Cola: WO Listos para PS]
     |
     v (usuario crea PS desde ShippingQueue)
[PackingSlip status='pending']
     |
     v (usuario marca como shipped)
[PackingSlip status='shipped']
     |
     | invoice_id IS NULL → boton "Crear Invoice FPL-12" visible
     v
[InvoiceFromPackingSlipService::createFromPackingSlip($ps)]
  - Genera invoice_number (siguiente correlativo)
  - Crea registro en `invoices` (status='draft')
  - Por cada PackingSlipItem:
      → Calcula unit_cost via Price::getActivePriceForPart()
      → Crea InvoiceItem (snapshot)
      → Llena packing_slip_items.unit_price, price_tier_id, price_source
  - Crea 3 InvoiceItems de cargos fijos
  - Calcula totales (total_quantity, grand_total)
  - Actualiza packing_slips.invoice_id = $invoice->id
     |
     v
[Invoice status='draft']
  - Precios editables por el usuario
  - Cargos fijos editables
  - Calculo en tiempo real (Alpine.js)
  - Alerta si algun item tiene unit_cost = 0 (parte sin precio)
     |
     | usuario hace clic "Emitir Invoice" y confirma
     v
[Invoice status='issued']
  - Datos bloqueados (readonly)
  - issued_at = NOW(), issued_by = auth()->id()
  - PDF disponible para descarga
     |
     v (fase futura)
[Invoice status='paid']
  - paid_at registrado
```

### 5.3 Diagrama de Relaciones de Base de Datos

```
packing_slips                    invoices
=============                    ========
id (PK)                          id (PK)
ps_number                        invoice_number
status                    +----> packing_slip_id (FK, nullable)
shipped_at                |      invoice_date
invoice_id ---------------+      status (draft|issued|paid)
created_by                       type (product|standalone)
                                 fob_location
                                 sold_to_name
                                 sold_to_address
                                 shipped_to_name
                                 shipped_to_address
                                 charge_machine_maintenance
                                 charge_administration_fee
                                 charge_shipping_cost
                                 total_quantity
                                 subtotal_items
                                 subtotal_charges
                                 grand_total
                                 notes
                                 issued_at
                                 paid_at
                                 created_by (FK → users)
                                 issued_by (FK → users)
                                      |
                                      | hasMany
                                      v
                                 invoice_items
                                 =============
                                 id (PK)
                                 invoice_id (FK → invoices)
                                 packing_slip_item_id (FK, nullable)
                                 lot_id (FK, nullable)
                                 work_order_id (FK, nullable)
                                 purchase_order_id (FK, nullable)
                                 part_id (FK, nullable)
                                 description
                                 item_number
                                 lot_number
                                 po_number
                                 wo_number
                                 quantity
                                 unit_cost (decimal 10,4)
                                 line_total (decimal 12,2)
                                 sort_order
                                 is_fixed_charge (boolean)

packing_slip_items               prices            price_tiers
==================               ======            ===========
id (PK)                          id (PK)           id (PK)
packing_slip_id (FK)             part_id (FK)      price_id (FK)
lot_id (FK)                      sample_price      min_quantity
quantity_packed                  workstation_type  max_quantity
wo_number_ps                     active            tier_price
lot_date_code                         |
label_spec                            | hasMany
unit_price [NULL → llenar]            v
price_tier_id [NULL → llenar] → price_tiers.id
price_source [NULL → llenar]
```

---

## 6. Campos de Precio Existentes — Evaluacion de Suficiencia

### 6.1 Lo que YA existe y esta listo para usar

| Campo / Modelo | Ubicacion | Estado | Uso en Invoice |
|---|---|---|---|
| `unit_price` | `packing_slip_items` | Existe, NULL | Se llena al generar el Invoice |
| `price_tier_id` | `packing_slip_items` | Existe, NULL | FK al tier usado (auditoria) |
| `price_source` | `packing_slip_items` | Existe, NULL | 'tier', 'sample' o 'manual' |
| `Price::getActivePriceForPart($partId)` | `app/Models/Price.php` | Implementado | Obtiene el precio activo con sus tiers |
| `$price->getPriceForQuantity($qty)` | `app/Models/Price.php` | Implementado | Aplica la logica de tiers por volumen |
| `PriceTier::matchesQuantity($qty)` | `app/Models/PriceTier.php` | Implementado | Verifica si una cantidad cae en el tier |
| `PackingSlipItem::getLineTotalAttribute()` | `app/Models/PackingSlipItem.php` | Implementado | `unit_price * quantity_packed` |
| `barryvdh/laravel-dompdf` v3.1 | `composer.json` | Instalado | Generacion del PDF |

### 6.2 Lo que NO existe y debe crearse

| Elemento | Tipo | Prioridad | Fase |
|---|---|---|---|
| Tabla `invoices` | Migracion + Modelo | Alta | 5 |
| Tabla `invoice_items` | Migracion + Modelo | Alta | 5 |
| Campo `invoice_id` en `packing_slips` | Migracion (ALTER) | Alta | 5 |
| `config/invoice.php` | Archivo de configuracion | Alta | 5 |
| `InvoiceFromPackingSlipService` | Servicio PHP | Alta | 6 |
| `InvoicePdfService` | Servicio PHP | Alta | 6 |
| Vista `resources/views/pdf/invoice.blade.php` | Blade template | Alta | 6 |
| Componentes Livewire (List, Create, Show) | Livewire | Alta | 7 |
| Rutas del modulo Invoice | `routes/admin.php` | Alta | 7 |
| Integracion en `PackingSlipShow` | Modificacion | Alta | 7 |

### 6.3 Suficiencia de los campos pre-existentes

Los campos `unit_price`, `price_tier_id` y `price_source` en `packing_slip_items` son **suficientes** para el flujo de precio en el Invoice. No se requieren campos adicionales en esa tabla.

**Unico campo que falta y que no estaba previsto**: el campo `invoice_id` en `packing_slips` para la navegacion rapida bidireccional `$ps->invoice`. Este es un campo de conveniencia (no estrictamente necesario porque se puede hacer `Invoice::where('packing_slip_id', $ps->id)->first()`), pero mejora el rendimiento y la claridad del codigo.

---

## 7. Diseno de Base de Datos — Tablas Nuevas

### 7.1 Tabla `invoices`

```sql
CREATE TABLE invoices (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identificacion del documento
    invoice_number              VARCHAR(10)     NOT NULL,
    -- Formato: '01006' (5 digitos, sin prefijo). El proximo es '01007'.
    -- Alternativa futura: '001007' si supera 5 digitos antes de rediseno

    invoice_date                DATE            NOT NULL,
    -- Fecha del documento. Puede diferir de packing_slips.shipped_at
    -- (el Invoice puede emitirse dias despues del despacho fisico)

    lot_no                      VARCHAR(20)     NULL,
    -- LOT NO. predominante del Invoice. Formato: MMDDYY + 'x' + sufijo (ej: '030926x01').
    -- Calculado automaticamente desde el primer lunes anterior a packing_slips.shipped_at.
    -- MUTABLE por diseno (D-12-21): editable por Administrador en estado draft e issued.
    -- NULL si el workstation_type no pudo determinarse automaticamente.

    status                      ENUM('draft','issued','paid')
                                NOT NULL DEFAULT 'draft',
    -- draft:  en edicion, precios editables
    -- issued: emitido, PDF generado, datos financieros bloqueados
    -- paid:   pagado (fase futura)

    type                        ENUM('product','standalone')
                                NOT NULL DEFAULT 'product',
    -- product:    generado desde un Packing Slip
    -- standalone: Invoice sin PS (consumibles, solventes, servicios)

    -- Referencia al Packing Slip (NULL para standalone)
    packing_slip_id             BIGINT UNSIGNED NULL,
    -- UNIQUE parcial: un PS solo puede tener un Invoice de tipo product
    -- La constraint se implementa a nivel de servicio + indice unico parcial

    -- Snapshot de datos del cliente (inmutable al emitir)
    fob_location                VARCHAR(100)    NOT NULL DEFAULT 'Tecate, Ca.',
    sold_to_name                VARCHAR(200)    NOT NULL DEFAULT 'S.E.I.P., Inc.',
    sold_to_address             TEXT            NOT NULL,
    shipped_to_name             VARCHAR(200)    NOT NULL DEFAULT 'S.E.I.P., Inc.',
    shipped_to_address          TEXT            NOT NULL,

    -- Cargos fijos (snapshot editable en estado draft; bloqueado en issued/paid)
    charge_machine_maintenance  DECIMAL(10,2)   NOT NULL DEFAULT 1200.00,
    -- NOTA: Actualizado a $1,200 segun Invoice #01006 (era $800 en Excel 2025)
    charge_administration_fee   DECIMAL(10,2)   NOT NULL DEFAULT 250.00,
    charge_shipping_cost        DECIMAL(10,2)   NOT NULL DEFAULT 450.00,

    -- Totales calculados (desnormalizados para rendimiento)
    total_quantity              INT             UNSIGNED NULL,
    subtotal_items              DECIMAL(14,2)   NULL,
    subtotal_charges            DECIMAL(10,2)   NULL,
    grand_total                 DECIMAL(14,2)   NULL,

    -- Control y auditoria
    notes                       TEXT            NULL,
    issued_at                   TIMESTAMP       NULL,
    paid_at                     TIMESTAMP       NULL,
    created_by                  BIGINT UNSIGNED NULL,
    issued_by                   BIGINT UNSIGNED NULL,

    created_at                  TIMESTAMP       NULL,
    updated_at                  TIMESTAMP       NULL,
    deleted_at                  TIMESTAMP       NULL,   -- SoftDeletes

    -- Constraints de FK
    CONSTRAINT fk_invoices_packing_slip
        FOREIGN KEY (packing_slip_id) REFERENCES packing_slips(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_invoices_issued_by
        FOREIGN KEY (issued_by) REFERENCES users(id)
        ON DELETE SET NULL,

    -- Indices de rendimiento
    UNIQUE  INDEX idx_inv_number       (invoice_number),
    INDEX         idx_inv_packing_slip (packing_slip_id),
    INDEX         idx_inv_date         (invoice_date),
    INDEX         idx_inv_status       (status),
    INDEX         idx_inv_type         (type)
);
```

### 7.2 Tabla `invoice_items`

```sql
CREATE TABLE invoice_items (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- FK al Invoice padre (CASCADE: si se elimina el Invoice, se eliminan sus items)
    invoice_id              BIGINT UNSIGNED NOT NULL,

    -- Referencia al PackingSlipItem origen (nullable: los cargos fijos no tienen PSItem)
    packing_slip_item_id    BIGINT UNSIGNED NULL,

    -- Referencias de navegacion al origen del item (nullable; SET NULL si se elimina)
    lot_id                  BIGINT UNSIGNED NULL,
    work_order_id           BIGINT UNSIGNED NULL,
    purchase_order_id       BIGINT UNSIGNED NULL,
    part_id                 BIGINT UNSIGNED NULL,

    -- Snapshot inmutable del item al momento de facturacion
    description             VARCHAR(255)    NOT NULL,
    -- Para cargos fijos: 'Machine Maintenance', 'Administration Fee', 'SHIPPING COST'
    item_number             VARCHAR(100)    NULL,
    -- NULL para cargos fijos
    lot_number              VARCHAR(30)     NULL,
    -- Formato: MMDDYY + 'x' + sufijo ('01' o '20'). NULL para cargos fijos.
    -- Ejemplo: '030926x01' = 9 de marzo de 2026, workstation tipo table
    po_number               VARCHAR(50)     NULL,
    -- NULL para cargos fijos
    wo_number               VARCHAR(50)     NULL,
    -- Formato: 7 digitos del external_wo_number puro (sin W0 ni sufijo 001)
    -- NULL para cargos fijos

    -- Cantidades y precios
    quantity                INT UNSIGNED    NOT NULL DEFAULT 1,
    unit_cost               DECIMAL(10,4)   NOT NULL,
    -- 4 decimales: precision para precios como 0.1796, 0.0935, 0.2712
    line_total              DECIMAL(12,2)   NOT NULL,
    -- Calculado: bcmul(quantity, unit_cost) redondeado a 2 decimales

    -- Clasificacion y orden
    sort_order              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_fixed_charge         TINYINT(1)      NOT NULL DEFAULT 0,
    -- 1 (TRUE) para Machine Maintenance, Admin Fee, Shipping Cost
    -- 0 (FALSE) para items de producto

    created_at              TIMESTAMP       NULL,
    updated_at              TIMESTAMP       NULL,

    -- Constraints de FK
    CONSTRAINT fk_ii_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ii_psi
        FOREIGN KEY (packing_slip_item_id) REFERENCES packing_slip_items(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_ii_lot
        FOREIGN KEY (lot_id) REFERENCES lots(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_ii_wo
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_ii_po
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_ii_part
        FOREIGN KEY (part_id) REFERENCES parts(id)
        ON DELETE SET NULL,

    -- Indices de rendimiento
    INDEX idx_ii_invoice    (invoice_id),
    INDEX idx_ii_psi        (packing_slip_item_id),
    INDEX idx_ii_sort       (invoice_id, sort_order)
);
```

### 7.3 Modificacion a `packing_slips` — Campo `invoice_id`

```sql
-- Migracion separada (debe ejecutarse DESPUES de create_invoices_table)
ALTER TABLE packing_slips
    ADD COLUMN invoice_id BIGINT UNSIGNED NULL
    AFTER shipped_by
    COMMENT 'Invoice generado para este PS. NULL si aun no se genero el Invoice.';

ALTER TABLE packing_slips
    ADD CONSTRAINT fk_ps_invoice
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
    ON DELETE SET NULL;

CREATE INDEX idx_ps_invoice_id ON packing_slips (invoice_id);
```

**Nota sobre referencia circular:** La FK en `packing_slips.invoice_id` apunta a `invoices.id`, y la FK en `invoices.packing_slip_id` apunta a `packing_slips.id`. Esto forma una referencia bidireccional que es correcta y comun en documentos relacionados. El orden de migraciones garantiza que no hay deadlock:
1. `create_invoices_table` — crea `invoices` con FK a `packing_slips` (que ya existe)
2. `create_invoice_items_table` — crea `invoice_items` con FK a `invoices`
3. `add_invoice_id_to_packing_slips_table` — agrega FK en `packing_slips` a `invoices`

---

## 8. Plan de Implementacion por Fases

### FASE 5: Infraestructura (Base de Datos + Modelos + Config)

**Prerequisito:** Packing Slip completamente operativo (COMPLETADO). Campos `unit_price`, `price_tier_id`, `price_source` en `packing_slip_items` existentes (CONFIRMADO).

**Duracion estimada:** 1-2 dias

#### Paso 5.1 — Migracion: `create_invoices_table`

```
Archivo: database/migrations/YYYY_MM_DD_HHMMSS_create_invoices_table.php
Crear la tabla `invoices` con la estructura de la seccion 7.1.
Comando: php artisan make:migration create_invoices_table
```

#### Paso 5.2 — Migracion: `create_invoice_items_table`

```
Archivo: database/migrations/YYYY_MM_DD_HHMMSS_create_invoice_items_table.php
Crear la tabla `invoice_items` con la estructura de la seccion 7.2.
Ejecutar DESPUES de 5.1.
Comando: php artisan make:migration create_invoice_items_table
```

#### Paso 5.3 — Migracion: `add_invoice_id_to_packing_slips_table`

```
Archivo: database/migrations/YYYY_MM_DD_HHMMSS_add_invoice_id_to_packing_slips_table.php
Agregar campo invoice_id a packing_slips (seccion 7.3).
Ejecutar DESPUES de 5.1.
Comando: php artisan make:migration add_invoice_id_to_packing_slips_table
```

#### Paso 5.4 — Archivo de configuracion: `config/invoice.php`

```php
// config/invoice.php
return [
    'number_format' => [
        'digits'       => 5,
        'pad_char'     => '0',
        'first_number' => 1,
        // DECISION P-12-03 RESUELTA (D-12-23): el sistema arranca desde #00001.
        // Los Invoices de Excel (#01006 y anteriores) son documentos externos
        // y no se migran al sistema. La separacion de series evita confusion de auditoria.
        // Formato generado: str_pad($next, 5, '0', STR_PAD_LEFT) → '00001', '00002', etc.
    ],
    // NOTA: La seccion 'charges' fue ELIMINADA (ver Decision P-12-02 / D-12-22).
    // Los montos de los cargos fijos (Machine Maintenance, Administration Fee, Shipping Cost)
    // se gestionan en la tabla `invoice_charge_types` (catalogo administrable por el Admin).
    // El `default_amount` de cada registro es la fuente de verdad, editable desde la UI
    // sin necesidad de modificar este archivo de configuracion ni hacer despliegues.
    'issuer' => [
        'name'    => 'FLEXCON',
        'address' => '330 Rocky Woods Lane - Bigfork, Montana - 59911',
        'phone'   => 'PH# 425-466-2184',
        'email'   => 'frank@flexconinc.com',
        // NOTA: email diferente al del PS (Frank@flexconinc.com vs frank@flexconinc.com)
    ],
    'sold_to' => [
        'name'    => 'S.E.I.P., Inc.',
        'address' => '915 Armorlite Dr.',
        'city'    => 'San Marcos, Ca. 92069',
    ],
    'shipped_to' => [
        'name'    => 'S.E.I.P., Inc.',
        'address' => '915 Armorlite Dr.',
        'city'    => 'San Marcos, Ca. 92069',
    ],
    'fob_location' => 'Tecate, Ca.',
];
```

#### Paso 5.5 — Modelo `Invoice`

```
Archivo: app/Models/Invoice.php

Constantes:
  STATUS_DRAFT    = 'draft'
  STATUS_ISSUED   = 'issued'
  STATUS_PAID     = 'paid'
  TYPE_PRODUCT    = 'product'
  TYPE_STANDALONE = 'standalone'

$fillable: [invoice_number, invoice_date, lot_no, status, type, packing_slip_id,
            fob_location, sold_to_name, sold_to_address, shipped_to_name,
            shipped_to_address, charge_machine_maintenance,
            charge_administration_fee, charge_shipping_cost,
            total_quantity, subtotal_items, subtotal_charges, grand_total,
            notes, issued_at, paid_at, created_by, issued_by]
-- NOTA: lot_no es mutable (D-12-21). No omitir de $fillable aunque el Invoice este issued.

$casts: [
    'invoice_date'              => 'date',
    'issued_at'                 => 'datetime',
    'paid_at'                   => 'datetime',
    'charge_machine_maintenance'=> 'decimal:2',
    'charge_administration_fee' => 'decimal:2',
    'charge_shipping_cost'      => 'decimal:2',
    'subtotal_items'            => 'decimal:2',
    'subtotal_charges'          => 'decimal:2',
    'grand_total'               => 'decimal:2',
]

SoftDeletes: SI

Relaciones:
  - hasMany(InvoiceItem::class)
  - belongsTo(PackingSlip::class)
  - belongsTo(User::class, 'created_by') → creator()
  - belongsTo(User::class, 'issued_by')  → issuer()

Boot (auto-generacion del invoice_number):
  static::creating(function(Invoice $inv) {
      if (empty($inv->invoice_number)) {
          $inv->invoice_number = static::generateInvoiceNumber();
      }
  });

Metodos estaticos:
  generateInvoiceNumber(): string
    - Busca el ultimo invoice_number (withTrashed, todos los tipos)
    - Si no hay registros: usa config('invoice.number_format.first_number') + 1
    - Suma 1 al ultimo numero y formatea con str_pad($next, 5, '0', STR_PAD_LEFT)

Metodos de instancia:
  calculateTotals(): static
    - total_quantity  = sum(invoice_items.quantity WHERE is_fixed_charge = false)
    - subtotal_items  = sum(invoice_items.line_total WHERE is_fixed_charge = false)
    - subtotal_charges = charge_machine_maintenance + charge_administration_fee + charge_shipping_cost
    - grand_total     = subtotal_items + subtotal_charges

  canBeModified(): bool
    return $this->status === self::STATUS_DRAFT

  isPdfAvailable(): bool
    return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_PAID])

  isDraft():  bool
  isIssued(): bool
  isPaid():   bool

  getStatusLabelAttribute(): string
  getStatusColorAttribute(): string (tailwind color: yellow/green/blue)

  getRouteKeyName(): string → return 'invoice_number'
  getRouteKey(): string → return $this->invoice_number (sin strtolower, es numerico)
```

#### Paso 5.6 — Modelo `InvoiceItem`

```
Archivo: app/Models/InvoiceItem.php

$fillable: [invoice_id, packing_slip_item_id, lot_id, work_order_id,
            purchase_order_id, part_id, description, item_number,
            lot_number, po_number, wo_number, quantity, unit_cost,
            line_total, sort_order, is_fixed_charge]

$casts: [
    'unit_cost'        => 'decimal:4',
    'line_total'       => 'decimal:2',
    'is_fixed_charge'  => 'boolean',
    'quantity'         => 'integer',
    'sort_order'       => 'integer',
]

Relaciones:
  - belongsTo(Invoice::class)
  - belongsTo(PackingSlipItem::class) [nullable]
  - belongsTo(Lot::class)->withTrashed() [nullable]
  - belongsTo(WorkOrder::class)->withTrashed() [nullable]
  - belongsTo(PurchaseOrder::class)->withTrashed() [nullable]
  - belongsTo(Part::class)->withTrashed() [nullable]

Metodo auxiliar:
  recalculateLineTotal(): void
    - $this->line_total = round((float)bcmul((string)$this->quantity, (string)$this->unit_cost, 4), 2)
    - $this->save()
```

#### Paso 5.7 — Modificar modelo `PackingSlip`

```
Archivo: app/Models/PackingSlip.php (MODIFICAR)

Agregar en $fillable:
  'invoice_id'

Agregar en $casts:
  'invoice_id' => 'integer'

Agregar relacion:
  public function invoice(): HasOne
  {
      return $this->hasOne(Invoice::class);
  }

Agregar metodo:
  public function hasInvoice(): bool
  {
      return $this->invoice_id !== null;
  }
```

---

### FASE 6: Logica de Negocio (Servicios + PDF)

**Prerequisito:** Fase 5 completada y ejecutadas las migraciones.

**Duracion estimada:** 2-3 dias

#### Paso 6.1 — Servicio `InvoiceFromPackingSlipService`

```
Archivo: app/Services/InvoiceFromPackingSlipService.php

Responsabilidades:
1. Recibe PackingSlip model con items cargados.
2. Validaciones previas a la transaccion:
   a. PS debe estar en status='shipped' → Exception si no
   b. PS no debe tener invoice_id ya asignado → Exception si ya existe
3. DB::transaction():
   a. Crea el registro en invoices:
      - invoice_date = now()->toDateString()
        (O pedir al usuario la fecha: PENDIENTE P-12-01)
      - invoice_number = Invoice::generateInvoiceNumber()
      - packing_slip_id = $ps->id
      - sold_to_*, shipped_to_*, fob_location desde config('invoice.sold_to.*')
      - charge_machine_maintenance = config('invoice.charges.machine_maintenance')
      - charge_administration_fee  = config('invoice.charges.administration_fee')
      - charge_shipping_cost       = config('invoice.charges.shipping_cost')
      - status = 'draft', type = 'product'
      - created_by = Auth::id()
   b. Por cada PackingSlipItem del PS (con eager loading lot.workOrder.purchaseOrder.part):
      - $part = $item->lot->workOrder->purchaseOrder->part
      - $price = Price::getActivePriceForPart($part->id)
      - $po = $item->lot->workOrder->purchaseOrder
      - $unitCost = $price ? $price->getPriceForQuantity($po->quantity) : 0
      - $priceSource = determinar('tier'|'sample'|'manual')
      - $tier = encontrar el tier que se aplico (o null)
      - Derivar $lotNumber: MMDDYY desde $ps->shipped_at + 'x' + sufijo_tipo_estacion
      - Derivar $woNumber: $item->lot->workOrder->external_wo_number (solo digitos)
      - Crear InvoiceItem con todos los snapshots
      - Actualizar packing_slip_items: unit_price, price_tier_id, price_source
   c. Crear 3 InvoiceItems de cargos fijos:
      - Machine Maintenance, is_fixed_charge = true, sort_order = N+1
      - Administration Fee,  is_fixed_charge = true, sort_order = N+2
      - SHIPPING COST,       is_fixed_charge = true, sort_order = N+3
   d. $invoice->calculateTotals()->save()
   e. $ps->update(['invoice_id' => $invoice->id])
4. Retorna el Invoice creado

Logica de derivacion del LOT NO.:
  $shippedAt = Carbon::parse($ps->shipped_at)
  $dateComponent = $shippedAt->format('m') .
                   $shippedAt->format('d') .
                   $shippedAt->format('y')  // MMDDYY
  $workstationType = $price?->workstation_type ?? null
  $suffix = match($workstationType) {
      'table'          => '01',
      'machine'        => '20',
      'semi_automatic' => '20',
      default          => null,  // Si NULL: lot_number queda NULL y usuario debe ingresar manualmente
  }
  $lotNumber = $suffix ? $dateComponent . 'x' . $suffix : null

Manejo de partes sin precio:
  Si $price === null:
    - unit_cost = 0.0000
    - price_source = 'manual'
    - Registrar en un array $itemsWithoutPrice para retornarlos al llamador
  El Invoice se crea en estado 'draft' siempre.
  La vista InvoiceCreate mostrara un banner de advertencia si algun item tiene unit_cost = 0.
```

#### Paso 6.2 — Servicio `InvoicePdfService`

```
Archivo: app/Services/InvoicePdfService.php

Responsabilidades:
1. Recibe Invoice model con items cargados.
2. Valida que el Invoice este en status='issued' o 'paid'.
3. Prepara el array de datos para la vista:
   - $invoice con todos sus campos
   - $productItems = $invoice->items->where('is_fixed_charge', false)->sortBy('sort_order')
   - $fixedChargeItems = $invoice->items->where('is_fixed_charge', true)->sortBy('sort_order')
   - $logoPath = public_path('flexcon.png')
4. Renderiza Pdf::loadView('pdf.invoice', $data)->setPaper('letter', 'portrait')
5. Retorna Response::stream() o Response::download()

Nombre del archivo: 'invoice-' . $invoice->invoice_number . '.pdf'
Ejemplo: 'invoice-01006.pdf'
```

#### Paso 6.3 — Vista Blade del PDF del Invoice

```
Archivo: resources/views/pdf/invoice.blade.php

Estructura (replicando exactamente el PDF FPL-12 visto):
- Sin etiquetas de navegacion ni Livewire
- Papel carta 8.5x11", portrait
- Encabezado fijo (position:fixed, top:0):
  Banner azul oscuro:
    LEFT:  Logo FLEXCON (imagen)
    CENTER: "FLEXCON" (grande) + "INVOICE" (mas grande)
    RIGHT: "Clave: FPL-12" + "Revision: 01"
  Barra de direccion: "330 Rocky Woods Lane • Bigfork, Montana • 59911"
  Barra de contacto: "PH# 425-466-2184" | "frank@flexconinc.com"
  Bloque de cliente (tabla 2 columnas + columna derecha):
    LEFT:  "Sold to:" + $invoice->sold_to_name + direccion
    CENTER: "Shipped to:" + $invoice->shipped_to_name + direccion
    RIGHT: "Packing Slip #" + $invoice->packingSlip->ps_number +
           "F.O.B: " + $invoice->fob_location
  Barra de Invoice:
    LEFT:  "Invoice#" + $invoice->invoice_number (en rojo, fuente grande)
    RIGHT: "DATE: " + $invoice->invoice_date->format('F-d-Y')

- Pie de pagina fijo (position:fixed, bottom:0):
  "X de Y" | "FPL-12  Rev 01"

- Tabla de items (#main-content, margin-top para compensar header):
  Columnas (8): DESCRIPTION | Item No. | LOT NO. | P.O No. | W.O No. | QUANTITY | UNIT COST | TOTAL
  - Filas de producto (is_fixed_charge = false):
    Sin agrupacion por PO (lista continua, a diferencia del PS)
    Ordenadas por sort_order
    TOTAL alineado a la derecha con 2 decimales: number_format($line->line_total, 2)
  - Filas de cargos fijos (is_fixed_charge = true):
    DESCRIPTION centrado spanning cols 1-5 (o con colspan)
    UNIT COST = importe del cargo
    TOTAL = mismo importe del cargo
  - Fila GRAND TOTAL:
    Spanning DESCRIPTION-QUANTITY con texto "GRAND TOTAL:"
    QUANTITY = number_format($invoice->total_quantity)
    TOTAL = "$" . number_format($invoice->grand_total, 2)

Notas de implementacion CSS (dompdf):
  - Usar display:table para todos los layouts (no flexbox ni grid)
  - Fuente: Arial 8pt para items, 9pt para encabezados de columna
  - Color azul oscuro del banner: #1e3a5f (mismo que el PS)
  - "Invoice#NNNNN" en rojo: color #cc0000 o #e53e3e
  - Anchos de columna estimados (para 8.5" con margenes de 0.5"):
    DESCRIPTION: 22%
    Item No.:     10%
    LOT NO.:       9%
    P.O No.:       7%
    W.O No.:       7%
    QUANTITY:      9% (text-align: right)
    UNIT COST:    10% (text-align: right)
    TOTAL:        10% (text-align: right)
```

---

### FASE 7: Interfaz de Usuario (Livewire + Rutas)

**Prerequisito:** Fase 6 completada.

**Duracion estimada:** 3-4 dias

#### Paso 7.1 — Componente `InvoiceList`

```
Archivo PHP:  app/Livewire/Admin/Invoices/InvoiceList.php
Vista Blade:  resources/views/livewire/admin/invoices/invoice-list.blade.php
Ruta:         GET /admin/invoices
Nombre ruta:  admin.invoices.index

Funcionalidad:
- Listado paginado (15 por pagina) de todos los Invoices
- Columnas: Invoice #, Fecha, PS Asociado, Items, Cantidad Total, Grand Total, Estado, Acciones
- Filtros: por estado (all/draft/issued/paid), busqueda por numero de Invoice o PS
- Acciones por fila: Ver detalle (siempre), Descargar PDF (si issued/paid)
- Stats en tarjetas: total Invoices, draft, issued, paid
- Ordenamiento por columna (invoice_number, invoice_date, grand_total)
```

#### Paso 7.2 — Componente `InvoiceShow`

```
Archivo PHP:  app/Livewire/Admin/Invoices/InvoiceShow.php
Vista Blade:  resources/views/livewire/admin/invoices/invoice-show.blade.php
Ruta:         GET /admin/invoices/{invoice}
Nombre ruta:  admin.invoices.show

Funcionalidad (modo draft):
- Vista del Invoice con todos los campos
- Tabla de items con columna UNIT COST editable via Alpine.js
  - Cambio de unit_cost recalcula line_total en tiempo real
  - Al hacer blur en un campo, dispara updateUnitCost(itemId, newValue) en Livewire
- Cargos fijos editables: Machine Maintenance, Admin Fee, Shipping Cost
- Banner de advertencia si algun item tiene unit_cost = 0 (parte sin precio)
- Grand Total recalculado en tiempo real desde Alpine.js
- Botones: "Emitir Invoice" (con confirmacion modal) + "Cancelar/Eliminar"
- Enlace al Packing Slip de origen

Funcionalidad (modo issued/paid):
- Vista completamente readonly
- Boton "Descargar PDF" prominente
- Boton "Ver Packing Slip PS-YYYY-NNNN"
- Badge de estado (verde: issued, azul: paid)

Metodos Livewire:
- updateUnitCost(int $itemId, string $newUnitCost): void
  - Valida que el Invoice este en draft
  - Actualiza invoice_items.unit_cost y recalcula line_total con bcmul()
  - Llama $invoice->calculateTotals()->save()
- updateFixedCharge(string $field, string $value): void
  - Actualiza el cargo fijo en la tabla invoices
  - Recalcula totales
- issueInvoice(): void
  - Valida que no haya items con unit_cost = 0
  - status = 'issued', issued_at = now(), issued_by = Auth::id()
  - dispatch('notify', success)
  - redirect a la misma pagina (ahora en modo readonly)

- updateLotNo(string $newLotNo): void   [TAREA NUEVA — ver D-12-21 y seccion 4.1.1]
  - Solo accesible para usuarios con rol admin
  - Valida formato: regex:/^\d{6}x(01|20)$/
  - Actualiza invoices.lot_no
  - Actualiza invoice_items.lot_number en todos los items de producto del Invoice
    (conserva el sufijo x01/x20 de cada item individual; solo actualiza el componente de fecha MMDDYY)
  - Registra en log de actividad: "LOT NO. cambiado de {anterior} a {nuevo}"
  - El PDF NO se regenera automaticamente (se genera bajo demanda al descargar)
  - dispatch('notify', ['type' => 'success', 'message' => 'LOT NO. actualizado'])
```

**Sub-tarea 7.2.A — UI de edicion inline del LOT NO.:**
```
En invoice-show.blade.php, en la fila del encabezado donde se muestra el LOT NO.:

  @if(auth()->user()->hasRole('admin'))
    <!-- Editor inline Alpine.js -->
    <div x-data="{ editing: false, value: '{{ $invoice->lot_no }}' }">
      <span x-show="!editing" class="font-mono">{{ $invoice->lot_no ?? '(sin lote)' }}</span>
      <button x-show="!editing" @click="editing = true" class="text-gray-400 hover:text-blue-600">
        [icono lapiz]
      </button>
      <div x-show="editing" class="flex items-center gap-2">
        <input type="text" x-model="value" maxlength="10"
               placeholder="ej: 030926x01"
               class="font-mono border rounded px-2 py-0.5 text-sm w-32" />
        <button @click="$wire.updateLotNo(value); editing = false"
                class="text-green-600 text-sm">Guardar</button>
        <button @click="editing = false" class="text-gray-500 text-sm">Cancelar</button>
      </div>
    </div>
  @else
    <span class="font-mono">{{ $invoice->lot_no ?? '—' }}</span>
  @endif
```

#### Paso 7.3 — Controlador `InvoiceController`

```
Archivo: app/Http/Controllers/InvoiceController.php

Metodos:
  createFromPackingSlip(PackingSlip $packingSlip): RedirectResponse
    - Valida que el PS este shipped y no tenga Invoice
    - Llama InvoiceFromPackingSlipService::createFromPackingSlip($packingSlip)
    - Redirige a admin.invoices.show con flash de exito

  downloadPdf(Invoice $invoice): Response
    - Valida que invoice->isPdfAvailable() (issued o paid)
    - Llama InvoicePdfService::download($invoice)
    - Retorna la respuesta de descarga del PDF

  streamPdf(Invoice $invoice): Response
    - Igual que downloadPdf pero retorna stream (preview en el navegador)
```

#### Paso 7.4 — Rutas del modulo Invoice

En `routes/admin.php`, agregar dentro del grupo `role:admin|Empaques`:

```php
// Gestion de Invoices (FPL-12)
Route::get('/invoices', \App\Livewire\Admin\Invoices\InvoiceList::class)
    ->name('invoices.index');

Route::get('/invoices/{invoice}', \App\Livewire\Admin\Invoices\InvoiceShow::class)
    ->name('invoices.show');

// Accion de creacion (POST desde PackingSlipShow)
Route::post('/invoices/from-packing-slip/{packingSlip}',
    [\App\Http\Controllers\InvoiceController::class, 'createFromPackingSlip'])
    ->name('invoices.create-from-ps');

// PDF del Invoice
Route::get('/invoices/{invoice}/pdf',
    [\App\Http\Controllers\InvoiceController::class, 'streamPdf'])
    ->name('invoices.pdf');

Route::get('/invoices/{invoice}/pdf/download',
    [\App\Http\Controllers\InvoiceController::class, 'downloadPdf'])
    ->name('invoices.pdf.download');
```

**Nota sobre Route Model Binding:** El modelo `Invoice` debe implementar `getRouteKeyName()` retornando `'invoice_number'`, y `resolveRouteBinding()` para busqueda case-insensitive (aunque el invoice_number es numerico, la consistencia con el PS es recomendable).

#### Paso 7.5 — Modificacion a `PackingSlipShow`

En el componente `PackingSlipShow` (PHP + Blade), agregar en la seccion del estado `shipped`:

```
Condicion: $packingSlip->isShipped()

Si $packingSlip->hasInvoice() === false:
  Mostrar boton prominente:
    "Crear Invoice FPL-12"
    → POST a route('admin.invoices.create-from-ps', $packingSlip)
    → Color: verde oscuro o azul
    → Con confirmacion modal: "¿Crear Invoice para PS #XXXX?"

Si $packingSlip->hasInvoice() === true:
  Mostrar tarjeta/panel con:
    "Invoice Generado"
    Invoice # NNNNN
    Estado: draft | issued | paid (badge con color)
    Enlace "Ver Invoice #NNNNN" → route('admin.invoices.show', $invoice->invoice_number)
    Si issued/paid: boton "Descargar PDF"
```

---

### FASE 8: Casos Especiales y Refinamientos

**Prerequisito:** Fases 5-7 completadas y respuestas del cliente a P-12-01, P-12-02, P-12-03.

**Elementos a incluir:**
- Importacion historica de Invoices #00954 a #01005 (si el cliente lo confirma)
- Invoice standalone para consumibles/solventes (sin PS)
- Tests de integracion del flujo completo PS → Invoice → PDF
- Permisos granulares (`invoice.view`, `invoice.create`, `invoice.issue`, `invoice.manage`)

---

## 9. Impacto en Codigo Existente

### 9.1 Archivos que se MODIFICAN

| Archivo | Tipo de cambio | Descripcion |
|---|---|---|
| `app/Models/PackingSlip.php` | Modificacion menor | Agregar `invoice_id` a `$fillable`, relacion `invoice()`, metodo `hasInvoice()` |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | Modificacion media | Agregar logica para mostrar boton Crear Invoice o enlace al Invoice existente |
| `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php` | Modificacion media | Agregar seccion condicional de Invoice en el panel de estado `shipped` |
| `routes/admin.php` | Modificacion menor | Agregar 5 rutas del modulo Invoice |

### 9.2 Archivos que NO se modifican

| Archivo | Razon |
|---|---|
| `app/Models/PackingSlipItem.php` | Los campos `unit_price`, `price_tier_id`, `price_source` ya existen y ya tienen los metodos. Se llenan en el Servicio. |
| `app/Models/Price.php` | Ya tiene `getActivePriceForPart()` y `getPriceForQuantity()`. No requiere cambios. |
| `app/Models/PriceTier.php` | Ya tiene `matchesQuantity()`. No requiere cambios. |
| `database/migrations/2026_03_08_100003_create_packing_slip_items_table.php` | Los campos de precio ya existen desde Fase 1. |
| `app/Http/Controllers/PackingSlipPdfController.php` | El PDF del PS no cambia. El Invoice tiene su propio controlador. |
| `resources/views/pdf/packing-slip.blade.php` | El PDF del PS no cambia. El Invoice tiene su propia vista Blade. |

### 9.3 Archivos NUEVOS a crear

| Archivo | Tipo | Fase |
|---|---|---|
| `database/migrations/XXXX_create_invoices_table.php` | Migracion | 5 |
| `database/migrations/XXXX_create_invoice_items_table.php` | Migracion | 5 |
| `database/migrations/XXXX_add_invoice_id_to_packing_slips_table.php` | Migracion | 5 |
| `config/invoice.php` | Configuracion | 5 |
| `app/Models/Invoice.php` | Modelo Eloquent | 5 |
| `app/Models/InvoiceItem.php` | Modelo Eloquent | 5 |
| `app/Services/InvoiceFromPackingSlipService.php` | Servicio | 6 |
| `app/Services/InvoicePdfService.php` | Servicio | 6 |
| `resources/views/pdf/invoice.blade.php` | Vista Blade PDF | 6 |
| `app/Livewire/Admin/Invoices/InvoiceList.php` | Livewire component | 7 |
| `resources/views/livewire/admin/invoices/invoice-list.blade.php` | Vista Livewire | 7 |
| `app/Livewire/Admin/Invoices/InvoiceShow.php` | Livewire component | 7 |
| `resources/views/livewire/admin/invoices/invoice-show.blade.php` | Vista Livewire | 7 |
| `app/Http/Controllers/InvoiceController.php` | Controller HTTP | 7 |
| `tests/Unit/Models/InvoiceTest.php` | Test unitario | 5 |
| `tests/Unit/Models/InvoiceItemTest.php` | Test unitario | 5 |
| `tests/Feature/InvoiceFromPackingSlipServiceTest.php` | Test de integracion | 6 |

---

## 10. Decisiones Pendientes del Cliente

Estas preguntas deben ser respondidas **antes de iniciar la implementacion** de la fase correspondiente.

| ID | Pregunta | Impacto | Fase bloqueada | Prioridad |
|---|---|---|---|---|
| **P-12-01** | La fecha del LOT NO. en el Invoice, ¿es la fecha de `packing_slips.shipped_at` (fecha del despacho fisico del PS) o la fecha de produccion del lote?
| **P-12-01 Respuesta** | NO, en este caso cambia el 03 le pertence al mes el 09 al dia y 26 es el año, pero en el dia no se obtienen de la fecha del Packing Slip  mas bien lo agarra del primer lunes anterior al envio por ejemplo en este caso `0309` el `09` serial el primer lunes de fecha de envio al 18 por eso se botiene `030926` 
El PDF #01006 muestra LOT NO. = `030926` (9-Mar-2026) y el Invoice fue creado el 11-Mar-2026, lo que sugiere que la fecha es del despacho del PS. ¿Es correcto? Respuesta: No se pone el numero del dia el primer lunes antes del envio| Logica de derivacion del LOT NO. en `InvoiceFromPackingSlipService` | 6 | ALTA |
| **P-12-02** | Machine Maintenance en el Invoice #01006 es $1,200 vs $800 en todos los Invoices de 2025. ¿Es $1,200 el nuevo valor por defecto para todos los Invoices de 2026 en adelante? ¿Los valores de Administration Fee ($250) y SHIPPING COST ($450) tambien cambiaron o se mantienen? | Valor por defecto en `config/invoice.php` y en la tabla `invoices` | 5 | ALTA |
| **P-12-02 — RESUELTA** | El cliente confirma que Machine Maintenance puede variar en el tiempo y solicita una tabla separada para gestionar esta informacion, ya que el precio puede incrementar en el futuro. **Decision:** Machine Maintenance se gestiona como `invoice_charge_type` con `default_amount` mutable en la tabla `invoice_charge_types`. El `default_amount` inicial del seeder es $1,200 (valor del Invoice #01006, el mas reciente confirmado por PDF). El Admin puede modificarlo desde la UI de gestion de tipos de cargo sin tocar codigo. El historial de precios queda preservado en los snapshots de `invoice_items.unit_cost`. Ver D-12-22 y Seccion 15. |
| **P-12-03** | El Invoice #01006 usa el numero `01006`. ¿El proximo Invoice del sistema debe ser `01007`? ¿O existen Invoices emitidos fuera del sistema (en Excel) entre #00954 y #01005 que deben importarse primero para no colisionar con la numeracion? | `config/invoice.php` → `first_number` + posible importacion historica | 5 | ALTA |
| **P-12-03 — RESUELTA** | El cliente indica que el numero de inicio no es critico y lo deja a criterio del arquitecto. **Decision del arquitecto:** El sistema arranca desde **#00001**. Los Invoices emitidos en Excel (#01006 y anteriores) son documentos externos y no se migran al sistema. Separar las series evita confusion de auditoria entre documentos generados por el sistema y documentos historicos de Excel. En `config/invoice.php`: `first_number = 1`, formato `%05d` → genera `00001`. Ver D-12-23. |
| **P-12-04** | La fuente del `unit_cost` en el Invoice: ¿el precio viene del catalogo de partes (`prices` + `price_tiers` usando la cantidad total de la PO como referencia de tier)? ¿O el precio esta directamente en la PO (`purchase_orders.unit_price`) y el catalogo es solo de referencia? El analisis del Excel muestra que el precio de la misma parte varia entre Invoices, lo que es consistente con el uso del catalogo con tiers por volumen. | Logica central de calculo de precios en `InvoiceFromPackingSlipService` | 6 | ALTA |
|**P-12-4 Respuesta** | Si se confirma tu respuesta el `UNIT COST` viene del catalogo de partes que esta ligado a `prices` y la prengunta ¿O el precio esta directamente en la PO (`purchase_orders.unit_price`) y el catalogo es solo de referencia? No viene desde el catalogo Prices |
| **P-12-05** | ¿Quien tiene permiso para crear, editar y emitir un Invoice? Opciones: (a) solo Admin, (b) Admin + Shipping, (c) un rol nuevo de Facturacion. ¿Existe ya un rol de "Facturacion" en el sistema? | Middleware de rutas del modulo Invoice | 7 | MEDIA |
| **P-12-05 Respuesta** | Quien tendra los permisos para Inovices l respeusta es solo el administrador en este caso el gerente de la planta o el perfil que tenga ese acceso, ¿Existe ya un rol de "Facturacion" en el sistema? No aun no pero tal vez secree en el futuro, pero en esta face no esta considerado.
| **P-12-06** | Los cargos fijos (Machine Maintenance, Administration Fee, SHIPPING COST): ¿son siempre los mismos para todos los envios? ¿O pueden variar por envio especial? Si pueden variar, ¿quien tiene permiso para cambiarlos al crear el Invoice? | UI del `InvoiceShow` (si los cargos son editables o readonly) | 7 | MEDIA |
| **P-12-06 Respuesta** | Por el momenot seran fijos, pero existe la posibilidad que se cambien los costos y con respecto quien deveria tener acceso a esto solo el administrador o el que tenga este permiso o perfil 
| **P-12-07** | El Invoice standalone (sin PS, para consumibles/solventes como el Invoice #000944): ¿se genera con que frecuencia? ¿El sistema debe soportarlo desde el inicio o es una fase futura? | Scope del desarrollo — si se incluye en Fase 7 o se pospone a Fase 8 | 7 | MEDIA |
| **P-12-07 Respuesta** | No son muy frecuentes, esto lo vere con el cliente porque no estoy seguro como manejarlo, deja ponerlo como pendiente
| **P-12-08** | ¿Los 21 Invoices del Excel 2025 (#00932-#00953) y los Invoices entre #00954 y #01005 deben importarse al sistema como registros historicos? ¿Solo el encabezado o tambien todos los items y precios? | Fase 8: comando Artisan de importacion | 8 | BAJA |
| **P-12-08 Respuesta** | En el proyecto nunca se considero migrar la informacion de Exceles a la base de datos , asi que lo puedes poner como pendinete 


---

## 11. Decisiones Tecnicas Adoptadas (no requieren confirmacion del cliente)

| ID | Decision | Justificacion |
|---|---|---|
| D-12-01 | Tabla `invoices` separada de `packing_slips` | El Invoice tiene ciclo de vida, estados y datos financieros propios |
| D-12-02 | Tabla `invoice_items` separada de `packing_slip_items` | Los items del Invoice son snapshot financiero independiente |
| D-12-03 | Relacion 1:1 PS → Invoice para type=product | Confirmado por todos los documentos analizados |
| D-12-04 | `invoice_id` en `packing_slips` para navegacion bidireccional | Permite `$ps->hasInvoice()` sin consulta adicional |
| D-12-05 | Calculos monetarios con `bcmul()` para `line_total` | Evita errores de punto flotante con precios de 4 decimales × 100,000 piezas |
| D-12-06 | `DECIMAL(10,4)` para `unit_cost`; `DECIMAL(12,2)` para `line_total`; `DECIMAL(14,2)` para `grand_total` | Precision suficiente para los rangos observados (grand_total max ~$85,000) |
| D-12-07 | PDF generado con `barryvdh/laravel-dompdf` v3.1 ya instalado | Reutilizar la misma libreria del PS |
| D-12-08 | Nombre de la vista del PDF del Invoice: `resources/views/pdf/invoice.blade.php` | Consistencia con `pdf/packing-slip.blade.php` |
| D-12-09 | El campo LOT NO. usa formato `MMDDYY` + `x` + sufijo | Confirmado por analisis del PDF #01006 y Excel 2025 |
| D-12-10 | Sufijo del LOT NO.: `x01` para `workstation_type = 'table'`; `x20` para `machine` o `semi_automatic` | Confirmado por el diagrama de flujo `8-diagrama-flujo-invoice.mkd` |
| D-12-11 | El `wo_number` en `invoice_items` es solo los 7 digitos de `external_wo_number` | Confirmado por el PDF #01006 (muestra `2022465`, no `W02022465001`) |
| D-12-12 | Route Model Binding del Invoice usa `invoice_number` como clave de ruta | Consistencia con el patron del PS (`ps_number`) |
| D-12-13 | Los cargos fijos se guardan como snapshot en `invoices` (no solo en `invoice_items`) | Permite recalcular totales sin navegar items; facilita la edicion en draft |
| D-12-14 | Invoice `status='draft'` es editable (precios y cargos); `status='issued'` es readonly | Permite revision antes de emision; bloquea datos al emitir |
| D-12-15 | El status `paid` se incluye en el modelo pero no tiene flujo de UI en Fase 5-7 | Preparacion para fase futura sin bloquear el desarrollo actual |
| D-12-16 | La fecha del LOT NO. se calcula como el primer lunes anterior a `packing_slips.shipped_at` | Confirmado por el cliente en respuesta a P-12-01: el dia del lote es el primer lunes antes del envio |
| D-12-17 | El campo `lot_no` se agrega a la tabla `invoices` ademas de `invoice_items.lot_number` | `invoices.lot_no` representa el lote predominante del Invoice completo; permite edicion global desde un solo campo |
| D-12-18 | Solo el rol `admin` puede editar el `lot_no` | Consistente con P-12-05: todos los permisos de escritura en Invoice pertenecen al Administrador |
| D-12-19 | Al editar `lot_no`, se actualiza `invoice_items.lot_number` en todos los items de producto del mismo Invoice | Garantiza consistencia entre el encabezado del Invoice y sus lineas al generar el PDF |
| D-12-20 | El PDF del Invoice NO se regenera automaticamente al editar el `lot_no` | El PDF se genera bajo demanda en cada descarga; asi siempre refleja el estado actual sin necesidad de invalidar versiones cacheadas |
| D-12-21 | El campo `lot_no` en `invoices` es **mutable por diseno** y no se bloquea al emitir el Invoice (`status='issued'`) | El LOT NO. es un identificador de lote de produccion, no un dato financiero. Puede corregirse sin afectar los calculos monetarios del Invoice. Solo el Administrador puede editarlo. Ver subseccion 4.1.1 para el diseno completo. |
| D-12-22 | Machine Maintenance se gestiona como `invoice_charge_type` con `default_amount` mutable en la tabla `invoice_charge_types`, NO como constante de configuracion ni columna fija en `invoices` | El cliente confirmo (P-12-02) que el costo de Machine Maintenance puede variar en el tiempo y requiere una tabla separada editable. El `default_amount` inicial del seeder es $1,200 (Invoice #01006). Cada Invoice hace snapshot del monto vigente en el `unit_cost` del `InvoiceItem` correspondiente, preservando el historial exacto de lo cobrado. Cambiar el `default_amount` en el catalogo solo afecta Invoices futuros. |
| D-12-23 | La numeracion del sistema comienza en **#00001**, no en continuacion de la serie de Excel (#01007) | El cliente (P-12-03) deja la decision a criterio del arquitecto. Arrancar en #00001 establece una separacion clara entre documentos generados por el sistema FlexCon Tracker y documentos historicos emitidos en Excel. Evita confusion de auditoria sobre el origen de cada Invoice. Los Invoices de Excel (#01006 y anteriores) son documentos externos y no se migran. En `config/invoice.php`: `first_number = 1`. |

---

## 12. Riesgos y Consideraciones Tecnicas

### 12.1 Precision de Calculos Monetarios

Los precios unitarios tienen hasta 4 decimales (`0.1796`, `0.0935`, `0.2712`). Las cantidades son del orden de 100,000+ piezas. Ejemplo del PDF: `108,000 × 0.1796 = 19,396.80`.

En PHP con floats nativos: `108000 * 0.1796 = 19396.800000000002` (error de punto flotante).

**Mitigacion implementada:**
```php
$lineTotal = round((float) bcmul((string)$quantity, (string)$unitCost, 6), 2);
```
Usar `bcmul()` con 6 decimales de precision intermedios, luego redondear a 2 para almacenar en `line_total`. El `grand_total` se calcula sumando los `line_total` desde la BD (que ya tienen 2 decimales exactos) usando `$invoice->items()->sum('line_total')`.

### 12.2 Referencia Circular en Migraciones

`packing_slips.invoice_id` → `invoices.id`
`invoices.packing_slip_id` → `packing_slips.id`

**Orden obligatorio de ejecucion de migraciones:**
1. `create_invoices_table` (referencia `packing_slips` que ya existe desde `2026_03_08_100002`)
2. `create_invoice_items_table` (referencia `invoices`)
3. `add_invoice_id_to_packing_slips_table` (referencia `invoices`)

Los timestamps de las migraciones deben reflejar este orden. Laravel ejecuta migraciones en orden lexicografico por el timestamp del nombre del archivo.

### 12.3 Concurrencia al Crear el Invoice

Si dos usuarios intentan crear el Invoice del mismo PS simultaneamente, el UNIQUE en `invoices.invoice_number` y la validacion previa de `invoice_id IS NULL` en el PS pueden generar condicion de carrera.

**Mitigacion:** En `InvoiceFromPackingSlipService`, usar `SELECT ... FOR UPDATE` sobre el registro del PS antes de insertar:
```php
DB::transaction(function() use ($ps) {
    // Bloquear el registro del PS para actualizacion exclusiva
    $ps = PackingSlip::lockForUpdate()->findOrFail($ps->id);
    if ($ps->hasInvoice()) {
        throw new \Exception('Este Packing Slip ya tiene un Invoice asociado.');
    }
    // ... resto de la logica
});
```

### 12.4 Partes sin Precio en el Catalogo

Si una parte no tiene un precio activo en la tabla `prices`, `Price::getActivePriceForPart($partId)` retornara `null`. El servicio debe manejar este caso:
- Crear el `InvoiceItem` con `unit_cost = 0.0000` y `price_source = 'manual'`
- El Invoice queda en estado `draft`
- La vista `InvoiceShow` muestra una alerta: "X item(s) no tienen precio definido. Ingreselos manualmente antes de emitir el Invoice."
- El boton "Emitir Invoice" esta deshabilitado si hay items con `unit_cost = 0`

### 12.5 Compatibilidad del PDF con dompdf

El Invoice tiene 8 columnas vs 7 del PS. El espacio horizontal es mas apretado. Recomendaciones para la vista Blade del PDF:
- Fuente: 7.5pt para las celdas de datos (mismo que el PS)
- Reducir padding de celdas a `2px 4px`
- Las columnas numericas (QUANTITY, UNIT COST, TOTAL) deben ser `text-align: right`
- Probar con el caso de mayor volumen: Invoice con 31 items (observado en el Excel 2025)
- La fila "GRAND TOTAL" debe usar `page-break-inside: avoid` junto con las ultimas filas de cargos fijos

### 12.6 Email del Emisor — Diferencia Documentada

El PS usa `Frank@flexconinc.com` (mayuscula F) y el Invoice usa `frank@flexconinc.com` (minuscula f). Aunque a nivel de protocolo email son equivalentes, el documento oficial del cliente muestra `frank@flexconinc.com`. El `config/invoice.php` debe usar `frank@flexconinc.com`.

---

## 13. Resumen Ejecutivo de Implementacion

### Estado actual del proyecto

| Elemento | Estado | Notas |
|---|---|---|
| Packing Slip (FPL-10) | COMPLETADO | Tablas, modelos, componentes Livewire, PDF implementados |
| Campos de precio en `packing_slip_items` | LISTOS | `unit_price`, `price_tier_id`, `price_source` existen (NULL) |
| Sistema de precios (`Price` + `PriceTier`) | COMPLETO | `getActivePriceForPart()` y `getPriceForQuantity()` operativos |
| `barryvdh/laravel-dompdf` | INSTALADO | v3.1.1 en `composer.json` |
| Modelos de precio | COMPLETOS | Sin necesidad de modificacion |
| Tablas de Invoice | NO EXISTEN | Crear en Fase 5 |
| Modelo `Invoice` | NO EXISTE | Crear en Fase 5 |
| Modelo `InvoiceItem` | NO EXISTE | Crear en Fase 5 |

### Lo que bloquea el inicio de la Fase 5

**No hay bloqueantes criticos para la Fase 5.** Las tres preguntas que la condicionaban han sido resueltas:
- P-12-02 RESUELTA: Machine Maintenance se gestiona via `invoice_charge_types` (D-12-22).
- P-12-03 RESUELTA: el sistema arranca desde #00001 (D-12-23).
- P-12-12 RESUELTA: el seeder de `InvoiceChargeTypeSeeder` usa $1,200 como `default_amount` inicial.

La Fase 5 puede iniciarse inmediatamente.

### Lo que bloquea el inicio de la Fase 6

- **P-12-01**: Confirmar si la fecha del LOT NO. es `shipped_at` del PS (lo mas probable por el analisis del PDF)
- **P-12-04**: Confirmar si el precio viene del catalogo o de la PO directamente

### Metricas del modulo a implementar

| Dimension | Cantidad |
|---|---|
| Nuevas migraciones | 3 |
| Nuevos modelos | 2 (`Invoice`, `InvoiceItem`) |
| Modelos modificados | 1 (`PackingSlip`) |
| Nuevos servicios | 2 (`InvoiceFromPackingSlipService`, `InvoicePdfService`) |
| Nuevos componentes Livewire | 2 (`InvoiceList`, `InvoiceShow`) |
| Nuevos controladores | 1 (`InvoiceController`) |
| Nuevas rutas | 5 |
| Nuevas vistas Blade | 3 (PDF, list, show) |
| Archivos modificados | 3 (`PackingSlip.php`, `PackingSlipShow.php`, `packing-slip-show.blade.php`, `admin.php`) |
| Decisiones pendientes del cliente | 6 pendientes (P-12-01, P-12-02, P-12-03 RESUELTAS; P-12-04 a P-12-08 en proceso) |
| Decisiones tecnicas adoptadas | 23 (D-12-01 a D-12-23) |
| Campo adicional en `invoices` | 1 (`lot_no VARCHAR(20) NULL`, mutable, editable por Admin) |
| Metodo Livewire adicional en `InvoiceShow` | 1 (`updateLotNo()` con edicion inline Alpine.js) |

### Primer numero de Invoice del sistema

**DECISION P-12-03 RESUELTA (D-12-23):** El sistema arranca desde **#00001**. Los Invoices de Excel (#01006 y anteriores) son documentos externos. La separacion de series garantiza trazabilidad de auditoria clara: todo Invoice numerado en el sistema fue generado por el sistema.

---

## 14. Referencias de Archivos del Proyecto

| Archivo | Relevancia |
|---|---|
| `app/Models/PackingSlip.php` | Modelo base; agregar `invoice_id` y relacion `invoice()` |
| `app/Models/PackingSlipItem.php` | Campos `unit_price`, `price_tier_id`, `price_source` ya existen |
| `app/Models/Price.php` | `getActivePriceForPart()` y `getPriceForQuantity()` ya implementados |
| `app/Models/PriceTier.php` | `matchesQuantity()` ya implementado |
| `app/Models/Part.php` | `label_spec` ya implementado; necesario para derivar datos de linea |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | Agregar logica del boton "Crear Invoice" |
| `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php` | Agregar seccion condicional del Invoice |
| `resources/views/pdf/packing-slip.blade.php` | Referencia de layout para replicar estructura en el PDF del Invoice |
| `app/Http/Controllers/PackingSlipPdfController.php` | Referencia de patron para el `InvoiceController` |
| `routes/admin.php` | Agregar 5 rutas del modulo Invoice |
| `database/migrations/2026_03_08_100003_create_packing_slip_items_table.php` | Confirma que los campos de precio ya existen con los tipos correctos |
| `composer.json` | Confirma `barryvdh/laravel-dompdf` v3.1 instalado |
| `Diagramas_flujo/Estructura/docs/ef/FPL-12 Invoice #01006.pdf` | Documento real del Invoice; fuente definitiva de la estructura visual |
| `09_fpl12_invoice_analisis_implementacion.md` | Analisis previo completo con el plan de fases 5-8 |

---

---

## 15. Costos Adicionales Configurables — Diseno Extensible

**Version de la seccion:** 1.0
**Fecha:** 2026-03-17
**Motivacion:** El diseno inicial de la Fase 5 hardcodea tres cargos fijos como columnas en la tabla `invoices` y valores en `config/invoice.php`. Esta seccion analiza las limitaciones de ese enfoque y propone un diseno extensible mediante un catalogo administrable.

---

### 15.1 Situacion Actual de los Cargos Fijos

El diseno de Fase 5 (secciones 7.1 y 5.5 de este documento) define los tres cargos adicionales del Invoice de la siguiente manera:

#### Columnas en la tabla `invoices`

```sql
charge_machine_maintenance  DECIMAL(10,2)  NOT NULL DEFAULT 1200.00,
charge_administration_fee   DECIMAL(10,2)  NOT NULL DEFAULT 250.00,
charge_shipping_cost        DECIMAL(10,2)  NOT NULL DEFAULT 450.00,
```

#### Valores en `config/invoice.php`

```php
'charges' => [
    'machine_maintenance' => env('INVOICE_CHARGE_MACHINE_MAINTENANCE', 1200.00),
    'administration_fee'  => env('INVOICE_CHARGE_ADMINISTRATION_FEE', 250.00),
    'shipping_cost'       => env('INVOICE_CHARGE_SHIPPING_COST', 450.00),
],
```

#### Calculo en `Invoice::calculateTotals()`

```php
$subtotal_charges = $this->charge_machine_maintenance
                  + $this->charge_administration_fee
                  + $this->charge_shipping_cost;
```

#### Limitaciones estructurales del diseno actual

| Limitacion | Descripcion |
|---|---|
| **Cantidad fija** | Siempre son exactamente 3 cargos. No se pueden agregar ni quitar sin cambiar el codigo. |
| **Nombres en ingles hardcodeados** | Los identificadores `machine_maintenance`, `administration_fee`, `shipping_cost` estan grabados en columnas SQL, claves de config, modelos y vistas. Cambiar un nombre requiere una migracion ALTER TABLE. |
| **No hay catalogo auditable** | No existe registro de quien cambio un monto, cuando ni por que. |
| **Acoplamiento en el PDF** | La vista `pdf/invoice.blade.php` tendra que referenciar explicitamente las tres columnas: `$invoice->charge_machine_maintenance`, `$invoice->charge_administration_fee`, `$invoice->charge_shipping_cost`. Si se agrega un cuarto cargo, hay que modificar la vista. |
| **No hay soporte para cargos opcionales** | En el diseno actual no hay forma de omitir un cargo para un Invoice especifico sin poner el valor en 0 (que igual apareceria en la tabla del PDF). |

---

### 15.2 Analisis del Problema: Escenarios que Debe Soportar el Sistema

El cliente (FlexCon) emite Invoices a un unico cliente en la actualidad (S.E.I.P., Inc.), pero el negocio puede evolucionar. Se identifican los siguientes escenarios reales que el diseno actual NO puede manejar sin cambios de codigo:

#### Escenario A — Cambio de nombre de un concepto

> "Machine Maintenance" debe mostrarse como "Mantenimiento de Maquinaria" en algunas facturas.

Con el diseno actual: requiere una migracion `ALTER TABLE invoices RENAME COLUMN`, actualizacion del modelo, del config y de la vista del PDF.

Con el diseno extensible: se edita el campo `label` en la tabla `invoice_charge_types` desde la UI de administracion. El PDF lo lee dinamicamente.

#### Escenario B — Cambio de monto para Invoices futuros

> A partir del Invoice #01020, Machine Maintenance sube a $1,400.

Con el diseno actual: se actualiza el `.env` o `config/invoice.php`. El cambio afecta inmediatamente pero no hay registro de la transicion.

Con el diseno extensible: se edita `invoice_charge_types.default_amount`. Los Invoices anteriores conservan el valor capturado en su `invoice_items` correspondiente (snapshot historico intacto).

#### Escenario C — Nuevo cargo

> A partir de cierta fecha se agrega un "Quality Inspection Fee" de $175.

Con el diseno actual: requiere migracion (agregar columna), cambio en el modelo (`$fillable`, `$casts`, `calculateTotals()`), cambio en el servicio, cambio en la vista del PDF y cambio en el componente Livewire.

Con el diseno extensible: se inserta un nuevo registro en `invoice_charge_types` desde la UI de administracion. Sin tocar codigo.

#### Escenario D — Cargo opcional por envio

> El SHIPPING COST no aplica para envios dentro de la misma ciudad; algunos Invoices no lo deben incluir.

Con el diseno actual: se establece el campo en 0 pero aparece en el PDF con valor $0.00 (poco profesional).

Con el diseno extensible: el campo `always_include` en `invoice_charge_types` puede ser `false`, y al crear el Invoice el usuario decide incluirlo o no. Si no se incluye, no existe el `InvoiceItem` y no aparece en el PDF.

#### Escenario E — Cargos diferentes por cliente (futuro)

> Si FlexCon incorpora un segundo cliente con una estructura de cargos diferente.

Con el diseno actual: imposible sin una refactorizacion mayor.

Con el diseno extensible: se agrega un campo `customer_id` nullable en `invoice_charge_types` y se filtran los tipos activos por cliente al crear el Invoice.

---

### 15.3 Propuesta de Diseno: Tabla `invoice_charge_types`

La tabla `invoice_charge_types` actua como **catalogo configurable de conceptos de cargo adicional**. Es administrada por el rol Admin desde una pantalla de gestion. Los Invoices consumen este catalogo al momento de creacion, tomando un snapshot del monto en `invoice_items`.

#### DDL completo

```sql
CREATE TABLE invoice_charge_types (
    -- Clave primaria
    id                  BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,

    -- Identificador tecnico interno (slug, nunca cambia, usado en codigo si necesario)
    -- Ejemplos: 'machine_maintenance', 'administration_fee', 'shipping_cost'
    code                VARCHAR(64)         NOT NULL,

    -- Etiqueta visible en el Invoice PDF y en la UI
    -- Este es el campo que el Admin puede cambiar sin tocar codigo
    -- Ejemplo: 'Machine Maintenance', 'Administration Fee', 'SHIPPING COST'
    label               VARCHAR(150)        NOT NULL,

    -- Monto por defecto que se pre-carga al crear el Invoice
    -- El usuario puede sobreescribir este valor en el InvoiceShow (draft)
    default_amount      DECIMAL(10,2)       NOT NULL DEFAULT 0.00,

    -- Controla si este tipo aparece activo en el catalogo
    -- FALSE = desactivado (no se usara en nuevos Invoices, pero el historial queda intacto)
    -- TRUE  = activo (se incluye al crear nuevos Invoices si always_include = true)
    is_active           TINYINT(1)          NOT NULL DEFAULT 1,

    -- Controla si este cargo se incluye automaticamente en TODOS los Invoices
    -- TRUE  = siempre incluido (el servicio lo agrega sin pedir confirmacion)
    -- FALSE = opcional (el usuario lo agrega manualmente si aplica para ese Invoice)
    always_include      TINYINT(1)          NOT NULL DEFAULT 1,

    -- Orden de aparicion en la tabla del PDF (menor numero = aparece primero)
    sort_order          SMALLINT UNSIGNED   NOT NULL DEFAULT 0,

    -- Notas internas para el equipo de administracion (no aparece en el PDF)
    notes               TEXT                NULL,

    -- Auditoria de creacion y modificacion
    created_by          BIGINT UNSIGNED     NULL,
    updated_by          BIGINT UNSIGNED     NULL,

    -- Timestamps estandar de Laravel
    created_at          TIMESTAMP           NULL,
    updated_at          TIMESTAMP           NULL,

    -- Soft delete: no se eliminan registros con historial
    deleted_at          TIMESTAMP           NULL,

    -- Constraints
    PRIMARY KEY (id),
    UNIQUE KEY uq_ict_code (code),

    -- FK de auditoria
    CONSTRAINT fk_ict_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ict_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,

    -- Indices de rendimiento
    INDEX idx_ict_active_order  (is_active, sort_order),
    INDEX idx_ict_deleted_at    (deleted_at)
);
```

#### Relacion con `invoice_items`

El campo `invoice_charge_type_id` en `invoice_items` (FK nullable) vincula cada fila de cargo fijo en un Invoice con el tipo del catalogo que lo origino:

```sql
-- Campo adicional en invoice_items (Fase 5 actualizada)
invoice_charge_type_id  BIGINT UNSIGNED  NULL,

CONSTRAINT fk_ii_charge_type
    FOREIGN KEY (invoice_charge_type_id) REFERENCES invoice_charge_types(id)
    ON DELETE SET NULL,

INDEX idx_ii_charge_type (invoice_charge_type_id)
```

**Regla de integridad:** cuando `is_fixed_charge = 1` en `invoice_items`, el campo `invoice_charge_type_id` debe contener el ID del tipo de cargo. Cuando `is_fixed_charge = 0` (item de producto), `invoice_charge_type_id` es NULL.

---

### 15.4 Impacto en la Tabla `invoices` con el Nuevo Diseno

Con la tabla `invoice_charge_types` y el campo `invoice_charge_type_id` en `invoice_items`, los tres campos de columnas fijas en `invoices` se vuelven tecnicamente redundantes porque:

- Cada cargo fijo vive como un `InvoiceItem` con `is_fixed_charge = true`.
- `Invoice::calculateTotals()` puede derivar `subtotal_charges` sumando `invoice_items WHERE is_fixed_charge = true`.
- El numero y nombre de los cargos es variable; no tiene sentido tenerlos como columnas fijas.

#### Estrategia A — Eliminacion completa de columnas fijas (enfoque limpio)

Se eliminan `charge_machine_maintenance`, `charge_administration_fee` y `charge_shipping_cost` de la tabla `invoices`. Los cargos viven exclusivamente en `invoice_items`.

| Ventaja | Desventaja |
|---|---|
| Diseno normalizado; no hay duplicacion de datos | Requiere JOIN a `invoice_items` para conocer los cargos de un Invoice |
| Soporte nativo para N cargos sin cambios de esquema | Calcular `subtotal_charges` requiere consulta adicional (mitigable con `calculateTotals()`) |
| El modelo `Invoice` es mas simple (`$fillable` mas corto) | Si se importan Invoices historicos pre-catalogo, los cargos historicos deben tener entradas en `invoice_charge_types` igualmente |
| El PDF itera `invoice_items WHERE is_fixed_charge = true` de forma generica | Primera implementacion mas costosa (cambio de paradigma) |

#### Estrategia B — Diseno hibrido con columnas de snapshot (enfoque pragmatico)

Se mantienen las tres columnas en `invoices` como snapshot de conveniencia, pero se agrega `invoice_charge_type_id` en `invoice_items` para la flexibilidad futura.

| Ventaja | Desventaja |
|---|---|
| Compatibilidad con el plan de Fase 5 existente (menor refactorizacion) | Duplicacion parcial de datos (el monto en `invoices` y en `invoice_items`) |
| `calculateTotals()` sigue sumando las tres columnas (codigo existente valido) | Riesgo de inconsistencia si se actualiza uno sin el otro |
| Facilita importacion historica sin necesidad del catalogo | Limita la extensibilidad a largo plazo (agregar un cuarto cargo sigue requiriendo ALTER TABLE) |
| Menor riesgo durante la implementacion inicial | Deuda tecnica explicita que hay que resolver en una refactorizacion futura |

#### Recomendacion

**Implementar la Estrategia A (eliminacion completa) desde el inicio de la Fase 5**, dado que el modulo Invoice aun no existe en produccion. No hay datos legacy que proteger. El costo de implementar bien el diseno desde el inicio es significativamente menor que una refactorizacion post-lanzamiento.

La Estrategia B solo es justificable si el cliente confirma que la importacion de Invoices historicos (#00932–#01005) es un requisito de alta prioridad y el trabajo de poblar el catalogo con tipos de cargo historicos no es viable en el tiempo disponible.

---

### 15.5 Modificaciones al Plan de Implementacion — Fase 5 Actualizada

La Fase 5 original contemplaba 3 migraciones, 2 modelos y 1 archivo de configuracion. Con el diseno extensible, la Fase 5 se actualiza de la siguiente manera:

#### Paso 5.0 (NUEVO) — Migracion: `create_invoice_charge_types_table`

```
Archivo: database/migrations/YYYY_MM_DD_HHMMSS_create_invoice_charge_types_table.php
Crear la tabla invoice_charge_types con la estructura de la seccion 15.3.
Este paso debe ejecutarse ANTES que create_invoices_table y create_invoice_items_table.
Comando: php artisan make:migration create_invoice_charge_types_table
```

#### Paso 5.1 (MODIFICADO) — Migracion: `create_invoices_table`

Eliminar las columnas `charge_machine_maintenance`, `charge_administration_fee` y `charge_shipping_cost`.

La tabla `invoices` mantiene las columnas de totales (`subtotal_items`, `subtotal_charges`, `grand_total`) que son valores calculados y persistidos por `calculateTotals()`. El `subtotal_charges` se calcula sumando `invoice_items WHERE is_fixed_charge = true`.

#### Paso 5.2 (MODIFICADO) — Migracion: `create_invoice_items_table`

Agregar el campo `invoice_charge_type_id BIGINT UNSIGNED NULL` con su FK a `invoice_charge_types`.

#### Paso 5.3 — Sin cambios

La migracion `add_invoice_id_to_packing_slips_table` no se ve afectada.

#### Paso 5.4 (MODIFICADO) — Archivo `config/invoice.php`

Eliminar la seccion `'charges'` del archivo de configuracion. Los montos por defecto viven en `invoice_charge_types.default_amount` (base de datos, administrable). El archivo de configuracion conserva solo los datos del emisor, cliente y formato de numero.

```php
// config/invoice.php (version simplificada)
return [
    'number_format' => [
        'digits'       => 5,
        'pad_char'     => '0',
        'first_number' => 1,
        // DECISION P-12-03 RESUELTA (D-12-23): el sistema arranca desde #00001.
        // Los Invoices de Excel son documentos externos; no se mezclan con la serie del sistema.
    ],
    'issuer' => [
        'name'    => 'FLEXCON',
        'address' => '330 Rocky Woods Lane - Bigfork, Montana - 59911',
        'phone'   => 'PH# 425-466-2184',
        'email'   => 'frank@flexconinc.com',
    ],
    'sold_to' => [
        'name'    => 'S.E.I.P., Inc.',
        'address' => '915 Armorlite Dr.',
        'city'    => 'San Marcos, Ca. 92069',
    ],
    'shipped_to' => [
        'name'    => 'S.E.I.P., Inc.',
        'address' => '915 Armorlite Dr.',
        'city'    => 'San Marcos, Ca. 92069',
    ],
    'fob_location' => 'Tecate, Ca.',
];
```

#### Paso 5.5 (MODIFICADO) — Modelo `Invoice`

En `$fillable`: eliminar `charge_machine_maintenance`, `charge_administration_fee`, `charge_shipping_cost`.

En `$casts`: eliminar los casts de esos tres campos.

En `calculateTotals()`: actualizar para derivar `subtotal_charges` desde `invoice_items`:

```php
public function calculateTotals(): static
{
    $productItems = $this->items()->where('is_fixed_charge', false);
    $chargeItems  = $this->items()->where('is_fixed_charge', true);

    $this->total_quantity    = (int) $productItems->sum('quantity');
    $this->subtotal_items    = (string) $productItems->sum('line_total');
    $this->subtotal_charges  = (string) $chargeItems->sum('line_total');
    $this->grand_total       = bcadd($this->subtotal_items, $this->subtotal_charges, 2);

    return $this;
}
```

#### Paso 5.6 (NUEVO) — Modelo `InvoiceChargeType`

```
Archivo: app/Models/InvoiceChargeType.php

Traits: HasFactory, SoftDeletes

$fillable: [code, label, default_amount, is_active, always_include,
            sort_order, notes, created_by, updated_by]

$casts: [
    'default_amount' => 'decimal:2',
    'is_active'      => 'boolean',
    'always_include' => 'boolean',
]

Relaciones:
  - hasMany(InvoiceItem::class)
  - belongsTo(User::class, 'created_by') → creator()
  - belongsTo(User::class, 'updated_by') → updater()

Scopes:
  - scopeActive(Builder $q): filtra is_active = true AND deleted_at IS NULL
  - scopeAlwaysInclude(Builder $q): filtra always_include = true
  - scopeOrdered(Builder $q): ordena por sort_order ASC

Metodo estatico:
  getActiveTypesForNewInvoice(): Collection
    return static::active()->alwaysInclude()->ordered()->get();
```

#### Paso 5.7 (NUEVO) — Seeder: `InvoiceChargeTypeSeeder`

Ver seccion 15.8 de este documento.

#### Orden de ejecucion de migraciones (actualizado)

```
1. create_invoice_charge_types_table   ← NUEVO (sin dependencias de Invoice)
2. create_invoices_table               ← depende de packing_slips (ya existe)
3. create_invoice_items_table          ← depende de invoices + invoice_charge_types
4. add_invoice_id_to_packing_slips_table ← depende de invoices
```

---

### 15.6 Modificaciones a `InvoiceFromPackingSlipService` — Fase 6 Actualizada

El servicio de creacion de Invoice desde Packing Slip debe actualizarse para usar el catalogo de tipos de cargo en lugar de leer valores hardcodeados de `config/invoice.php`.

#### Logica actualizada para los cargos fijos

```
PASO 1 — Cargar los tipos de cargo activos y de inclusion automatica:
  $chargeTypes = InvoiceChargeType::getActiveTypesForNewInvoice();
  // Retorna coleccion ordenada por sort_order de tipos con is_active=true y always_include=true

PASO 2 — Para cada tipo, crear un InvoiceItem de cargo:
  foreach ($chargeTypes as $index => $type) {
      InvoiceItem::create([
          'invoice_id'              => $invoice->id,
          'invoice_charge_type_id'  => $type->id,
          'description'             => $type->label,
          'quantity'                => 1,
          'unit_cost'               => $type->default_amount,  // snapshot del default actual
          'line_total'              => $type->default_amount,
          'is_fixed_charge'         => true,
          'sort_order'              => 1000 + $index,  // despues de todos los items de producto
      ]);
  }

PASO 3 — Llamar calculateTotals() que ahora suma los InvoiceItems de cargo:
  $invoice->calculateTotals()->save();
```

#### Beneficio clave del nuevo diseno

El `unit_cost` del `InvoiceItem` es un **snapshot**: captura el valor de `default_amount` en el momento de creacion del Invoice. Si el Admin cambia el `default_amount` del tipo en el futuro, los Invoices anteriores no se ven afectados. El usuario puede sobreescribir el `unit_cost` en el `InvoiceShow` (draft) antes de emitir, exactamente como antes.

#### Modificacion a `InvoiceShow` (Livewire) — Fase 7

El metodo `updateFixedCharge(string $field, string $value)` del plan original debe renombrarse para trabajar con `invoice_item_id` en lugar de un nombre de campo de columna:

```
updateChargeAmount(int $invoiceItemId, string $newAmount): void
  - Valida que el Invoice este en draft
  - Busca el InvoiceItem por ID, verifica que pertenece al Invoice y que is_fixed_charge = true
  - Actualiza unit_cost y recalcula line_total
  - Llama $invoice->calculateTotals()->save()
```

La vista `invoice-show.blade.php` iterara sobre `$invoice->items->where('is_fixed_charge', true)` para renderizar los cargos editables, en lugar de referenciar tres campos fijos del modelo.

---

### 15.7 UI de Administracion de Tipos de Cargo

Se propone una pantalla CRUD simple para que el rol Admin gestione el catalogo de `invoice_charge_types` sin necesidad de acceso al codigo o a la base de datos.

#### Especificacion de la ruta y acceso

```
URL:          /admin/invoice-charge-types
Nombre ruta:  admin.invoice-charge-types.index
Middleware:   role:admin (solo accesible por Admin)
Componente:   app/Livewire/Admin/InvoiceChargeTypes/InvoiceChargeTypeManager.php
Vista:        resources/views/livewire/admin/invoice-charge-types/manager.blade.php
```

#### Operaciones disponibles

| Operacion | Descripcion |
|---|---|
| **Listar** | Tabla con todas las filas: Code, Label, Default Amount, Active, Always Include, Sort Order, Acciones |
| **Crear** | Modal inline: campos Label, Code (auto-sugerido desde Label), Default Amount, Always Include, Sort Order, Notes |
| **Editar** | Modal inline para editar Label, Default Amount, is_active, always_include, sort_order, notes. El campo `code` NO es editable (es el identificador tecnico permanente). |
| **Activar/Desactivar** | Toggle en la lista para `is_active`. Desactivar previene que el tipo se use en nuevos Invoices pero preserva el historial. |
| **Reordenar** | Flechas de subir/bajar en la lista para ajustar `sort_order`. Opcional: drag-and-drop con Alpine.js Sortable. |
| **Eliminar** | Solo disponible si el tipo NO ha sido usado en ningun `InvoiceItem` (consulta `hasInvoiceUsage()`). Si ya fue usado: muestra mensaje "Este cargo ya fue usado en X Invoices. Solo puede desactivarse, no eliminarse." Implementa soft delete. |

#### Restriccion de eliminacion — metodo `hasInvoiceUsage()`

```
InvoiceChargeType::hasInvoiceUsage(): bool
  return $this->invoiceItems()->exists();
  // Si retorna true, el boton Eliminar aparece como deshabilitado en la UI
  // con tooltip: "No eliminable — usado en {count} Invoice(s)"
```

#### Rutas adicionales a agregar en `routes/admin.php`

```php
// Administracion del catalogo de tipos de cargo
Route::get('/invoice-charge-types',
    \App\Livewire\Admin\InvoiceChargeTypes\InvoiceChargeTypeManager::class)
    ->name('invoice-charge-types.index')
    ->middleware('role:admin');
```

---

### 15.8 Seeders Iniciales

El seeder `InvoiceChargeTypeSeeder` debe ejecutarse como parte del `DatabaseSeeder` principal en el ambiente de produccion inicial. Tambien debe ser idempotente (usar `firstOrCreate` para evitar duplicados si se vuelve a ejecutar).

#### Contenido del seeder

```php
// database/seeders/InvoiceChargeTypeSeeder.php

use App\Models\InvoiceChargeType;

class InvoiceChargeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code'           => 'machine_maintenance',
                'label'          => 'Machine Maintenance',
                'default_amount' => 1200.00,
                // DECISION P-12-02 RESUELTA (D-12-22): $1,200 es el valor inicial del seeder,
                // basado en Invoice #01006 (el mas reciente confirmado por PDF real).
                // Este default_amount ES EDITABLE por el Admin desde la UI de gestion de
                // tipos de cargo (/admin/invoice-charge-types) sin necesidad de modificar
                // codigo ni hacer despliegues. Si el precio sube en el futuro, el Admin
                // actualiza este campo y todos los Invoices nuevos usaran el nuevo valor.
                // Los Invoices anteriores conservan su snapshot historico en invoice_items.unit_cost.
                'is_active'      => true,
                'always_include' => true,
                'sort_order'     => 1,
                'notes'          => 'Cargo fijo de mantenimiento de maquinaria. '
                                  . 'Valor inicial: $1,200 segun Invoice #01006 (marzo 2026). '
                                  . 'Era $800 en Invoices de 2025. '
                                  . 'EDITABLE por Admin desde /admin/invoice-charge-types. '
                                  . 'Decision P-12-02: el precio puede variar en el tiempo.',
            ],
            [
                'code'           => 'administration_fee',
                'label'          => 'Administration Fee',
                'default_amount' => 250.00,
                'is_active'      => true,
                'always_include' => true,
                'sort_order'     => 2,
                'notes'          => 'Cargo fijo de administracion. '
                                  . 'Confirmado en Invoice #01006.',
            ],
            [
                'code'           => 'shipping_cost',
                'label'          => 'SHIPPING COST',
                'default_amount' => 450.00,
                'is_active'      => true,
                'always_include' => true,
                'sort_order'     => 3,
                'notes'          => 'Cargo de envio. '
                                  . 'Confirmado en Invoice #01006. '
                                  . 'Considerar cambiar a always_include=false si no aplica en envios locales.',
            ],
        ];

        foreach ($types as $data) {
            InvoiceChargeType::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
```

#### Tabla de datos iniciales (referencia rapida)

| Code | Label | Default Amount | Sort Order | Always Include |
|---|---|---|---|---|
| `machine_maintenance` | Machine Maintenance | $1,200.00 | 1 | true |
| `administration_fee` | Administration Fee | $250.00 | 2 | true |
| `shipping_cost` | SHIPPING COST | $450.00 | 3 | true |

**Fuente de los montos:** Invoice #01006 (March-11-2026), que es el ultimo Invoice emitido confirmado por PDF real.

**DECISION P-12-02 RESUELTA (D-12-22):** El `default_amount` de Machine Maintenance ($1,200) es el valor inicial del seeder y es el mecanismo oficial para manejar cambios de precio en el tiempo. El Admin puede modificarlo desde la UI sin tocar codigo. El historial queda en los snapshots de `invoice_items.unit_cost` de cada Invoice emitido.

---

### 15.9 Decisiones Tecnicas Adicionales (D-12-16 a D-12-23)

| ID | Decision | Justificacion |
|---|---|---|
| D-12-16 | Los cargos fijos se almacenan como `InvoiceItem` con `is_fixed_charge=true`, NO como columnas separadas en `invoices` | Normaliza el esquema, elimina el limite de 3 cargos y permite agregar/quitar tipos desde la UI sin cambios de codigo |
| D-12-17 | La tabla `invoice_charge_types` implementa SoftDeletes | Un tipo que fue usado en un Invoice no puede eliminarse fisicamente sin romper el historial; el soft delete preserva la integridad referencial con valor informativo |
| D-12-18 | El campo `code` en `invoice_charge_types` es inmutable post-creacion | Actua como identificador tecnico estable; el campo `label` es el visible al usuario y si puede cambiarse |
| D-12-19 | `InvoiceFromPackingSlipService` carga los tipos activos con `getActiveTypesForNewInvoice()` en lugar de leer `config/invoice.php` | Desacopla la logica de negocio del archivo de configuracion; el catalogo es la fuente de verdad de los cargos |
| D-12-20 | El `unit_cost` del `InvoiceItem` de cargo es un snapshot del `default_amount` al momento de creacion | Garantiza inmutabilidad del historial: cambiar el default en el catalogo no altera Invoices ya creados, solo los nuevos |
| D-12-22 | Machine Maintenance se gestiona como `invoice_charge_type` con `default_amount` mutable, NO como constante de configuracion | Cliente confirmo (P-12-02) que el precio puede variar en el tiempo y requiere tabla separada administrable. El seeder establece $1,200 como valor inicial (Invoice #01006). El Admin lo edita desde la UI; cada Invoice hace snapshot del monto vigente en `invoice_items.unit_cost` |
| D-12-23 | La numeracion del sistema comienza en **#00001**, separada de la serie de Excel historica | Cliente dejo la decision al arquitecto (P-12-03). Separar series evita confusion de auditoria entre documentos generados por el sistema y documentos Excel historicos. `config/invoice.php`: `first_number = 1`, formato de 5 digitos → `00001` |

---

### 15.10 Nuevas Decisiones Pendientes del Cliente (P-12-09 a P-12-12)

| ID | Pregunta | Impacto | Fase bloqueada | Prioridad |
|---|---|---|---|---|
| **P-12-09** | El cargo "SHIPPING COST" ($450): ¿se incluye en absolutamente todos los Invoices o hay casos donde no aplica (ej: envios locales, retiros en planta)? Si hay excepciones, el campo `always_include` debe ser `false` y el usuario debe decidir al crear cada Invoice si incluirlo. | Valor de `invoice_charge_types.always_include` para el tipo `shipping_cost`; logica del Servicio | 6 | ALTA |
| **P-12-10** | ¿Quien debe tener permiso para administrar el catalogo de tipos de cargo (`/admin/invoice-charge-types`)? ¿Solo el rol Admin o tambien algun rol de Facturacion? Esta pantalla afecta directamente el importe de todos los Invoices futuros. | Middleware de la ruta `/admin/invoice-charge-types` | 7 | MEDIA |
| **P-12-11** | Si en el futuro FlexCon incorpora un segundo cliente, ¿los cargos adicionales deben ser los mismos para todos los clientes o podrian variar por cliente? Si varian por cliente, la tabla `invoice_charge_types` necesitara un campo `customer_id` nullable desde el principio. | Diseno del esquema de `invoice_charge_types` | 5 | BAJA (pero con alto impacto si se decide tarde) |
| **P-12-12 — RESUELTA** | Machine Maintenance cambia de $800 (2025) a $1,200 (Invoice #01006, 2026). El cliente confirmo (P-12-02) que el precio puede variar en el tiempo y requiere tabla separada. **Decision:** El seeder inicial usa $1,200 (valor mas reciente confirmado por PDF). El Admin puede modificar el `default_amount` en cualquier momento desde `/admin/invoice-charge-types`. No existe un criterio fijo de revision anual — el cambio de precio queda bajo responsabilidad del Admin del sistema. Ver D-12-22. | Resuelto via P-12-02: `invoice_charge_types.default_amount` para `machine_maintenance` = $1,200, editable por Admin | 5 — DESBLOQUEADA | ALTA |

---

### Actualizacion de Metricas del Modulo (Seccion 13 revisada)

La tabla de metricas de la seccion 13 se actualiza para reflejar los elementos introducidos por el diseno extensible de costos configurables:

| Dimension | Cantidad original | Cantidad actualizada |
|---|---|---|
| Nuevas migraciones | 3 | **4** (+1: `create_invoice_charge_types_table`) |
| Nuevos modelos | 2 (`Invoice`, `InvoiceItem`) | **3** (+1: `InvoiceChargeType`) |
| Nuevos seeders | 0 | **1** (`InvoiceChargeTypeSeeder`) |
| Nuevas rutas | 5 | **6** (+1: `/admin/invoice-charge-types`) |
| Nuevos componentes Livewire | 2 (`InvoiceList`, `InvoiceShow`) | **3** (+1: `InvoiceChargeTypeManager`) |
| Nuevas vistas Blade | 3 (PDF, list, show) | **4** (+1: `invoice-charge-types/manager.blade.php`) |
| Modelos modificados | 1 (`PackingSlip`) | **1** (sin cambio; `Invoice` se disenara desde cero con el nuevo enfoque) |
| Decisiones tecnicas adoptadas | 15 (D-12-01 a D-12-15) | **23** (D-12-01 a D-12-23; +D-12-22 y D-12-23 por respuestas P-12-02 y P-12-03) |
| Decisiones pendientes del cliente | 8 (P-12-01 a P-12-08) | **12** (P-12-01 a P-12-12; P-12-01, P-12-02, P-12-03, P-12-12 RESUELTAS) |

---

*Documento generado como parte de la serie de analisis tecnico del modulo Invoice (FPL-12) del sistema FlexCon Tracker.*
*Fecha de generacion: 2026-03-17 | Ultima actualizacion: 2026-03-18 (v1.1 — P-12-02 y P-12-03 resueltas; D-12-22 y D-12-23 adoptadas)*
