# Analisis Tecnico: Invoice FPL-12 — Plan de Implementacion Detallado

**Fecha:** 2026-03-12
**Elaborado por:** Arquitecto de Software - FlexCon Tracker
**Version:** 1.0
**Proposito:** Documentar el analisis completo del documento FPL-12 (Invoice) y producir el plan de implementacion tecnico para el modulo de facturacion, integrando el estado actual del proyecto (Packing Slip implementado, campos de precio pre-existentes en `packing_slip_items`) con las decisiones pendientes del cliente.

**Documentos previos:**
- `01_shipping_list_analysis.md` — Estructura del Packing Slip FPL-10
- `02_invoice_analysis.md` — Analisis inicial del Invoice FPL-12 (analisis del Excel, gaps, requerimientos, arquitectura propuesta)
- `03_field_mapping_lista_envio_to_packing_slip.md` — Mapeo de campos
- `04_empaque_to_shipping_list_transition.md` — Opciones de diseno
- `05_decisiones_confirmadas_y_plan_implementacion.md` — Plan de implementacion Packing Slip v1.0
- `06_impacto_respuestas_pendientes_y_ajustes.md` — Ajustes al plan
- `07_fpl10_cumplimiento_vs_implementacion.md` — Cumplimiento FPL-10 vs codigo
- `08_label_spec_en_parts_analisis_impacto.md` — Agregar `label_spec` a tabla `parts`
- `8-diagrama-flujo-invoice.mkd` — Diagrama de flujo operativo del proceso de creacion del Invoice

---

## 1. Contexto y Motivacion

### 1.1 Posicion del Invoice en el Flujo de Negocio

El Invoice (FPL-12) es el **documento financiero terminal** del ciclo de produccion y despacho de FlexCon. Su posicion en el flujo es:

```
PO Recibida -> WO Creada -> Lotes -> Empaque -> Inspeccion
   -> Packing Slip (FPL-10) -> INVOICE (FPL-12)
```

El Invoice no se genera desde cero: se construye directamente sobre un Packing Slip existente en estado `shipped`. Es la conversion del documento logistico (que confirma que se despacharon X piezas) en el documento financiero (que cobra al cliente por esas X piezas).

### 1.2 Estado Actual del Proyecto

El modulo de Packing Slip esta implementado. Las tablas `packing_slips` y `packing_slip_items` existen en la base de datos. De forma intencionada, la migracion `2026_03_08_100003_create_packing_slip_items_table.php` ya incluye tres campos de Invoice en la tabla `packing_slip_items`:

| Campo | Tipo | Estado actual | Proposito futuro |
|---|---|---|---|
| `unit_price` | `decimal(10,4) nullable` | NULL en todos los registros | Snapshot del precio unitario al generar el Invoice |
| `price_tier_id` | `FK -> price_tiers nullable` | NULL en todos los registros | Referencia al tier usado (auditoria) |
| `price_source` | `enum(tier,sample,manual) nullable` | NULL en todos los registros | Fuente del precio: tier / sample / manual |

Estos campos fueron pre-definidos en Fase 1 para evitar una migracion adicional en la tabla `packing_slip_items` cuando se implemente el Invoice. El comentario en la migracion lo confirma explicitamente:

> `CAMPOS DEL INVOICE FPL-12 (se llenan en Fase 3, son NULL en Fase 1/2)`

El modelo `PackingSlipItem` ya incluye estos campos en `$fillable`, `$casts` y tiene los metodos auxiliares `getLineTotalAttribute()` y `getPriceSourceLabelAttribute()` implementados.

El sistema de precios (`prices` + `price_tiers`) ya existe completamente implementado. El modelo `Price` tiene el metodo `getPriceForQuantity(int $quantity): ?float` que aplica la logica de tiers por volumen. El metodo estatico `Price::getActivePriceForPart(int $partId): ?Price` obtiene el precio activo de una parte con sus tiers cargados en eager loading.

### 1.3 Por que el Invoice es el Siguiente Paso

Con el Packing Slip operativo y `label_spec` en `parts` implementado (documento 08), el siguiente paso natural es activar los campos de precio que ya existen dormidos en `packing_slip_items` y crear las tablas y componentes propios del Invoice. No hay dependencias sin resolver en la capa de base de datos que bloqueen este desarrollo.

La tabla `packing_slips` actual **no tiene** el campo `document_date` ni los campos de direccion (`sold_to_name`, `shipped_to_name`, etc.) como columnas propias — estos datos se derivan del PS en tiempo de ejecucion o se hard-codean en el template del Invoice. Esto se analiza en detalle en la seccion 4.

### 1.4 Diagrama de Flujo del Proceso (Referencia Operativa)

El diagrama de flujo operativo del Invoice (archivo `8-diagrama-flujo-invoice.mkd`) describe el proceso desde la perspectiva del operador. Se reproduce a continuacion con el analisis de correspondencia con el modelo de datos.

#### 1.4.1 Diagrama de Flujo Original

**Inicio**

1. **Recibe Shipping List**
2. **Crear Invoice**
3. **Datos generales para Invoice**
4. **Numero de Invoice**
5. **Fecha actual**
6. **Packing Slip**
7. **Lista de productos**
8. **Descripcion, Item No.**
9. **Fecha actual**

---

10. **¿Empleado o Maquina?**
    - **Empleado (Si)**
      - Empleado = x01
    - **Maquina (No)**
      - Maquina = x20

11. **Lot No.**
12. **Total = Quantity x Unit Cost**
13. **Sumar total de Cantidad producida**
14. **Mostrar el total de la factura**
15. **Agregar costos adicionales**
    (Machine Maintenance, Administration Fee)

**Fin**

#### 1.4.2 Analisis Paso a Paso

**Paso 1 — Recibe Shipping List:**
El "Shipping List" en la terminologia operativa equivale al Packing Slip en estado `shipped`. El trigger que habilita la creacion del Invoice es `packing_slips.status = 'shipped'`. El sistema bloquea la creacion del Invoice si el PS no ha alcanzado este estado. La validacion se realiza en `InvoiceFromPackingSlipService` antes de abrir la transaccion de BD.

**Pasos 2-5 — Crear Invoice, Datos generales, Numero, Fecha:**
Corresponden a la creacion del registro en la tabla `invoices`. El `invoice_number` se genera secuencialmente con `Invoice::generateInvoiceNumber()` (5 digitos con padding, comenzando en `00954`). El `invoice_date` se toma directamente de `packing_slips.shipped_at::date` — la fecha del Invoice es siempre la misma que la fecha de despacho del PS. Los datos generales (`sold_to_*`, `shipped_to_*`, `fob_location`) se cargan desde `config/invoice.php` como snapshot.

**Paso 6 — Packing Slip:**
La referencia al PS se almacena en `invoices.packing_slip_id` como FK a `packing_slips.id`. En el documento impreso (PDF) aparece como `Packing Slip #XXXXXX` en el encabezado, usando `$invoice->packingSlip->ps_number`.

**Pasos 7-8 — Lista de productos, Descripcion, Item No.:**
Cada `PackingSlipItem` del PS despachado origina un registro en `invoice_items`. Los campos `description` e `item_number` son snapshots de `parts.description` y `parts.item_number` en el momento de generar el Invoice — valores inmutables una vez creados, independientes de cambios futuros en la tabla `parts`.

**Paso 9 — Fecha actual (LOT):**
El diagrama menciona "Fecha actual" como un paso intermedio antes de la bifurcacion Empleado/Maquina. En el contexto del LOT NO. del Invoice (formato `052625x01`), esta fecha es la componente MMDDYY que se combina con el sufijo de tipo de estacion. Se almacena en `invoice_items.lot_number`. Ver analisis del Paso 11 y decision P-09-03 actualizada en seccion 6.

**Paso 10 — ¿Empleado o Maquina? (sufijo x01 / x20):**
Este es un **dato nuevo critico** aportado por el diagrama. El LOT NO. en el Invoice lleva un sufijo que indica el tipo de estacion de trabajo con la que se produjo el lote:

- `x01` = Empleado (Mesa de trabajo manual)
- `x20` = Maquina

Este sufijo se corresponde con el campo `workstation_type` en la tabla `prices` (enum: `table`, `machine`, `semi_automatic`). La logica de derivacion propuesta:
- Si la parte tiene precio activo de tipo `table` → sufijo `x01`
- Si la parte tiene precio activo de tipo `machine` o `semi_automatic` → sufijo `x20`

Este hallazgo resuelve parcialmente la pregunta pendiente P-09-03 sobre el formato del LOT NO. (ver seccion 6 actualizada y decision D-09-09 en seccion 8).

**Paso 11 — Lot No.:**
El LOT NO. completo tiene la estructura: `MMDDYY` + `x` + codigo de tipo de estacion (`01` o `20`). Ejemplo: `052625x01` = 26 de mayo de 2025, estacion de tipo mesa/empleado. Este valor se guarda en `invoice_items.lot_number` como snapshot inmutable. La fecha candidata es `packing_slips.shipped_at` formateada como MMDDYY, aunque queda pendiente confirmar si es la fecha de despacho del PS o la fecha de produccion del lote (ver P-09-03).

**Pasos 12-13 — Total = Quantity x Unit Cost, Sumar totales:**
`invoice_items.line_total = quantity x unit_cost`, calculado con `bcmul()` para evitar errores de punto flotante. El campo `invoices.total_quantity` acumula la suma de `invoice_items.quantity` para los items de producto (`is_fixed_charge = false`). El calculo de totales se centraliza en `Invoice::calculateTotals()`.

**Paso 14 — Mostrar el total de la factura:**
El campo `invoices.grand_total` contiene la suma de todos los `line_total` (items de producto + cargos fijos). Se muestra en la fila `GRAND TOTAL:` del PDF, junto a `invoices.total_quantity` en la columna de cantidad.

**Paso 15 — Agregar costos adicionales:**
El diagrama menciona explicitamente dos cargos: Machine Maintenance y Administration Fee. Sin embargo, el analisis del Excel (seccion 2.5) identifica **tres** cargos en la totalidad de los 21 Invoices: Machine Maintenance ($800), Administration Fee ($250) y **SHIPPING COST ($450)**. El diagrama omite Shipping Cost, posiblemente por ser un documento operativo simplificado. La implementacion incluira los tres cargos como `InvoiceItem` con `is_fixed_charge = true`, consistente con los datos del Excel.

#### 1.4.3 Tabla de Correspondencia Diagrama — Modelo de Datos

| Paso | Descripcion en diagrama | Modelo/Tabla | Campo | Notas |
|---|---|---|---|---|
| 1 | Recibe Shipping List | `PackingSlip` | `status = 'shipped'` | Trigger del flujo |
| 2 | Crear Invoice | `Invoice` | — | Nuevo registro |
| 3 | Datos generales | `Invoice` | `sold_to_*`, `shipped_to_*`, `fob_location` | Desde `config/invoice.php` |
| 4 | Numero de Invoice | `Invoice` | `invoice_number` | Secuencial 5 digitos |
| 5 | Fecha actual | `Invoice` | `invoice_date` | = `packing_slips.shipped_at::date` |
| 6 | Packing Slip | `Invoice` | `packing_slip_id` | FK a `packing_slips.id` |
| 7 | Lista de productos | `InvoiceItem` | — | Una fila por `PackingSlipItem` |
| 8 | Descripcion, Item No. | `InvoiceItem` | `description`, `item_number` | Snapshot de `parts` |
| 9 | Fecha actual (LOT) | `InvoiceItem` | `lot_number` | Fecha en formato MMDDYY (ver P-09-03) |
| 10 | ¿Empleado o Maquina? | `Price` | `workstation_type` | x01=tabla/empleado, x20=maquina o semi_automatica |
| 11 | Lot No. | `InvoiceItem` | `lot_number` | = fecha MMDDYY + 'x' + sufijo tipo estacion |
| 12 | Total = Qty x Unit Cost | `InvoiceItem` | `line_total` | `bcmul(quantity, unit_cost, 4)` |
| 13 | Sumar total cantidad | `Invoice` | `total_quantity` | Suma de `invoice_items.quantity` |
| 14 | Total de la factura | `Invoice` | `grand_total` | Suma de todos los `line_total` |
| 15 | Costos adicionales | `InvoiceItem` | `is_fixed_charge = true` | Diagrama: 2 cargos (Machine Maint. + Admin Fee); Excel: 3 cargos (+ Shipping Cost $450) |

---

## 2. Analisis del Documento FPL-12

### 2.1 Descripcion General del Archivo Excel

El archivo `FPL-12 Invoice 2025.xlsx` contiene 22 hojas, una por cada Invoice emitido en 2025:

| Concepto | Valor |
|---|---|
| Total de hojas | 22 |
| Invoices de producto terminado | 21 (uno por semana, Ene-May 2025) |
| Invoices de consumibles/solventes | 1 (caso especial: hoja `Solvents 03-26-2025`) |
| Rango de fechas | Ene-08-2025 a May-28-2025 |
| Rango de numeracion | Invoice #00932 a Invoice #00953 |
| Packing Slips referenciadas | #001229 a #001249 |
| Columnas activas por hoja | 8 columnas (B a I) |
| Promedio de lineas por Invoice | ~20 items de producto |
| Promedio de piezas por Invoice | ~450,000 piezas |
| Promedio de importe por Invoice | ~$68,500 USD |

### 2.2 Seccion 1: Encabezado (Header)

Filas 1 a 14 de cada hoja del Excel:

```
Fila 1:  FLEXCON
Fila 2:  [vacio] | INVOICE | [vacio] | Clave: FPL-12
Fila 3:  [vacio] | [vacio] | [vacio] | Revision: 01
Fila 4:  330 Rocky Woods Lane - Bigfork, Montana - 59911
Fila 5:  [vacio]
Fila 6:  PH# 425-466-2184 | [vacio] | [vacio] | franknwflexcon@comcast.net
Fila 7:  [vacio]
Fila 8:  "Sold to:" | S.E.I.P., Inc. | "Shipped to:" | S.E.I.P., Inc. | Packing Slip | #001249
Fila 9:  S.E.I.P., Inc.   |   S.E.I.P., Inc.
Fila 10: 915 Armorlite Dr. | 915 Armorlite Dr.
Fila 11: San Marcos, Ca. 92069 | San Marcos, Ca. 92069 | F.O.B: Tecate, Ca.
Fila 12: [vacio]
Fila 13: Invoice#00953 | [vacio] | [vacio] | DATE: May-28-2025
Fila 14: [vacio]
```

**Campos del encabezado identificados:**

| Campo | Tipo inferido | Ejemplo | Fuente de datos | Notas |
|---|---|---|---|---|
| Nombre empresa emisora | Texto fijo | `FLEXCON` | Constante en template | Diferente al PS que usa `ENSAMBLES FORMULA` |
| Clave del documento | Texto fijo | `FPL-12` | Constante en template | |
| Revision | Texto fijo | `01` | Constante en template | El PS usa revision `02` |
| Direccion emisor | Texto fijo | `330 Rocky Woods Lane, Bigfork, MT 59911` | Constante en template | |
| Telefono emisor | Texto fijo | `PH# 425-466-2184` | Constante en template | |
| Email emisor | Texto fijo | `franknwflexcon@comcast.net` | Constante en template | Email diferente al del PS (`Frank@flexconinc.com`) |
| Numero de Packing Slip referenciado | String | `#001249` | `packing_slips.ps_number` | Campo critico: vincula Invoice con PS |
| Sold to (nombre) | String | `S.E.I.P., Inc.` | Constante / configurable | Igual al PS |
| Sold to (direccion 1) | String | `915 Armorlite Dr.` | Constante / configurable | Igual al PS |
| Sold to (ciudad/estado/CP) | String | `San Marcos, Ca. 92069` | Constante / configurable | Igual al PS |
| Shipped to (nombre) | String | `S.E.I.P., Inc.` | Constante / configurable | Igual al PS |
| Shipped to (direccion 1) | String | `915 Armorlite Dr.` | Constante / configurable | Igual al PS |
| Shipped to (ciudad/estado/CP) | String | `San Marcos, Ca. 92069` | Constante / configurable | Igual al PS |
| F.O.B. | Texto fijo | `F.O.B: Tecate, Ca.` | Constante en template | Igual al PS |
| Numero de Invoice | String (5 digitos) | `Invoice#00953` | `invoices.invoice_number` | Secuencial propio del Invoice |
| Fecha del Invoice | Fecha | `May-28-2025` | `invoices.invoice_date` | Misma fecha que el PS despachado |

### 2.3 Seccion 2: Encabezado de Columnas (Fila 15)

```
DESCRIPTION | Item No. | LOT NO. | P.O No. | W.O No. | QUANTITY | UNIT COST | TOTAL
```

| Columna Excel | Nombre campo | Tipo inferido | Ejemplo | Diferencia vs PS |
|---|---|---|---|---|
| Col B | DESCRIPTION | varchar(255) | `STS H-ML-8` | Igual |
| Col C | Item No. | varchar(50) | `189-10257` | Igual |
| Col D | LOT NO. | varchar(30) | `052625x01` | **Formato diferente** (MMDDYY + x + seq) |
| Col E | P.O No. | integer / varchar | `49032` | Igual |
| Col F | W.O No. | integer (7 digitos) | `1980231` | **Formato diferente** (sin prefijo W0 ni sufijo 001) |
| Col G | QUANTITY | integer | `100,000` | Igual |
| Col H | UNIT COST | decimal(10,4) | `0.1380` | **NUEVO** — no existe en PS |
| Col I | TOTAL | decimal(12,2) | `13,800.00` | **NUEVO** — no existe en PS |

### 2.4 Seccion 3: Cuerpo — Filas de Items de Producto

**Patron estructural:**

A diferencia del Packing Slip, el Invoice no tiene filas de subtotal intermedias por PO. Todos los items de producto aparecen en lista continua, ordenados generalmente de mayor a menor cantidad. La misma parte puede aparecer en multiples filas si tiene diferentes POs.

**Ejemplo real (Invoice #00953, hoja 05-28-2025):**

```
DESCRIPTION               | Item No.  | LOT NO.   | P.O No. | W.O No. | QTY    | UNIT   | TOTAL
STS H-ML-8                | 189-10257 | 052625x01 | 49032   | 1980231 | 100000 | 0.138  | 13800.00
STS H-M-3                 | 189-10179 | 052625x01 | 49110   | 1982798 | 100000 | 0.091  |  9120.00
STS H-CR-436-37-CRIMP     | 189-10492 | 052625x01 | 48322   | 1955992 | 100000 | 0.168  | 16800.00
STS H-CR-436-37-CRIMP     | 189-10492 | 052625x01 | 48321   | 1955984 |  49000 | 0.168  |  8232.00
```

**Nota sobre precios observados en el Excel:**

El precio `UNIT COST` para la misma parte varia entre Invoices semanales para la misma PO. Por ejemplo, `189-10492` aparece con `0.168` en algunos Invoices y valores ligeramente distintos en otros. Esto es relevante para la decision de fuente del precio (ver seccion 6, pregunta P-09-01).

### 2.5 Seccion 4: Cargos Adicionales Fijos

Inmediatamente despues de los items de producto, todos los Invoices de producto terminado incluyen tres filas de cargo fijo. Estas filas no tienen Item No., LOT NO., P.O. No. ni W.O. No.

| Descripcion | Columna H (UNIT COST) | Columna I (TOTAL) | Frecuencia observada |
|---|---|---|---|
| `Machine Maintenance` | `800.00` | `800.00` | 100% de los Invoices de producto (21/21) |
| `Administration Fee` | `250.00` | `250.00` | 100% de los Invoices de producto (21/21) |
| `SHIPPING COST` | `450.00` | `450.00` | 100% de los Invoices de producto (21/21) |
| **Subtotal cargos fijos** | | **$1,500.00** | Constante en todos |

### 2.6 Seccion 5: Fila de Totales (Grand Total)

La ultima fila de datos del Invoice:

| Columna | Contenido | Ejemplo |
|---|---|---|
| Col B | Texto `GRAND TOTAL:` | `GRAND TOTAL:` |
| Col G | Suma total de piezas (items de producto, excluye cargos fijos) | `539,200` |
| Col I | Suma de todos los importes (items + cargos fijos) | `$80,052.11` |

**Nota:** La suma de TOTAL en Col I incluye los tres cargos fijos ($1,500). No existe un subtotal separado de "solo items de producto".

### 2.7 Caso Especial: Invoice de Solventes (000944)

La hoja `Solvents 03-26-2025` documenta un Invoice de naturaleza fundamentalmente diferente:

| Caracteristica | Invoice normal de producto | Invoice de solventes |
|---|---|---|
| Numero | `00932`-`00953` (5 digitos) | `000944` (6 digitos, formato diferente) |
| Packing Slip | `#001229` a `#001249` | `N/A` (no tiene PS) |
| Direccion emisor | `330 Rocky Woods Lane, Bigfork, MT` | `10512 19th Ave. SE, Everett, WA 98208` |
| Tipo de items | Partes ensambladas | Entregas de Alcohol isopropilico |
| Referencia WO | Numero del WO | `n/a` |
| Cantidad | Piezas | `1` (entrega) |
| Cargos fijos | Machine Maintenance, Admin Fee, Shipping | Ninguno |
| Items | Partes × PO | Entregas con fecha y referencia Flx-Ref |

Este tipo de Invoice requiere un flujo separado (`type = 'standalone'`) sin dependencia del PS.

---

## 3. Mapeo de Campos FPL-12 a Base de Datos

### 3.1 Encabezado del Invoice

| Campo en FPL-12 | Tabla.Columna en BD | Estado | Accion requerida |
|---|---|---|---|
| `FLEXCON` (nombre empresa) | Constante en template Blade | Ya implementable | Ninguna |
| `FPL-12` (clave) | Constante en template Blade | Ya implementable | Ninguna |
| `Revision: 01` | Constante en template Blade | Ya implementable | Ninguna |
| Direccion emisor | Constante en template / config | Ya implementable | Ninguna |
| Telefono emisor | Constante en template / config | Ya implementable | Ninguna |
| Email emisor (`franknwflexcon@comcast.net`) | Constante en template / config | Ya implementable | Diferente al email del PS |
| `Packing Slip #001249` | `packing_slips.ps_number` (via FK `invoices.packing_slip_id`) | Por crear | Crear tabla `invoices` con FK a PS |
| `Sold to: S.E.I.P., Inc.` | Constante / `invoices.sold_to_name` | Por crear | Campo en tabla `invoices` |
| Direccion Sold to | Constante / `invoices.sold_to_address` | Por crear | Campo en tabla `invoices` |
| `Shipped to: S.E.I.P., Inc.` | Constante / `invoices.shipped_to_name` | Por crear | Campo en tabla `invoices` |
| Direccion Shipped to | Constante / `invoices.shipped_to_address` | Por crear | Campo en tabla `invoices` |
| `F.O.B: Tecate, Ca.` | Constante / `invoices.fob_location` | Por crear | Campo en tabla `invoices` |
| `Invoice#00953` | `invoices.invoice_number` | Por crear | Secuencial en tabla `invoices` |
| `DATE: May-28-2025` | `invoices.invoice_date` | Por crear | Igual a `packing_slips.shipped_at::date` |

### 3.2 Items de Linea del Invoice

| Campo en FPL-12 | Tabla.Columna en BD | Estado | Accion requerida |
|---|---|---|---|
| `DESCRIPTION` | `invoice_items.description` (snapshot) | Por crear | Snapshot de `parts.description` |
| `Item No.` (ej: `189-10257`) | `invoice_items.item_number` (snapshot) | Por crear | Snapshot de `parts.item_number` |
| `LOT NO.` (formato `MMDDYYxNN`) | `invoice_items.lot_number` (snapshot) | Por crear | **PENDIENTE CLIENTE** (ver P-09-03) |
| `P.O No.` (ej: `49032`) | `invoice_items.po_number` (snapshot) | Por crear | Snapshot de `purchase_orders.po_number` |
| `W.O No.` (ej: `1980231`) | `invoice_items.wo_number` (snapshot) | Por crear | `work_orders.external_wo_number` (7 digitos, sin W0 ni sufijo) |
| `QUANTITY` | `invoice_items.quantity` | Por crear | Igual a `packing_slip_items.quantity_packed` |
| `UNIT COST` | `invoice_items.unit_cost` | Por crear | Calculado via `prices` + `price_tiers` |
| `TOTAL` (linea) | `invoice_items.line_total` | Por crear | `quantity x unit_cost` (calculado) |

**Campos que ya existen en `packing_slip_items` y que se usan como fuente:**

| Campo en `packing_slip_items` | Uso en el Invoice |
|---|---|
| `unit_price` (decimal 10,4, NULL) | Se llena al generar el Invoice; es el snapshot del `unit_cost` |
| `price_tier_id` (FK nullable) | Se llena al generar el Invoice; referencia al tier usado |
| `price_source` (enum nullable) | Se llena al generar el Invoice; indica si fue tier/sample/manual |
| `quantity_packed` | Fuente de `invoice_items.quantity` |
| `wo_number_ps` | Fuente para derivar `invoice_items.wo_number` (remover prefijo W0 y sufijo 001) |
| `lot_date_code` | Candidato para `LOT NO.` del Invoice (pendiente confirmar formato) |

### 3.3 Cargos Fijos

| Campo en FPL-12 | Tabla.Columna en BD | Estado | Accion requerida |
|---|---|---|---|
| `Machine Maintenance` ($800) | `invoices.charge_machine_maintenance` | Por crear | Snapshot con default configurable |
| `Administration Fee` ($250) | `invoices.charge_administration_fee` | Por crear | Snapshot con default configurable |
| `SHIPPING COST` ($450) | `invoices.charge_shipping_cost` | Por crear | Snapshot con default configurable |

### 3.4 Totales

| Campo en FPL-12 | Tabla.Columna en BD | Estado | Accion requerida |
|---|---|---|---|
| Total de piezas (GRAND TOTAL Qty) | `invoices.total_quantity` | Por crear | Suma de `invoice_items.quantity` donde `is_fixed_charge = false` |
| Total importe (GRAND TOTAL $) | `invoices.grand_total` | Por crear | Suma de todos los `line_total` (items + cargos fijos) |
| Subtotal solo items | `invoices.subtotal_items` | Por crear | Suma de `line_total` donde `is_fixed_charge = false` |
| Subtotal solo cargos | `invoices.subtotal_charges` | Por crear | Suma de los 3 cargos fijos = `charge_machine_maintenance + charge_administration_fee + charge_shipping_cost` |

---

## 4. Modelo de Datos Propuesto

### 4.1 Nueva Tabla: `invoices`

```sql
CREATE TABLE invoices (
    id                         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identificacion del documento
    invoice_number             VARCHAR(20)     NOT NULL UNIQUE,
    -- Ejemplo: '00954'. 5 digitos, continua desde el ultimo del Excel.
    -- El primer Invoice del sistema debe ser '00954'.

    invoice_date               DATE            NOT NULL,
    -- Igual a la fecha del Packing Slip despachado.

    status                     ENUM('draft','issued','paid') DEFAULT 'draft',
    -- draft: en edicion, precios editables
    -- issued: emitido, PDF generado, datos bloqueados
    -- paid: pagado (Fase futura)

    type                       ENUM('product','standalone') DEFAULT 'product',
    -- product: generado desde un Packing Slip
    -- standalone: Invoice sin PS (consumibles, solventes, servicios)

    -- Referencia al Packing Slip de origen (nullable para standalone)
    packing_slip_id            BIGINT UNSIGNED NULL,
    FOREIGN KEY (packing_slip_id) REFERENCES packing_slips(id) ON DELETE RESTRICT,
    -- UNIQUE en packing_slip_id (un PS solo puede tener un Invoice de tipo product)

    -- Datos de direccion (snapshot al momento de emision)
    fob_location               VARCHAR(100)    NOT NULL DEFAULT 'Tecate, Ca.',
    sold_to_name               VARCHAR(200)    NOT NULL DEFAULT 'S.E.I.P., Inc.',
    sold_to_address            TEXT            NOT NULL,
    shipped_to_name            VARCHAR(200)    NOT NULL DEFAULT 'S.E.I.P., Inc.',
    shipped_to_address         TEXT            NOT NULL,

    -- Cargos fijos (snapshot de los valores vigentes al crear el Invoice)
    charge_machine_maintenance DECIMAL(10,2)   NOT NULL DEFAULT 800.00,
    charge_administration_fee  DECIMAL(10,2)   NOT NULL DEFAULT 250.00,
    charge_shipping_cost       DECIMAL(10,2)   NOT NULL DEFAULT 450.00,

    -- Totales calculados y desnormalizados (para rendimiento y auditoria)
    total_quantity             INT             NULL,
    subtotal_items             DECIMAL(12,2)   NULL,
    subtotal_charges           DECIMAL(10,2)   NULL,
    grand_total                DECIMAL(12,2)   NULL,

    -- Control y auditoria
    notes                      TEXT            NULL,
    issued_at                  TIMESTAMP       NULL,
    paid_at                    TIMESTAMP       NULL,
    created_by                 BIGINT UNSIGNED NULL,
    issued_by                  BIGINT UNSIGNED NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by)   REFERENCES users(id) ON DELETE SET NULL,

    created_at                 TIMESTAMP NULL,
    updated_at                 TIMESTAMP NULL,
    deleted_at                 TIMESTAMP NULL,

    INDEX idx_inv_number      (invoice_number),
    INDEX idx_inv_packing_slip(packing_slip_id),
    INDEX idx_inv_date        (invoice_date),
    INDEX idx_inv_status      (status),
    INDEX idx_inv_type        (type)
);
```

**Constraint UNIQUE en `packing_slip_id`:** Un Packing Slip puede tener como maximo un Invoice de tipo `product`. Para `standalone`, `packing_slip_id` es NULL y no aplica la restriccion. En Laravel, la restriccion se implementa como `UNIQUE` parcial o se valida en el servicio con un check previo a la creacion.

### 4.2 Nueva Tabla: `invoice_items`

```sql
CREATE TABLE invoice_items (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- FK obligatoria al Invoice padre
    invoice_id             BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,

    -- Referencia al PackingSlipItem de origen (nullable para standalone y cargos fijos)
    packing_slip_item_id   BIGINT UNSIGNED NULL,
    FOREIGN KEY (packing_slip_item_id) REFERENCES packing_slip_items(id) ON DELETE SET NULL,

    -- Referencias a entidades del sistema (nullable; para navegacion y reportes)
    lot_id                 BIGINT UNSIGNED NULL,
    work_order_id          BIGINT UNSIGNED NULL,
    purchase_order_id      BIGINT UNSIGNED NULL,
    part_id                BIGINT UNSIGNED NULL,
    FOREIGN KEY (lot_id)              REFERENCES lots(id)            ON DELETE SET NULL,
    FOREIGN KEY (work_order_id)       REFERENCES work_orders(id)     ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_id)   REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (part_id)             REFERENCES parts(id)           ON DELETE SET NULL,

    -- Datos desnormalizados (snapshot inmutable al momento de facturacion)
    description            VARCHAR(255)  NOT NULL,
    item_number            VARCHAR(100)  NULL,
    lot_number             VARCHAR(50)   NULL,
    po_number              VARCHAR(50)   NULL,
    wo_number              VARCHAR(50)   NULL,

    -- Cantidades y precios
    quantity               INT           NOT NULL DEFAULT 1,
    unit_cost              DECIMAL(10,4) NOT NULL,
    -- 4 decimales: precision suficiente para precios como 0.0912, 0.1752, 0.1683
    line_total             DECIMAL(12,2) NOT NULL,
    -- Calculado: quantity x unit_cost, redondeado a 2 decimales

    -- Clasificacion de la fila
    sort_order             SMALLINT      NOT NULL DEFAULT 0,
    is_fixed_charge        BOOLEAN       NOT NULL DEFAULT FALSE,
    -- TRUE para Machine Maintenance, Administration Fee, SHIPPING COST

    created_at             TIMESTAMP NULL,
    updated_at             TIMESTAMP NULL,

    INDEX idx_ii_invoice   (invoice_id),
    INDEX idx_ii_psi       (packing_slip_item_id),
    INDEX idx_ii_lot       (lot_id),
    INDEX idx_ii_wo        (work_order_id)
);
```

### 4.3 Campos Pre-existentes en `packing_slip_items` que se Activaran

Estos campos ya existen en la tabla (definidos en la migracion `2026_03_08_100003`). No se requiere ninguna migracion adicional en esta tabla. Simplemente se poblan al generar el Invoice:

| Campo | Tipo actual | Cuando se llena | Fuente del valor |
|---|---|---|---|
| `unit_price` | `decimal(10,4) nullable` | Al generar el Invoice | `Price::getActivePriceForPart($partId)->getPriceForQuantity($poQuantity)` o ingreso manual |
| `price_tier_id` | `FK price_tiers nullable` | Al generar el Invoice | ID del tier que coincidio con la cantidad |
| `price_source` | `enum(tier,sample,manual)` | Al generar el Invoice | `'tier'` si hubo tier; `'sample'` si se uso sample_price; `'manual'` si el admin lo ingreso a mano |

**Razon de mantener `unit_price` en `packing_slip_items` Y en `invoice_items.unit_cost`:** Son snapshots en contextos diferentes. `packing_slip_items.unit_price` registra el precio que estaba vigente al momento de procesar ese lote para el PS (auditoria del precio). `invoice_items.unit_cost` es el precio confirmado y usado en el calculo del importe facturado. En la practica seran el mismo valor, pero la separacion garantiza que el PS y el Invoice pueden tener ciclos de vida independientes.

### 4.4 Modificaciones Requeridas a `packing_slips`

La tabla `packing_slips` no tiene actualmente un campo `invoice_id` para la referencia inversa (saber rapidamente si un PS ya tiene Invoice). Se propone agregar este campo via migracion adicional:

```sql
ALTER TABLE packing_slips
ADD COLUMN invoice_id BIGINT UNSIGNED NULL AFTER shipped_by,
ADD FOREIGN KEY fk_ps_invoice (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;
```

Esto permite la consulta rapida `$ps->invoice` sin hacer un `Invoice::where('packing_slip_id', $ps->id)->first()`.

**Nota sobre referencia circular:** `packing_slips.invoice_id` -> `invoices.id` e `invoices.packing_slip_id` -> `packing_slips.id` forman una referencia circular bidireccional. Esto es correcto y comun en sistemas documentales que requieren navegacion bidireccional rapida. Laravel maneja esta situacion sin problema. En las migraciones, la FK de `invoices.packing_slip_id` se crea con la tabla `invoices`, y la FK de `packing_slips.invoice_id` se crea en una migracion posterior via `ALTER TABLE`.

### 4.5 Configuracion de Cargos Fijos

Se propone un archivo `config/invoice.php` para los valores por defecto de los cargos fijos:

```php
// config/invoice.php
return [
    'charges' => [
        'machine_maintenance' => env('INVOICE_CHARGE_MACHINE_MAINTENANCE', 800.00),
        'administration_fee'  => env('INVOICE_CHARGE_ADMINISTRATION_FEE', 250.00),
        'shipping_cost'       => env('INVOICE_CHARGE_SHIPPING_COST', 450.00),
    ],
    'number_format' => [
        'digits'   => 5,       // Formato de 5 digitos: 00954
        'pad_char' => '0',
        'first_number' => 954, // Primer numero del sistema (continua desde #00953 del Excel)
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
    'issuer' => [
        'name'    => 'FLEXCON',
        'address' => '330 Rocky Woods Lane - Bigfork, Montana - 59911',
        'phone'   => 'PH# 425-466-2184',
        'email'   => 'franknwflexcon@comcast.net',
    ],
];
```

Los valores de los cargos se guardan como **snapshot** en `invoices.charge_machine_maintenance`, `invoices.charge_administration_fee` y `invoices.charge_shipping_cost` al momento de crear el Invoice. Esto garantiza que si los valores de configuracion cambian en el futuro, los Invoices ya emitidos conservan los valores que tenian cuando fueron creados.

---

## 5. Relacion Packing Slip y Invoice

### 5.1 Tipo de Relacion: 1 a 1 (con excepcion standalone)

**Regla de negocio principal:**
- Cada Packing Slip despachado genera **exactamente un** Invoice de tipo `product`.
- Un Invoice de tipo `product` referencia **exactamente un** Packing Slip.
- La relacion es 1:1 estricta para el flujo normal.

**Evidencia del Excel:** Las 21 hojas de producto del Excel muestran que cada Invoice referencia un numero de Packing Slip diferente y unico. La fecha del Invoice es siempre la misma que la del embarque del PS.

**Excepcion standalone:**
- Un Invoice de tipo `standalone` no tiene Packing Slip. Su `packing_slip_id` es NULL.
- No hay Packing Slip que referencie un Invoice standalone (la FK inversa en `packing_slips.invoice_id` permanece NULL).

### 5.2 Diagrama de Relaciones

```
packing_slips
  id (PK)
  ps_number
  status
  shipped_at
  invoice_id ----FK-----+
                        |
                        v
invoices                |
  id (PK)  <------------+
  invoice_number
  invoice_date
  status
  type
  packing_slip_id --FK--> packing_slips.id   (nullable)
  ...cargos fijos
  ...totales
  created_by --FK--> users.id

      |
      | hasMany
      v

invoice_items
  id (PK)
  invoice_id --FK--> invoices.id  (CASCADE DELETE)
  packing_slip_item_id --FK--> packing_slip_items.id  (nullable, SET NULL)
  lot_id --FK--> lots.id          (nullable, SET NULL)
  work_order_id --FK--> work_orders.id  (nullable, SET NULL)
  purchase_order_id --FK--> purchase_orders.id (nullable, SET NULL)
  part_id --FK--> parts.id         (nullable, SET NULL)
  ...snapshot fields
  unit_cost
  line_total
  is_fixed_charge


packing_slip_items           prices
  id (PK)                      id (PK)
  packing_slip_id              part_id --FK--> parts.id
  lot_id                       sample_price
  quantity_packed              workstation_type
  unit_price  [NULL->fill]     active
  price_tier_id --FK--+        |
  price_source        |        | hasMany
                      |        v
                      +---> price_tiers
                               id (PK)
                               price_id
                               min_quantity
                               max_quantity
                               tier_price
```

### 5.3 Flujo de Conversion PS → Invoice

```
[PackingSlip status='shipped']
        |
        | usuario hace clic "Crear Invoice"
        v
[Validar: PS en estado shipped]
[Validar: PS no tiene Invoice ya (invoice_id IS NULL)]
        |
        v
[InvoiceFromPackingSlipService::createFromPackingSlip($ps)]
        |
        | DB::transaction()
        v
1. Generar invoice_number (siguiente correlativo)
2. Crear registro en invoices:
   - invoice_date = $ps->shipped_at->toDateString()
   - packing_slip_id = $ps->id
   - sold_to_*, shipped_to_* = valores de config/invoice.php
   - charge_machine_maintenance = config('invoice.charges.machine_maintenance')
   - charge_administration_fee  = config('invoice.charges.administration_fee')
   - charge_shipping_cost       = config('invoice.charges.shipping_cost')
   - status = 'draft'
   - type   = 'product'
3. Por cada PackingSlipItem del PS:
   a. Obtener el precio: Price::getActivePriceForPart($partId)
   b. Aplicar tier: $price->getPriceForQuantity($poQuantity)
   c. Crear InvoiceItem con unit_cost = precio calculado
   d. Actualizar packing_slip_items:
      - unit_price = precio calculado
      - price_tier_id = tier.id (si aplico tier)
      - price_source = 'tier' | 'sample' | 'manual'
4. Crear 3 InvoiceItems de cargos fijos (is_fixed_charge = true)
5. Calcular totales (total_quantity, subtotal_items, subtotal_charges, grand_total)
6. Actualizar packing_slips.invoice_id = $invoice->id
        |
        v
[Invoice en estado 'draft']
[Usuario revisa y ajusta precios si es necesario]
        |
        | usuario hace clic "Emitir Invoice"
        v
[Recalcular totales con precios finales confirmados]
[status = 'issued', issued_at = NOW(), issued_by = auth()->id()]
[Generar PDF con barryvdh/laravel-dompdf]
        |
        v
[Invoice en estado 'issued' — datos bloqueados]
[PDF disponible para descarga]
```

---

## 6. Pendientes del Cliente (PENDIENTE CLIENTE)

Estas preguntas deben ser respondidas antes de iniciar la implementacion o durante la Fase 5. Se marcan como PENDIENTE CLIENTE en la tabla de decisiones.

**P-09-01 (ALTA — Fase 5):** Fuente del precio unitario (`unit_cost`)

El analisis del Excel muestra que el precio de la misma parte cambia entre diferentes Invoices. Por ejemplo, `189-10492 STS H-CR-436-37-CRIMP` aparece con `0.168` en mayo 2025 pero con valores diferentes en meses anteriores. La pregunta es:

- El precio que va en el Invoice, ¿es el precio del catalogo de partes (`prices` + `price_tiers`) para la cantidad total de la PO?
- O bien, ¿el precio esta fijo en la PO misma (`purchase_orders.unit_price`) y el catalogo de precios es solo de referencia?
- Si la respuesta es el catalogo: ¿la cantidad de referencia para el tier es la cantidad de la PO (`purchase_orders.quantity`) o la cantidad empacada en ese PS (`packing_slip_items.quantity_packed`)?

Esta pregunta es critica porque determina como `InvoiceFromPackingSlipService` calcula el `unit_cost` de cada linea.

**P-09-02 (ALTA — Fase 5):** Numeracion del Invoice

El Excel termina en Invoice #00953. El sistema debe continuar desde #00954. Confirmacion requerida:
- ¿El primer Invoice del sistema es #00954?
- ¿La numeracion de los Invoices standalone usa la misma secuencia o una separada?
- ¿Existen Invoices emitidos despues de mayo 2025 (fuera del Excel) que el sistema deba importar o registrar para no colisionar con su numeracion?

**P-09-03 (ALTA — Fase 6):** Formato del campo LOT NO. en el Invoice

El Invoice usa el formato `052625x01` (MMDDYY + x + tipo de estacion) para el campo LOT NO. El diagrama de flujo `8-diagrama-flujo-invoice.mkd` aporta nuevo contexto que permite proponer una logica de derivacion automatica:

**Lo que el diagrama confirma:**
- El LOT NO. tiene dos componentes: una fecha en formato MMDDYY y un sufijo de tipo de estacion de trabajo.
- El sufijo es `x01` para produccion en mesa/empleado y `x20` para produccion en maquina.
- Este sufijo se puede derivar del campo `workstation_type` del precio activo de la parte: `table` → `x01`; `machine` o `semi_automatic` → `x20`.
- La fecha puede derivarse de `packing_slips.shipped_at` formateada como MMDDYY (dia-mes-ano en 2 digitos cada uno), lo que es consistente con el ejemplo `052625x01` = 26-mayo-2025.

**Logica de derivacion propuesta (en `InvoiceFromPackingSlipService`):**
```
lot_number = format(shipped_at, 'mdY_2digit') + 'x' + sufijo_tipo_estacion
```
donde `sufijo_tipo_estacion` = '01' si `prices.workstation_type = 'table'`; '20' si `prices.workstation_type = 'machine'` o `'semi_automatic'`.

Esta logica puede implementarse sin intervencion manual del usuario, siempre que el precio activo de la parte tenga definido su `workstation_type`.

**Pregunta pendiente reducida — confirmacion requerida:**
- ¿La fecha del LOT NO. es la fecha de despacho del PS (`packing_slips.shipped_at`) o la fecha de produccion del lote (campo `produced_at` o equivalente del lote)?
- ¿El sufijo `x01`/`x20` se determina correctamente desde `prices.workstation_type` o existe otro campo mas especifico en el lote o en el WO?

**P-09-04 (MEDIA — Fase 5):** Estados del Invoice y flujo de aprobacion

El documento FPL-12 del Excel no tiene campo de estado visible. Confirmacion requerida:
- ¿El Invoice requiere alguna aprobacion interna antes de emitirse?
- ¿Solo el Administrador (Frank) puede emitir el Invoice o tambien el area de Facturacion?
- ¿El estado `paid` (pagado) es requerido desde el inicio o es Fase futura?

**P-09-05 (MEDIA — Fase 5):** Cargos fijos variables

Los cargos fijos siempre son $800 + $250 + $450 en los 21 Invoices del Excel. Confirmacion:
- ¿Estos montos son invariables para todos los envios?
- ¿O pueden cambiar en envios especiales (envio urgente, peso excesivo, etc.)?
- Si pueden cambiar, ¿quien tiene permiso para modificarlos al crear el Invoice?

**P-09-06 (MEDIA — Fase 5):** Permisos del modulo de Invoice

- ¿Quien puede crear un Invoice? (Admin, Facturacion, Shipping)
- ¿Quien puede modificar los precios en un Invoice draft?
- ¿Quien puede emitir (marcar como `issued`) el Invoice?
- ¿Existe un rol especifico de Facturacion que aun no este en el sistema?

**P-09-07 (MEDIA — Fase 8):** Invoice de Solventes y consumibles

El Invoice `000944` de solventes tiene formato diferente. Confirmacion:
- ¿Este tipo de Invoice se generara con frecuencia?
- ¿La direccion diferente (Everett, WA) es un proveedor diferente o es la misma empresa?
- ¿El sistema debe soportar multiples "perfiles de emisor" (Bigfork vs Everett)?

**P-09-08 (BAJA — General):** Importacion de historial

- ¿Los 21 Invoices del Excel (00932-00953) deben importarse al sistema como registros historicos?
- Si si, ¿solo como referencia (datos de cabecera) o con todos los items y precios?
- ¿Los Packing Slips del historial (001229-001249) tambien estan en el sistema o solo existiran los generados por la app hacia adelante?

---

## 7. Plan de Implementacion

El modulo de Invoice se divide en cuatro fases, numerando como continuacion de las fases del Packing Slip (Fases 1-4) y de las fases propuestas en `02_invoice_analysis.md` (Fases 5-8).

### Fase 5: Infraestructura del Invoice (BD + Modelos)

**Prerequisito:** Packing Slip completamente implementado (tablas, modelos, flujo de despacho). Los campos `unit_price`, `price_tier_id`, `price_source` de `packing_slip_items` ya existen.

**Paso 5.1 — Migracion: crear tabla `invoices`**

```bash
php artisan make:migration create_invoices_table
```

Crear la tabla con la estructura del apartado 4.1. Indices en `invoice_number`, `packing_slip_id`, `invoice_date`, `status`.

**Paso 5.2 — Migracion: crear tabla `invoice_items`**

```bash
php artisan make:migration create_invoice_items_table
```

Crear la tabla con la estructura del apartado 4.2. La migracion debe ejecutarse despues de `create_invoices_table`.

**Paso 5.3 — Migracion: agregar `invoice_id` a `packing_slips`**

```bash
php artisan make:migration add_invoice_id_to_packing_slips_table
```

```php
Schema::table('packing_slips', function (Blueprint $table) {
    $table->foreignId('invoice_id')
          ->nullable()
          ->after('shipped_by')
          ->constrained('invoices')
          ->nullOnDelete()
          ->comment('Invoice generado para este PS (NULL si aun no se genero)');
});
```

**Nota de orden de migraciones:** Esta migracion debe ejecutarse despues de `create_invoices_table` porque la FK apunta a `invoices.id`.

**Paso 5.4 — Crear archivo de configuracion `config/invoice.php`**

Crear el archivo con los valores del apartado 4.5 (cargos fijos, formato de numero, datos del emisor, datos del cliente).

**Paso 5.5 — Modelo `Invoice`**

Archivo: `app/Models/Invoice.php`

```
Constantes:
  STATUS_DRAFT  = 'draft'
  STATUS_ISSUED = 'issued'
  STATUS_PAID   = 'paid'
  TYPE_PRODUCT    = 'product'
  TYPE_STANDALONE = 'standalone'

$fillable: invoice_number, invoice_date, status, type, packing_slip_id,
           fob_location, sold_to_name, sold_to_address, shipped_to_name,
           shipped_to_address, charge_machine_maintenance,
           charge_administration_fee, charge_shipping_cost,
           total_quantity, subtotal_items, subtotal_charges,
           grand_total, notes, issued_at, paid_at, created_by, issued_by

$casts: invoice_date (date), issued_at (datetime), paid_at (datetime),
        unit_price y totals (decimal:2)

SoftDeletes: si

Relaciones:
  - hasMany(InvoiceItem::class)
  - belongsTo(PackingSlip::class)
  - belongsTo(User::class, 'created_by')
  - belongsTo(User::class, 'issued_by')

Metodos estaticos:
  - generateInvoiceNumber(): string
    Obtiene el ultimo invoice_number de type='product', suma 1, formatea con
    str_pad($next, 5, '0', STR_PAD_LEFT)
    Primer numero si no hay registros: config('invoice.number_format.first_number') + 1 = 954

Metodos de instancia:
  - calculateTotals(): static
    Recalcula total_quantity, subtotal_items, subtotal_charges, grand_total
    desde los InvoiceItems asociados y los campos de cargos fijos.
  - canBeModified(): bool
    return $this->status === self::STATUS_DRAFT;
  - isPdfAvailable(): bool
    return $this->status === self::STATUS_ISSUED || $this->status === self::STATUS_PAID;
  - getStatusLabelAttribute(): string
  - getStatusColorAttribute(): string
```

**Paso 5.6 — Modelo `InvoiceItem`**

Archivo: `app/Models/InvoiceItem.php`

```
$fillable: invoice_id, packing_slip_item_id, lot_id, work_order_id,
           purchase_order_id, part_id, description, item_number,
           lot_number, po_number, wo_number, quantity, unit_cost,
           line_total, sort_order, is_fixed_charge

$casts: unit_cost (decimal:4), line_total (decimal:2),
        is_fixed_charge (boolean)

Relaciones:
  - belongsTo(Invoice::class)
  - belongsTo(PackingSlipItem::class) -> nullable
  - belongsTo(Lot::class)             -> nullable
  - belongsTo(WorkOrder::class)       -> nullable
  - belongsTo(PurchaseOrder::class)   -> nullable
  - belongsTo(Part::class)            -> nullable
```

**Paso 5.7 — Actualizar modelo `PackingSlip`**

Agregar a `app/Models/PackingSlip.php`:
- `'invoice_id'` en `$fillable`
- `'invoice_id' => 'integer'` en `$casts`
- Relacion `public function invoice(): HasOne` -> `return $this->hasOne(Invoice::class);`
- Metodo `hasInvoice(): bool` -> `return $this->invoice_id !== null;`

**Paso 5.8 — Tests unitarios de modelos**

- `tests/Unit/Models/InvoiceTest.php`: probar `generateInvoiceNumber()`, `calculateTotals()`, `canBeModified()`
- `tests/Unit/Models/InvoiceItemTest.php`: probar relaciones y calculos basicos

---

### Fase 6: Servicio de Conversion y Logica de Precios

**Prerequisito:** Fase 5 completada.

**Paso 6.1 — Servicio `InvoiceFromPackingSlipService`**

Archivo: `app/Services/InvoiceFromPackingSlipService.php`

Responsabilidades:
1. Recibe un `PackingSlip` model con sus items cargados en eager loading.
2. Valida que el PS este en estado `shipped`.
3. Valida que el PS no tenga `invoice_id` ya asignado.
4. Ejecuta todo en `DB::transaction()`.
5. Para cada `PackingSlipItem`:
   - Navega la cadena `lot -> workOrder -> purchaseOrder -> part`.
   - Llama `Price::getActivePriceForPart($partId)`.
   - Llama `$price->getPriceForQuantity($poQuantity)` usando la cantidad de la PO (pendiente confirmar con P-09-01).
   - Si no hay precio: crea el item con `unit_cost = 0` y `price_source = 'manual'`, para que el usuario lo ingrese manualmente.
   - Actualiza `packing_slip_items.unit_price`, `price_tier_id`, `price_source`.
6. Crea los 3 `InvoiceItem` de cargos fijos con `is_fixed_charge = true`.
7. Llama `$invoice->calculateTotals()->save()`.
8. Actualiza `packing_slips.invoice_id`.
9. Retorna el Invoice creado.

**Paso 6.2 — Servicio `InvoicePdfService`**

Archivo: `app/Services/InvoicePdfService.php`

Usa el paquete `barryvdh/laravel-dompdf` (v3.1.1 ya instalado) para generar el PDF.

Responsabilidades:
1. Recibe un `Invoice` model con sus items cargados (`$invoice->load('items')`).
2. Renderiza la vista `resources/views/invoices/pdf.blade.php`.
3. Aplica configuracion de pagina: `paper = 'letter'`, `orientation = 'portrait'`.
4. Retorna el PDF como respuesta de descarga o como string binario para guardar en storage.

Nombre del archivo PDF: `Invoice_{invoice_number}_{invoice_date}.pdf`
Ejemplo: `Invoice_00954_2026-03-19.pdf`

**Paso 6.3 — Vista Blade para el PDF**

Archivo: `resources/views/invoices/pdf.blade.php`

Estructura del layout replicando FPL-12:
- Sin navegacion de la app
- Papel carta (8.5" x 11"), portrait
- Encabezado: nombre `FLEXCON`, tipo `INVOICE`, clave `FPL-12`, revision `01`, direccion
- Bloque de datos: Sold to / Shipped to / PS referenciado / F.O.B. / Numero Invoice / Fecha
- Tabla de items:
  - Columnas: DESCRIPTION | Item No. | LOT NO. | P.O No. | W.O No. | QUANTITY | UNIT COST | TOTAL
  - Filas de producto (is_fixed_charge = false)
  - Filas de cargo fijo (is_fixed_charge = true)
  - Fila GRAND TOTAL con suma de cantidad y suma de importe
- Fuentes: sans-serif, tamano 8-9pt para items (puede haber ~30 filas)
- Calculos monetarios con `number_format($value, 2)` para asegurar 2 decimales

**Nota sobre precision de calculos:** Usar `bcmul()` en PHP para el calculo `quantity x unit_cost` antes de guardar `line_total` en la BD. Ejemplo: `bcmul((string)$quantity, (string)$unitCost, 4)` para obtener 4 decimales, luego `round($result, 2)` para `line_total`.

---

### Fase 7: Interfaz de Usuario del Invoice

**Prerequisito:** Fase 6 completada.

**Paso 7.1 — Componente Livewire `InvoiceList`**

Archivo: `app/Livewire/Admin/Invoices/InvoiceList.php`
Vista: `resources/views/livewire/admin/invoices/invoice-list.blade.php`
Ruta: `GET /admin/invoices`

Funcionalidad:
- Listado paginado de Invoices (15 por pagina)
- Columnas: Invoice #, Fecha, PS Asociado, Estado, Cantidad Total, Grand Total, Acciones
- Filtros: por estado (draft/issued/paid), rango de fechas, busqueda por numero de Invoice o PS
- Acciones por fila: Ver detalle, Descargar PDF (si status=issued/paid)
- Boton "Nuevo Invoice Standalone" (para consumibles, pendiente de P-09-07)

**Paso 7.2 — Componente Livewire `InvoiceCreate`**

Archivo: `app/Livewire/Admin/Invoices/InvoiceCreate.php`
Vista: `resources/views/livewire/admin/invoices/invoice-create.blade.php`
Ruta: `GET /admin/invoices/create?packing_slip_id={id}` (o desde boton en PackingSlipShow)

Funcionalidad:
- Se carga pre-llenado con los datos del PS indicado
- Tabla de items con columna `UNIT COST` editable (los demas campos son solo lectura)
- Alerta visible si algun item tiene `unit_cost = 0` (parte sin precio en el catalogo)
- Calculo en tiempo real del `line_total` por item y del `grand_total` usando Alpine.js
- Cargos fijos editables (con permiso, pendiente P-09-05)
- Botones: "Guardar como Borrador" y "Crear y Emitir" (con confirmacion modal)
- Al emitir directamente: genera PDF y redirige a InvoiceShow con opcion de descarga

**Paso 7.3 — Componente Livewire `InvoiceShow`**

Archivo: `app/Livewire/Admin/Invoices/InvoiceShow.php`
Vista: `resources/views/livewire/admin/invoices/invoice-show.blade.php`
Ruta: `GET /admin/invoices/{invoice_number}`

Funcionalidad:
- Vista de solo lectura del Invoice con todos sus datos
- Si status = `draft`: boton "Editar precios" (recarga en modo edicion) y boton "Emitir"
- Si status = `issued` o `paid`: boton "Descargar PDF"
- Tabla de items (readonly): Description, Item No., LOT NO., P.O., W.O., Qty, Unit Cost, Total
- Tabla de cargos fijos
- Resumen de totales: Total piezas, Subtotal items, Subtotal cargos, Grand Total
- Enlace al Packing Slip origen (si type = 'product')

**Paso 7.4 — Controlador para descarga del PDF**

Archivo: `app/Http/Controllers/Admin/InvoiceController.php`
Ruta: `GET /admin/invoices/{invoice_number}/pdf`

```php
public function downloadPdf(Invoice $invoice): Response
{
    if (!$invoice->isPdfAvailable()) {
        abort(403, 'El Invoice aun no ha sido emitido.');
    }
    return app(InvoicePdfService::class)->download($invoice);
}
```

**Paso 7.5 — Actualizar `PackingSlipShow`**

En la vista de detalle del Packing Slip en estado `shipped`:
- Si `$ps->invoice_id === null`: mostrar boton prominente "Crear Invoice FPL-12"
- Si `$ps->invoice_id !== null`: mostrar enlace "Ver Invoice #XXXXX" con badge de estado (draft/issued)

**Paso 7.6 — Rutas**

En el archivo de rutas del admin (probablemente `routes/admin.php` o seccion protegida de `routes/web.php`):

```php
Route::middleware(['auth', 'verified', 'role:admin|Facturacion|Envios'])->group(function () {
    Route::get('/invoices',                  InvoiceList::class)->name('invoices.index');
    Route::get('/invoices/create',           InvoiceCreate::class)->name('invoices.create');
    Route::get('/invoices/{invoice_number}', InvoiceShow::class)->name('invoices.show');
    Route::get('/invoices/{invoice_number}/pdf',
               [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
});
```

**Nota sobre Route Model Binding para Invoice:** Implementar `getRouteKeyName()` en el modelo `Invoice` retornando `'invoice_number'`, de la misma manera que `PackingSlip` usa `'ps_number'`.

---

### Fase 8: Caso Especial y Refinamientos

**Prerequisito:** Fase 7 completada y respuestas del cliente a P-09-07 y P-09-08.

- Componente `InvoiceStandaloneCreate`: creacion de Invoice sin PS (consumibles/solventes).
- Importacion historica de precios: script Artisan `php artisan invoice:import-historical-prices` para poblar `prices` y `price_tiers` con los datos del Excel FPL-12 2025.xlsx.
- Importacion historica de Invoices (si el cliente confirma en P-09-08): comando Artisan para cargar los 21 Invoices del Excel como registros en `invoices` + `invoice_items` con status `issued`.
- Tests de integracion del flujo completo PS -> Invoice -> PDF.

---

## 8. Tabla de Decisiones

| ID | Decision | Estado | Notas |
|---|---|---|---|
| D-09-01 | Relacion Invoice-PS es 1:1 estricta para type=`product`; constraint UNIQUE en `invoices.packing_slip_id` | PROPUESTO | Confirmado por analisis del Excel (21 hojas, 1 PS por hoja) |
| D-09-02 | Tabla `invoices` con los campos descritos en apartado 4.1; separada de `packing_slips` | PROPUESTO | El Invoice tiene ciclo de vida, estados y datos financieros propios |
| D-09-03 | Tabla `invoice_items` separada de `packing_slip_items`; snapshot independiente al momento de facturacion | PROPUESTO | Los items del Invoice son un snapshot financiero; no modificar `packing_slip_items` retroactivamente |
| D-09-04 | Los campos `unit_price`, `price_tier_id`, `price_source` de `packing_slip_items` se llenan al generar el Invoice (no al crear el PS) | CONFIRMADO | Definido en migracion `2026_03_08_100003` con comentario explicito |
| D-09-05 | Agregar campo `invoice_id` a `packing_slips` via nueva migracion para navegacion bidireccional | PROPUESTO | Permite `$ps->hasInvoice()` sin consulta adicional |
| D-09-06 | Numeracion de Invoice: secuencial de 5 digitos comenzando en 00954 (continua desde ultimo del Excel) | PROPUESTO | **PENDIENTE CLIENTE** — confirmar si hay Invoices emitidos despues de May-2025 (P-09-02) |
| D-09-07 | Cargos fijos (Machine Maintenance $800, Admin Fee $250, Shipping Cost $450) se guardan como snapshot en `invoices`; valores configurables en `config/invoice.php` | PROPUESTO | **PENDIENTE CLIENTE** — confirmar invariabilidad (P-09-05) |
| D-09-08 | Fuente del precio: `Price::getActivePriceForPart()->getPriceForQuantity($poQuantity)` usando cantidad de la PO como cantidad de referencia para el tier | PROPUESTO | **PENDIENTE CLIENTE** — confirmar si el precio viene del catalogo o de la PO directamente (P-09-01) |
| D-09-09 | Campo LOT NO. del Invoice: derivado automaticamente como `MMDDYY_de_shipped_at` + `'x'` + `sufijo_tipo_estacion`; donde sufijo = `'01'` si `prices.workstation_type = 'table'`; `'20'` si `prices.workstation_type = 'machine'` o `'semi_automatic'` | PROPUESTO (pendiente confirmacion) | El diagrama `8-diagrama-flujo-invoice.mkd` confirma la estructura de dos partes del LOT NO. Pendiente confirmar si la fecha es `shipped_at` del PS o fecha de produccion del lote (P-09-03 actualizado) |
| D-09-10 | PDF generado con `barryvdh/laravel-dompdf` v3.1.1 (ya instalado); vista `resources/views/invoices/pdf.blade.php` | PROPUESTO | El paquete ya esta en `composer.json`; el mismo que usa el PS para su PDF |
| D-09-11 | Calculos de importes usando `bcmul()` en PHP + `DECIMAL(10,4)` para `unit_cost` y `DECIMAL(12,2)` para `line_total` en BD | PROPUESTO | Evita errores de punto flotante en sumas de ~20 items x cantidades de 100,000+ |
| D-09-12 | Invoice de tipo `standalone` (sin PS) para consumibles/solventes; `packing_slip_id = NULL` | PROPUESTO | **PENDIENTE CLIENTE** — confirmar frecuencia y formato (P-09-07) |
| D-09-13 | `InvoiceItem.wo_number` contiene solo los 7 digitos del `external_wo_number` (sin prefijo W0 ni sufijo 001), replicando el formato del Excel | PROPUESTO | Consistente con el formato observado en el Excel |
| D-09-14 | Los permisos del modulo Invoice se crean como: `invoice.view`, `invoice.create`, `invoice.issue`, `invoice.manage` | PROPUESTO | **PENDIENTE CLIENTE** — confirmar roles autorizados (P-09-06) |
| D-09-15 | Route Model Binding del Invoice usa `invoice_number` como clave de ruta (igual que PS usa `ps_number`) | PROPUESTO | Consistencia con el patron del modulo de PS |
| D-09-16 | Estado `paid` es opcional en Fase inicial; el Invoice puede estar en draft o issued sin implementar el flujo de pago | PROPUESTO | El pago se rastreara en una fase futura si el cliente lo requiere |
| D-09-17 | Importacion de precios historicos del Excel via comando Artisan (Fase 8) | PROPUESTO | **PENDIENTE CLIENTE** — confirmar si requiere importacion de historial (P-09-08) |

---

## 9. Tabla de Archivos Afectados

| Archivo | Tipo de cambio | Prioridad | Fase | Notas |
|---|---|---|---|---|
| `database/migrations/XXXX_create_invoices_table.php` | Nuevo archivo | Alta | 5 | Tabla principal del Invoice con campos de snapshot, cargos fijos, totales |
| `database/migrations/XXXX_create_invoice_items_table.php` | Nuevo archivo | Alta | 5 | Items del Invoice (productos + cargos fijos); FK a `invoices`, `packing_slip_items`, `lots`, `work_orders` |
| `database/migrations/XXXX_add_invoice_id_to_packing_slips_table.php` | Nuevo archivo | Alta | 5 | FK inversa `packing_slips.invoice_id` -> `invoices.id`; ejecutar despues de crear `invoices` |
| `config/invoice.php` | Nuevo archivo | Alta | 5 | Configuracion de cargos fijos, formato de numero, datos del emisor y del cliente |
| `app/Models/Invoice.php` | Nuevo archivo | Alta | 5 | Modelo con constantes de estado/tipo, `generateInvoiceNumber()`, `calculateTotals()`, relaciones |
| `app/Models/InvoiceItem.php` | Nuevo archivo | Alta | 5 | Modelo con relaciones y castings |
| `app/Models/PackingSlip.php` | Modificacion | Alta | 5 | Agregar `invoice_id` a `$fillable` y `$casts`; agregar relacion `hasOne(Invoice::class)` y metodo `hasInvoice()` |
| `app/Services/InvoiceFromPackingSlipService.php` | Nuevo archivo | Alta | 6 | Logica de conversion PS -> Invoice; usa `Price::getActivePriceForPart()` y `DB::transaction()` |
| `app/Services/InvoicePdfService.php` | Nuevo archivo | Alta | 6 | Generacion del PDF con `barryvdh/laravel-dompdf` |
| `resources/views/invoices/pdf.blade.php` | Nuevo archivo | Alta | 6 | Vista Blade del PDF del Invoice replicando formato FPL-12 |
| `app/Livewire/Admin/Invoices/InvoiceList.php` | Nuevo archivo | Alta | 7 | Listado de Invoices con filtros y paginacion |
| `resources/views/livewire/admin/invoices/invoice-list.blade.php` | Nuevo archivo | Alta | 7 | Vista del listado |
| `app/Livewire/Admin/Invoices/InvoiceCreate.php` | Nuevo archivo | Alta | 7 | Formulario de creacion desde PS; precios editables; calculo en tiempo real |
| `resources/views/livewire/admin/invoices/invoice-create.blade.php` | Nuevo archivo | Alta | 7 | Vista del formulario de creacion |
| `app/Livewire/Admin/Invoices/InvoiceShow.php` | Nuevo archivo | Alta | 7 | Vista de detalle + boton emitir + boton PDF |
| `resources/views/livewire/admin/invoices/invoice-show.blade.php` | Nuevo archivo | Alta | 7 | Vista de detalle |
| `app/Http/Controllers/Admin/InvoiceController.php` | Nuevo archivo | Alta | 7 | Endpoint `downloadPdf()` para descarga del PDF |
| `routes/web.php` (o archivo de rutas admin) | Modificacion | Alta | 7 | Agregar 4 rutas del modulo Invoice |
| `resources/views/livewire/admin/packing-slips/packing-slip-show.blade.php` | Modificacion | Alta | 7 | Agregar boton "Crear Invoice" (si no tiene) o enlace "Ver Invoice XXXXX" (si ya tiene) |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | Modificacion | Media | 7 | Agregar logica de estado de Invoice (hasInvoice, invoice->status) |
| `database/migrations/2026_03_08_100003_create_packing_slip_items_table.php` | Sin cambio | — | — | Los campos `unit_price`, `price_tier_id`, `price_source` ya existen; solo se activan al generar el Invoice |
| `app/Models/PackingSlipItem.php` | Sin cambio | — | — | Los metodos `getLineTotalAttribute()` y `getPriceSourceLabelAttribute()` ya existen |
| `app/Models/Price.php` | Sin cambio | — | — | `Price::getActivePriceForPart()` y `getPriceForQuantity()` ya implementados |
| `app/Models/PriceTier.php` | Sin cambio | — | — | `matchesQuantity()` ya implementado |
| `tests/Unit/Models/InvoiceTest.php` | Nuevo archivo | Media | 5 | Tests de `generateInvoiceNumber()`, `calculateTotals()`, estados |
| `tests/Unit/Models/InvoiceItemTest.php` | Nuevo archivo | Media | 5 | Tests de relaciones y castings |
| `tests/Feature/InvoiceFromPackingSlipServiceTest.php` | Nuevo archivo | Media | 6 | Tests del servicio de conversion PS -> Invoice |
| `tests/Feature/InvoicePdfServiceTest.php` | Nuevo archivo | Baja | 6 | Tests de generacion del PDF |
| `app/Livewire/Admin/Invoices/InvoiceStandaloneCreate.php` | Nuevo archivo | Baja | 8 | Solo si el cliente confirma P-09-07 |
| `app/Console/Commands/ImportHistoricalPrices.php` | Nuevo archivo | Baja | 8 | Solo si el cliente confirma P-09-08 |
| `app/Console/Commands/ImportHistoricalInvoices.php` | Nuevo archivo | Baja | 8 | Solo si el cliente confirma P-09-08 |

---

## 10. Riesgos y Consideraciones Tecnicas

### 10.1 Precision de Calculos Monetarios

Los precios unitarios tienen hasta 4 decimales (ej: `0.0912`, `0.1752`). Las cantidades son del orden de 100,000 piezas. El producto puede generar imprecisiones de punto flotante (ej: `100000 * 0.138 = 13800.000000000002` en PHP con floats nativos).

**Mitigacion:** Almacenar `unit_cost` como `DECIMAL(10,4)` y `line_total` como `DECIMAL(12,2)` en la BD. En PHP, calcular `line_total` con `bcmul((string)$quantity, (string)$unitCost, 4)` y redondear a 2 decimales. El grand total se calcula sumando los `line_total` desde la BD (no en PHP nativo).

### 10.2 Referencia Circular en Migraciones

La tabla `invoices` tiene FK a `packing_slips`, y la tabla `packing_slips` tendra FK a `invoices`. Esto requiere que:
1. La migracion `create_invoices_table` se ejecute ANTES de `add_invoice_id_to_packing_slips`.
2. La migracion de `create_invoices_table` ya puede referenciar `packing_slips` porque esa tabla existe (fue creada en `2026_03_08_100002`).
3. La FK inversa (`packing_slips.invoice_id`) se agrega en una migracion separada posterior.

**Orden de ejecucion de las nuevas migraciones:**

| Orden | Migracion | Dependencia |
|---|---|---|
| 1 | `create_invoices_table` | `packing_slips` existe, `users` existe |
| 2 | `create_invoice_items_table` | `invoices` existe, `packing_slip_items` existe, `lots` existe |
| 3 | `add_invoice_id_to_packing_slips_table` | `invoices` existe |

### 10.3 Concurrencia al Crear el Invoice

Si dos usuarios intentan crear el Invoice del mismo Packing Slip al mismo tiempo, el constraint UNIQUE en `invoices.packing_slip_id` rechazara el segundo intento a nivel de BD. Sin embargo, para dar un mensaje de error amigable al usuario, el servicio debe validar antes de insertar usando `DB::transaction()` con un `SELECT ... FOR UPDATE` sobre el registro del PS.

### 10.4 Formato de LOT NO. en el Invoice

El formato `052625x01` del Invoice es una construccion compuesta de dos partes confirmada por el diagrama de flujo `8-diagrama-flujo-invoice.mkd`:

**Componente 1 — Fecha (MMDDYY):**
La fecha del despacho del PS (`packing_slips.shipped_at`) formateada como MMDDYY es la fuente candidata principal. El ejemplo `052625` corresponde al 26 de mayo de 2025 (`shipped_at = 2025-05-26`), lo cual es consistente con la fecha del Invoice #00953 del Excel. Alternativa pendiente de confirmar: usar la fecha de produccion del lote en lugar de la fecha de despacho.

**Componente 2 — Sufijo de tipo de estacion (x01 / x20):**
El diagrama confirma que el sufijo indica el tipo de estacion con la que se produjo el lote:
- `x01` = Mesa de trabajo / Empleado (equivale a `prices.workstation_type = 'table'`)
- `x20` = Maquina (equivale a `prices.workstation_type = 'machine'` o `'semi_automatic'`)

**Logica de derivacion automatica propuesta:**
Esta logica puede implementarse completamente en `InvoiceFromPackingSlipService` sin requerir intervencion manual del usuario:

```
1. Obtener el precio activo de la parte: Price::getActivePriceForPart($partId)
2. Leer $price->workstation_type
3. sufijo = ($price->workstation_type === 'table') ? '01' : '20'
4. fecha = $invoice->packingSlip->shipped_at->format('mdY') truncado a 6 digitos (MMDDYY)
5. lot_number = $fecha . 'x' . $sufijo
```

**Condicion para la automatizacion completa:** La parte debe tener un precio activo con `workstation_type` definido. Si una parte no tiene precio activo, el campo `lot_number` se dejara en NULL y el usuario debera ingresarlo manualmente al editar el Invoice en estado `draft`.

**Pregunta pendiente reducida:** Confirmar si la fecha es `shipped_at` del PS o fecha de produccion del lote. Ver P-09-03 actualizado en seccion 6.

### 10.5 Impacto en Tests Existentes

Los tests existentes que usan `PackingSlipItem` no se ven afectados porque los nuevos campos (`unit_price`, `price_tier_id`, `price_source`) son nullable. Los tests de `PackingSlip` que no incluyen `invoice_id` siguen funcionando porque el campo es nullable.

### 10.6 Generacion del PDF con `barryvdh/laravel-dompdf` v3.1.1

El Invoice tiene mas columnas que el PS (agrega UNIT COST y TOTAL), lo que hace mas critico el ajuste de anchos en el PDF. El Invoice tipico tiene ~20 items de producto + 3 cargos fijos = ~23 filas en la tabla, lo que cabe holgadamente en una pagina carta. Para Invoices con muchos items (el maximo observado fue 31 items), se debe implementar paginacion automatica del PDF.

Recomendacion: usar fuente 8pt para la tabla de items (igual que en el PS), reducir el padding de celdas y verificar con el Invoice #00934 (31 items, el mas grande del Excel) que el layout cabe en una pagina.

---

## 11. Resumen Ejecutivo

| Concepto | Valor |
|---|---|
| Documentos base analizados | FPL-12 Invoice 2025.xlsx (22 hojas, 21 Invoices de producto) |
| Relacion con Packing Slip | 1:1 estricta para type=product |
| Campos pre-existentes aprovechados | `unit_price`, `price_tier_id`, `price_source` en `packing_slip_items` |
| Sistema de precios ya implementado | Si — tablas `prices` + `price_tiers`, modelos `Price` y `PriceTier` con logica de tiers |
| Paquete PDF disponible | `barryvdh/laravel-dompdf` v3.1.1 (ya en `composer.json`) |
| Nuevas tablas requeridas | 2 (`invoices` + `invoice_items`) |
| Tablas modificadas | 1 (`packing_slips`: agregar `invoice_id`) |
| Nuevos modelos | 2 (`Invoice` + `InvoiceItem`) |
| Modelos modificados | 1 (`PackingSlip`) |
| Nuevos servicios | 2 (`InvoiceFromPackingSlipService` + `InvoicePdfService`) |
| Nuevos componentes Livewire | 3 (`InvoiceList`, `InvoiceCreate`, `InvoiceShow`) |
| Decisiones pendientes del cliente | 8 (P-09-01 a P-09-08) |
| Primer numero de Invoice del sistema | 00954 (continua desde el Excel) |
| Fases de implementacion | 4 (Fases 5 a 8, en continuidad con las fases del PS) |
| Bloqueante critico antes de Fase 5 | Respuesta a P-09-01 (fuente del precio) y P-09-02 (numeracion) |
| Bloqueante critico antes de Fase 6 | Respuesta a P-09-03 (formato LOT NO. en Invoice) |
