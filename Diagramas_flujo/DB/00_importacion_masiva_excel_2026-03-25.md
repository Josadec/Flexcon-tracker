# Analisis Tecnico: Importacion Masiva de Datos desde Excel

**Proyecto**: Flexcon-Tracker
**Fecha**: 2026-03-25
**Version**: 1.0
**Autor**: Agent Architect
**Alcance**: Importacion masiva de Parts, Prices y Standards via phpMyAdmin

---

## Tabla de Contenidos

1. [Estructura de la Base de Datos](#1-estructura-de-la-base-de-datos)
2. [Analisis del Excel del Cliente](#2-analisis-del-excel-del-cliente)
3. [Diseno del Excel de Importacion para Parts](#3-diseno-del-excel-de-importacion-para-parts)
4. [Diseno del Excel de Importacion para Prices](#4-diseno-del-excel-de-importacion-para-prices)
5. [Diseno del Excel de Importacion para Standards](#5-diseno-del-excel-de-importacion-para-standards)
6. [Proceso de Importacion via phpMyAdmin](#6-proceso-de-importacion-via-phpmyadmin)
7. [Analisis de Impactos y Riesgos](#7-analisis-de-impactos-y-riesgos)
8. [Recomendaciones Pre-Importacion](#8-recomendaciones-pre-importacion)
9. [Plan de Rollback](#9-plan-de-rollback)

---

## 1. Estructura de la Base de Datos

### 1.1 Tabla `parts`

La tabla central del catalogo de productos. Toda importacion de precios y standards depende de que la parte exista aqui primero.

| Columna         | Tipo                | Nullable | Default | Restriccion         |
|-----------------|---------------------|----------|---------|---------------------|
| id              | BIGINT UNSIGNED     | NO       | auto    | PK, AUTO_INCREMENT  |
| number          | VARCHAR(255)        | NO       | -       | UNIQUE              |
| item_number     | VARCHAR(255)        | NO       | -       | UNIQUE              |
| unit_of_measure | VARCHAR(255)        | YES      | NULL    |                     |
| active          | TINYINT(1)          | NO       | 1       |                     |
| is_crimp        | TINYINT(1)          | NO       | 1       |                     |
| label_spec      | VARCHAR(150)        | YES      | NULL    | Especif. militar/aeronautica |
| description     | TEXT                | YES      | NULL    |                     |
| notes           | VARCHAR(255)        | YES      | NULL    |                     |
| deleted_at      | TIMESTAMP           | YES      | NULL    | Soft Delete         |
| created_at      | TIMESTAMP           | YES      | NULL    |                     |
| updated_at      | TIMESTAMP           | YES      | NULL    |                     |

**Indices**:
- `UNIQUE KEY` sobre `number`
- `UNIQUE KEY` sobre `item_number`
- `INDEX` compuesto sobre `(number, active, item_number)`
- `INDEX` sobre `deleted_at` (implícito por SoftDeletes)

**Notas criticas**:
- `number` e `item_number` son AMBOS unicos e independientes. En el Excel del cliente, el campo "Item no" (ej: `189-10004`) corresponde a `item_number`. El campo `number` es el numero interno de Flexcon (diferente).
- `is_crimp` distingue si la parte es un ensamble de tipo crimp (default `true`).
- El SoftDelete (`deleted_at`) significa que una parte "borrada" sigue en la BD. Si se intenta insertar una parte con el mismo `number` o `item_number` que una ya borrada, se producira un error de constraint UNIQUE.

---

### 1.2 Tabla `prices`

Almacena el precio base (sample_price) y el tipo de estacion de trabajo para cada parte.

| Columna          | Tipo                                          | Nullable | Default | Restriccion |
|------------------|-----------------------------------------------|----------|---------|-------------|
| id               | BIGINT UNSIGNED                               | NO       | auto    | PK          |
| part_id          | BIGINT UNSIGNED                               | NO       | -       | FK -> parts.id |
| sample_price     | DECIMAL(10,4)                                 | NO       | -       | Precio muestra/base |
| workstation_type | ENUM('table','machine','semi_automatic')       | NO       | 'table' |             |
| effective_date   | DATE                                          | NO       | -       |             |
| active           | TINYINT(1)                                    | NO       | 1       |             |
| comments         | TEXT                                          | YES      | NULL    |             |
| created_at       | TIMESTAMP                                     | YES      | NULL    |             |
| updated_at       | TIMESTAMP                                     | YES      | NULL    |             |

**Indices**:
- `INDEX` sobre `(part_id, active, effective_date)`
- `INDEX` sobre `(workstation_type)`

**Restriccion critica - TRIGGER de unicidad**:
La migracion `2026_01_22` creo dos TRIGGERS en MySQL:
- `check_unique_active_price_before_insert`: Bloquea INSERT si ya existe un precio `active=1` para la misma combinacion `(part_id, workstation_type)`.
- `check_unique_active_price_before_update`: Bloquea UPDATE si el cambio crea un conflicto similar.

**Esto significa**: Solo puede existir UN precio activo por parte por tipo de estacion de trabajo. Si se intenta insertar un segundo precio activo para la misma parte y mismo tipo, el TRIGGER lanzara el error SQLSTATE 45000 con mensaje: "Ya existe un precio activo para este tipo de estacion de trabajo".

---

### 1.3 Tabla `price_tiers`

Precios por volumen (rangos de cantidad). Cada registro de `prices` puede tener multiples tiers.

| Columna      | Tipo            | Nullable | Default | Restriccion |
|--------------|-----------------|----------|---------|-------------|
| id           | BIGINT UNSIGNED | NO       | auto    | PK          |
| price_id     | BIGINT UNSIGNED | NO       | -       | FK -> prices.id (CASCADE) |
| min_quantity | INT UNSIGNED    | NO       | -       |             |
| max_quantity | INT UNSIGNED    | YES      | NULL    | NULL = sin limite superior |
| tier_price   | DECIMAL(10,4)   | NO       | -       |             |
| created_at   | TIMESTAMP       | YES      | NULL    |             |
| updated_at   | TIMESTAMP       | YES      | NULL    |             |

**Restriccion UNIQUE**: `(price_id, min_quantity, max_quantity)` - no pueden existir dos tiers identicos para el mismo precio.

**Configuracion de tiers por tipo de estacion** (definida en el modelo `Price.php`):

| Tipo de Estacion | Tier 1         | Tier 2               | Tier 3              | Tier 4       |
|------------------|----------------|----------------------|---------------------|--------------|
| `table`          | 1 - 999        | 1,000 - 10,999       | 11,000 - 99,999     | 100,000+     |
| `machine`        | 1 - 9,999      | 10,000 - 49,999      | 50,000+             | -            |
| `semi_automatic` | 2,000 - 10,000 | 11,000+ (sin limite) | -                   | -            |

---

### 1.4 Tabla `standards`

Estandar de produccion que vincula una parte con una estacion de trabajo y define la productividad.

| Columna                | Tipo            | Nullable | Default | Restriccion |
|------------------------|-----------------|----------|---------|-------------|
| id                     | BIGINT UNSIGNED | NO       | auto    | PK          |
| part_id                | BIGINT UNSIGNED | NO       | -       | FK -> parts.id (CASCADE) |
| work_table_id          | BIGINT UNSIGNED | YES      | NULL    | FK -> tables.id (SET NULL) |
| semi_auto_work_table_id| BIGINT UNSIGNED | YES      | NULL    | FK -> semi__automatics.id (SET NULL) |
| machine_id             | BIGINT UNSIGNED | YES      | NULL    | FK -> machines.id (SET NULL) |
| units_per_hour         | INT             | NO       | 1       | Unidades/hora |
| persons_1              | INT             | YES      | NULL    |             |
| persons_2              | INT             | YES      | NULL    |             |
| persons_3              | INT             | YES      | NULL    |             |
| active                 | TINYINT(1)      | NO       | 1       |             |
| is_migrated            | TINYINT(1)      | NO       | 0       | Control de migracion |
| description            | TEXT            | YES      | NULL    |             |
| deleted_at             | TIMESTAMP       | YES      | NULL    | Soft Delete |
| created_at             | TIMESTAMP       | YES      | NULL    |             |
| updated_at             | TIMESTAMP       | YES      | NULL    |             |

**Nota arquitectural**: La columna `effective_date` fue ELIMINADA en la migracion `2026_02_13_remove_effective_date_from_standards_table`. No existe en la BD actual.

---

### 1.5 Tabla `standard_configurations`

Nueva estructura para multiples configuraciones por standard (un standard puede tener configuraciones para 1, 2 o 3 personas y diferentes tipos de estacion).

| Columna          | Tipo                                      | Nullable | Default | Restriccion |
|------------------|-------------------------------------------|----------|---------|-------------|
| id               | BIGINT UNSIGNED                           | NO       | auto    | PK          |
| standard_id      | BIGINT UNSIGNED                           | NO       | -       | FK -> standards.id (CASCADE) |
| workstation_type | ENUM('manual','semi_automatic','machine') | NO       | -       |             |
| workstation_id   | BIGINT UNSIGNED                           | YES      | NULL    | FK polimorfica |
| persons_required | TINYINT UNSIGNED                          | NO       | 1       | Max 3       |
| units_per_hour   | INT UNSIGNED                              | NO       | -       |             |
| is_default       | TINYINT(1)                                | NO       | 0       |             |
| notes            | TEXT                                      | YES      | NULL    |             |
| created_at       | TIMESTAMP                                 | YES      | NULL    |             |
| updated_at       | TIMESTAMP                                 | YES      | NULL    |             |

**Restriccion UNIQUE**: `(standard_id, workstation_type, persons_required)` - no pueden repetirse combinaciones identicas.

---

### 1.6 Diagrama de Relaciones (ERD simplificado)

```
parts (id, number[UNIQUE], item_number[UNIQUE], ...)
  |
  |--< prices (id, part_id[FK], workstation_type, sample_price, active, ...)
  |      |
  |      |--< price_tiers (id, price_id[FK], min_quantity, max_quantity, tier_price)
  |
  |--< standards (id, part_id[FK], work_table_id[FK], machine_id[FK], units_per_hour, ...)
         |
         |--< standard_configurations (id, standard_id[FK], workstation_type, persons_required, units_per_hour, ...)
```

**Tablas de referencia** (deben existir ANTES de importar standards):
- `tables` -> numeros de mesas de trabajo manuales
- `semi__automatics` -> mesas semi-automaticas (nota: nombre con doble guion bajo)
- `machines` -> maquinas de produccion

---

## 2. Analisis del Excel del Cliente

**Archivo analizado**: `FDI-81 Price List Flexcon (+20_ - 2024) Rev 4 - (05-20-2025).xlsx`

### 2.1 Estructura General del Archivo

El archivo contiene **4 hojas (sheets)**:

| Hoja               | Proposito                            | Filas de datos | Items unicos |
|--------------------|--------------------------------------|----------------|--------------|
| `pricing`          | Lista de precios para mesas (table)  | 522 filas      | ~428 unicos  |
| `non lead machine` | Precios para table Y machine         | ~15 filas      | ~8 unicos    |
| `Semi Automatic Tables` | Precios semi-automaticas        | ~14 filas      | ~14 unicos   |
| `Sheet1`           | Partes pendientes (sin precio)       | 10 filas       | 10 unicos    |

### 2.2 Estructura de la Hoja `pricing` (principal)

```
Fila 1:  ENSAMBLES FORMULA                     <- Nombre de empresa
Fila 2:  [vacio]  Price List   [vacio]...  Code: FDI-81
Fila 4:  [vacio]  [vacio]  ...  Effective Date: Jan-01-2024
Fila 5:  [vacio]  [vacio]  ...  Revision: 01
Fila 7:  [ENCABEZADOS]
Fila 8+: [DATOS]
```

**Columnas de encabezado (Fila 7)**:

| Col | Nombre en Excel                                          | Mapeo BD           | Tipo         |
|-----|----------------------------------------------------------|--------------------|--------------|
| A   | Item no                                                  | parts.item_number  | STRING       |
| B   | PRODUCT                                                  | parts.description  | STRING       |
| C   | Min. Order Qty. 1 To 999 Cost Per 100Pcs.                | price_tiers tier1  | DECIMAL      |
| D   | 20% Increase 1,000 To 10,999 Cost Per 100 Pcs.          | price_tiers tier2  | DECIMAL      |
| E   | 20% Increase 11,000 To 99,999 Cost Per 100 Pcs.         | price_tiers tier3  | DECIMAL      |
| F   | 20% Increase Over 100,000 Cost Per 100 Pcs.             | price_tiers tier4  | DECIMAL      |
| G   | Comments                                                 | prices.comments    | STRING       |
| H   | (sin nombre / notas adicionales)                         | -                  | -            |

**Observaciones criticas del analisis**:

1. **Multiples filas por item_number**: El mismo numero de parte aparece varias veces con diferentes precios segun variantes (con/sin accesorios: "w/ 408-20032", "BI", etc.). Esto NO es un error; cada fila representa una variante del ensamble.

2. **Precios en centavos por 100 piezas**: Los precios en el Excel estan expresados como "Cost Per 100 Pcs." (costo por 100 piezas). El sistema guarda precios unitarios. **Conversion necesaria: dividir entre 100**.

3. **Valores "N/A" en columna C**: Algunos items maquina no tienen tier1 (1-999), la columna muestra "N/A" (string), no un numero.

4. **Patron de numero de parte**: Todos siguen el patron `189-XXXXX` (prefijo numerico de 3 digitos, guion, 5 digitos). Ejemplo: `189-10004`.

5. **Sin campo `number` de Flexcon**: El Excel del cliente solo tiene el `item_number` del cliente. El campo `number` (numero interno de Flexcon) NO esta en el Excel y debe ser asignado manualmente o generado.

6. **Datos de precios en la hoja `pricing`**: Rango de precios: min $25.00, max $55.35 por 100 piezas (tier 1).

### 2.3 Estructura de la Hoja `non lead machine`

Partes que tienen precio TANTO para mesa (`table`) como para maquina (`machine`). El workstation_type se indica entre parentesis en el campo PRODUCT: "STS H-C-2 (Table)" vs "STS H-C-2 (Machine)".

**Columnas**:

| Col | Hoja "non lead machine" | Equivalencia en BD        |
|-----|-------------------------|---------------------------|
| A   | Item no                 | parts.item_number         |
| B   | PRODUCT (con tipo)      | parts.description + tipo  |
| C   | 1 to 999                | price_tiers tier1 (table) |
| D   | 1000 TO 49,999          | price_tiers tier2 (machine)|
| E   | Over 50,000             | price_tiers tier3 (machine)|
| F   | Comments                | prices.comments           |

### 2.4 Estructura de la Hoja `Semi Automatic Tables`

| Col | Hoja "Semi Automatic"   | Equivalencia en BD               |
|-----|-------------------------|----------------------------------|
| A   | Item no                 | parts.item_number                |
| B   | PRODUCT                 | parts.description                |
| C   | 2,000 TO 10,000         | price_tiers (min=2000, max=10000)|
| D   | Over 11,000             | price_tiers (min=11000, max=null)|
| E   | Comments                | prices.comments                  |

### 2.5 Mapeo Completo Excel -> Base de Datos

```
Excel (item_number) "189-10004"  -->  parts.item_number = "189-10004"
                                 -->  parts.number = ? (DEBE DEFINIRSE)
Excel (PRODUCT) "STS H-CL-181"  -->  parts.description = "STS H-CL-181"
Excel Col C (pricing) 52.71      -->  prices.sample_price = 52.71/100 = 0.5271
                                 -->  price_tiers: min=1, max=999, tier_price=0.5271
Excel Col D (pricing) 50.0802    -->  price_tiers: min=1000, max=10999, tier_price=0.500802
Excel Col E (pricing) 47.4582    -->  price_tiers: min=11000, max=99999, tier_price=0.474582
Excel Col F (pricing) 44.8362    -->  price_tiers: min=100000, max=NULL, tier_price=0.448362
Excel Col G "w/ 408-20032"       -->  prices.comments = "w/ 408-20032"
```

---

## 3. Diseno del Excel de Importacion para Parts

### 3.1 Estructura del Archivo CSV/Excel

**Nombre sugerido**: `import_parts_YYYY-MM-DD.csv`

El archivo debe tener exactamente estas columnas en la FILA 1 (encabezados), sin filas de titulo ni encabezados adicionales.

### 3.2 Especificacion de Columnas

| # | Nombre de Columna | Columna en BD       | Tipo        | Requerido | Valores Validos                    | Ejemplo              |
|---|-------------------|---------------------|-------------|-----------|-------------------------------------|----------------------|
| 1 | number            | parts.number        | VARCHAR(255)| SI        | Unico, sin caracteres especiales    | FLX-10004            |
| 2 | item_number       | parts.item_number   | VARCHAR(255)| SI        | Unico, formato cliente              | 189-10004            |
| 3 | description       | parts.description   | TEXT        | NO        | Texto libre                         | STS H-CL-181-1222    |
| 4 | unit_of_measure   | parts.unit_of_measure| VARCHAR(255)| NO       | EA, PCS, KIT, etc.                 | EA                   |
| 5 | active            | parts.active        | TINYINT     | NO        | 1 (activo) o 0 (inactivo)          | 1                    |
| 6 | is_crimp          | parts.is_crimp      | TINYINT     | NO        | 1 (si) o 0 (no)                    | 1                    |
| 7 | label_spec        | parts.label_spec    | VARCHAR(150)| NO        | Especificacion militar o NULL       | M83519/2-8           |
| 8 | notes             | parts.notes         | VARCHAR(255)| NO        | Texto libre o NULL                  | Incluye inserto       |

### 3.3 Ejemplo de Filas del Archivo

```
number,item_number,description,unit_of_measure,active,is_crimp,label_spec,notes
FLX-10004,189-10004,STS H-CL-181-1222-90/9,EA,1,1,,
FLX-10005,189-10005,STS H-CL-181-1224-90/9,EA,1,1,,
FLX-10006,189-10006,STS H-CL-181-1226-90/9,EA,1,1,,
FLX-10009,189-10009,STS H-CL-181-2222-90/9,EA,1,1,,
FLX-10010,189-10010,STS H-CL-181-2222-90/9-10,EA,1,1,,
FLX-10044,189-10044,STS H-C-1,EA,1,1,,BI
FLX-10046,189-10046,STS H-C110-35,EA,1,1,,
FLX-10056,189-10056,STS H-C-2,EA,1,0,,
FLX-10601,189-10601,STS H-MB-1,EA,1,1,,Pendiente de precio
```

### 3.4 Reglas de Validacion Pre-Importacion

1. **number**: No debe existir ya en `parts.number` (ni como registro activo ni como soft-deleted).
2. **item_number**: No debe existir ya en `parts.item_number`.
3. **active**: Debe ser exactamente `0` o `1`. Si se deja vacio, MySQL usara el default `1`.
4. **is_crimp**: Debe ser exactamente `0` o `1`. Default es `1`.
5. **Columnas vacias**: Para campos NULL, dejar la celda vacia (no escribir "NULL" como texto).
6. **Encoding**: Guardar en UTF-8 sin BOM.
7. **Delimitador**: Coma (`,`). Si los datos contienen comas, encerrar el campo en comillas dobles.

### 3.5 Script SQL de Verificacion Pre-Importacion

Ejecutar ANTES de importar para detectar duplicados:

```sql
-- Verificar si ya existen los item_numbers a importar
-- Reemplazar los valores con los del archivo a importar
SELECT item_number, number, deleted_at
FROM parts
WHERE item_number IN ('189-10004', '189-10005', '189-10006')
ORDER BY item_number;

-- Verificar registros con soft-delete que puedan causar conflicto
SELECT item_number, number, deleted_at
FROM parts
WHERE deleted_at IS NOT NULL
  AND item_number IN ('189-10004', '189-10005', '189-10006');
```

---

## 4. Diseno del Excel de Importacion para Prices

### 4.1 Consideraciones de Arquitectura

La tabla `prices` tiene una relacion compleja:
- Cada precio necesita el `part_id` (ID numerico de la parte en BD, no el `item_number`).
- Los tiers se guardan en tabla separada `price_tiers`.
- El TRIGGER de MySQL bloqueara la insercion de un segundo precio activo para la misma parte y tipo de estacion.

**Estrategia recomendada**: Importar en DOS archivos CSV separados:
1. **Archivo 1**: `import_prices.csv` - Un registro por parte por tipo de estacion.
2. **Archivo 2**: `import_price_tiers.csv` - Los tiers de precio para cada price_id.

### 4.2 Archivo 1: `import_prices.csv`

**Columnas**:

| # | Nombre de Columna | Columna en BD            | Tipo        | Requerido | Valores Validos                        |
|---|-------------------|--------------------------|-------------|-----------|----------------------------------------|
| 1 | part_id           | prices.part_id           | BIGINT      | SI        | ID numerico existente en parts.id      |
| 2 | sample_price      | prices.sample_price      | DECIMAL(10,4)| SI       | Precio unitario (no por 100 piezas)    |
| 3 | workstation_type  | prices.workstation_type  | ENUM        | SI        | 'table', 'machine', 'semi_automatic'   |
| 4 | effective_date    | prices.effective_date    | DATE        | SI        | Formato YYYY-MM-DD                     |
| 5 | active            | prices.active            | TINYINT     | SI        | 1 o 0                                  |
| 6 | comments          | prices.comments          | TEXT        | NO        | Texto libre o vacio                    |

**Ejemplo de filas** (precios en unidades, NO por 100 piezas):

```
part_id,sample_price,workstation_type,effective_date,active,comments
1,0.5271,table,2024-01-01,1,
2,0.5271,table,2024-01-01,1,
3,0.5271,table,2024-01-01,1,
4,0.5271,table,2024-01-01,1,
1,0.1385,semi_automatic,2024-01-01,1,FLX-00339
5,0.2500,table,2024-01-01,1,BI
5,0.0812,machine,2024-01-01,1,BI
```

**Conversion de precios del Excel**: Los precios en el Excel son "por 100 piezas". Para convertir:
```
sample_price_bd = precio_excel / 100

Ejemplo: Excel Col C = 52.71  -->  BD = 52.71 / 100 = 0.5271
Ejemplo: Excel Col C = 25.00  -->  BD = 25.00 / 100 = 0.2500
```

### 4.3 Archivo 2: `import_price_tiers.csv`

**IMPORTANTE**: Este archivo solo puede generarse DESPUES de importar `import_prices.csv`, porque necesita los `price_id` autogenerados por MySQL.

**Consulta para obtener price_ids despues de importar prices**:
```sql
SELECT p.id AS price_id, pt.item_number, p.workstation_type, p.effective_date
FROM prices p
JOIN parts pt ON p.part_id = pt.id
ORDER BY pt.item_number, p.workstation_type;
```

**Columnas del archivo de tiers**:

| # | Nombre de Columna | Columna en BD             | Tipo          | Requerido | Valores Validos              |
|---|-------------------|---------------------------|---------------|-----------|------------------------------|
| 1 | price_id          | price_tiers.price_id      | BIGINT        | SI        | ID de la tabla prices        |
| 2 | min_quantity      | price_tiers.min_quantity  | INT UNSIGNED  | SI        | Entero positivo              |
| 3 | max_quantity      | price_tiers.max_quantity  | INT UNSIGNED  | NO        | Entero positivo o vacio(NULL)|
| 4 | tier_price        | price_tiers.tier_price    | DECIMAL(10,4) | SI        | Precio unitario por tier     |

**Ejemplo de filas** (asumiendo que price_id=1 es la parte 189-10004 tipo 'table'):

```
price_id,min_quantity,max_quantity,tier_price
1,1,999,0.5271
1,1000,10999,0.5008
1,11000,99999,0.4746
1,100000,,0.4484
2,1,999,0.5271
2,1000,10999,0.5008
2,11000,99999,0.4746
2,100000,,0.4484
```

**Nota sobre `max_quantity` NULL**: En CSV, una celda vacia se importa como NULL en phpMyAdmin cuando la columna es nullable. NO escribir la palabra "NULL".

### 4.4 Mapeo de Tiers por Tipo de Estacion

**Tipo `table` (hoja "pricing")** - 4 tiers:

| Tier | min_quantity | max_quantity | Columna Excel |
|------|-------------|--------------|---------------|
| 1    | 1           | 999          | Col C         |
| 2    | 1000        | 10999        | Col D         |
| 3    | 11000       | 99999        | Col E         |
| 4    | 100000      | NULL (vacio) | Col F         |

**Tipo `machine` (hoja "non lead machine", filas Machine)** - 3 tiers:

| Tier | min_quantity | max_quantity | Columna Excel |
|------|-------------|--------------|---------------|
| 1    | 1           | 9999         | Col C         |
| 2    | 10000       | 49999        | Col D         |
| 3    | 50000       | NULL (vacio) | Col E         |

**Tipo `semi_automatic` (hoja "Semi Automatic Tables")** - 2 tiers:

| Tier | min_quantity | max_quantity | Columna Excel |
|------|-------------|--------------|---------------|
| 1    | 2000        | 10000        | Col C         |
| 2    | 11000       | NULL (vacio) | Col D         |

---

## 5. Diseno del Excel de Importacion para Standards

### 5.1 Consideraciones de Arquitectura

La tabla `standards` es la mas compleja porque:
- Requiere que la parte exista en `parts` (foreign key `part_id`).
- Requiere que la estacion de trabajo exista en `tables`, `semi__automatics` o `machines`.
- La columna `is_migrated` indica si el standard ya usa `standard_configurations`.
- El campo `effective_date` fue eliminado; NO debe incluirse.

**Para importacion masiva inicial se recomienda usar el enfoque legacy** (campos `persons_1`, `persons_2`, `persons_3`, `units_per_hour` en la tabla `standards`), con `is_migrated = 0`.

### 5.2 Consultas Previas para Obtener IDs de Referencia

Antes de preparar el archivo de standards, obtener los IDs existentes:

```sql
-- Obtener IDs de mesas de trabajo manuales
SELECT id, number FROM tables WHERE deleted_at IS NULL ORDER BY number;

-- Obtener IDs de semi-automaticos
SELECT id, number FROM semi__automatics WHERE deleted_at IS NULL ORDER BY number;

-- Obtener IDs de maquinas
SELECT id, name, brand, model FROM machines WHERE deleted_at IS NULL ORDER BY name;

-- Obtener IDs de parts importadas
SELECT id, number, item_number FROM parts WHERE deleted_at IS NULL ORDER BY item_number;
```

### 5.3 Archivo: `import_standards.csv`

**Columnas**:

| # | Nombre de Columna        | Columna en BD                  | Tipo        | Requerido | Valores Validos                          |
|---|--------------------------|-------------------------------|-------------|-----------|------------------------------------------|
| 1 | part_id                  | standards.part_id              | BIGINT      | SI        | ID existente en parts.id                 |
| 2 | work_table_id            | standards.work_table_id        | BIGINT      | NO        | ID en tables.id o vacio                  |
| 3 | semi_auto_work_table_id  | standards.semi_auto_work_table_id | BIGINT  | NO        | ID en semi__automatics.id o vacio        |
| 4 | machine_id               | standards.machine_id           | BIGINT      | NO        | ID en machines.id o vacio                |
| 5 | units_per_hour           | standards.units_per_hour       | INT         | SI        | Entero >= 1                              |
| 6 | persons_1                | standards.persons_1            | INT         | NO        | Entero o vacio (NULL)                    |
| 7 | persons_2                | standards.persons_2            | INT         | NO        | Entero o vacio (NULL)                    |
| 8 | persons_3                | standards.persons_3            | INT         | NO        | Entero o vacio (NULL)                    |
| 9 | active                   | standards.active               | TINYINT     | NO        | 1 o 0 (default: 1)                       |
| 10| is_migrated              | standards.is_migrated          | TINYINT     | NO        | 0 para importacion masiva                |
| 11| description              | standards.description          | TEXT        | NO        | Texto libre                              |

**Regla de exclusividad de estacion**: Solo uno de los tres IDs de estacion debe estar poblado por fila. Los otros dos deben estar vacios (NULL).

**Ejemplo de filas**:

```
part_id,work_table_id,semi_auto_work_table_id,machine_id,units_per_hour,persons_1,persons_2,persons_3,active,is_migrated,description
1,3,,, 150,1,2,,1,0,Standard mesa manual
1,,2,,200,1,,,1,0,Standard semi-automatica
2,3,,,120,1,2,,1,0,Standard mesa manual
4,,, 5,500,1,,,1,0,Standard maquina
```

### 5.4 Archivo Alternativo: `import_standard_configurations.csv`

Si se prefiere usar la nueva estructura `standard_configurations` (con `is_migrated = 1`), primero se importan los standards "padre" y luego sus configuraciones:

**Paso 1**: Importar standards padre (sin configuraciones aun):

```
part_id,work_table_id,semi_auto_work_table_id,machine_id,units_per_hour,active,is_migrated,description
1,,,,1,1,1,Standard con configuraciones multiples
2,,,,1,1,1,Standard con configuraciones multiples
```

**Paso 2**: Obtener standard_ids generados:

```sql
SELECT s.id AS standard_id, p.item_number
FROM standards s
JOIN parts p ON s.part_id = p.id
WHERE s.is_migrated = 1
ORDER BY p.item_number;
```

**Paso 3**: Importar `import_standard_configurations.csv`:

| # | Nombre de Columna | Columna en BD                           | Tipo        | Requerido | Valores Validos              |
|---|-------------------|-----------------------------------------|-------------|-----------|------------------------------|
| 1 | standard_id       | standard_configurations.standard_id    | BIGINT      | SI        | ID existente en standards.id |
| 2 | workstation_type  | standard_configurations.workstation_type| ENUM        | SI        | 'manual','semi_automatic','machine' |
| 3 | workstation_id    | standard_configurations.workstation_id | BIGINT      | NO        | ID de estacion o vacio       |
| 4 | persons_required  | standard_configurations.persons_required| TINYINT    | SI        | 1, 2 o 3                     |
| 5 | units_per_hour    | standard_configurations.units_per_hour | INT         | SI        | Entero >= 1                  |
| 6 | is_default        | standard_configurations.is_default     | TINYINT     | NO        | 1 o 0 (default: 0)           |
| 7 | notes             | standard_configurations.notes          | TEXT        | NO        | Texto libre o vacio          |

**Ejemplo**:

```
standard_id,workstation_type,workstation_id,persons_required,units_per_hour,is_default,notes
1,manual,3,1,150,1,
1,manual,3,2,200,0,
1,semi_automatic,2,1,300,0,
2,manual,3,1,120,1,
```

---

## 6. Proceso de Importacion via phpMyAdmin

### 6.1 Preparacion del Archivo CSV desde Excel

**Pasos en Microsoft Excel o LibreOffice Calc**:

1. Abrir el archivo de importacion disenado.
2. Verificar que la fila 1 contiene SOLO los encabezados (nombres de columnas exactos).
3. Ir a **Archivo -> Guardar como**.
4. Seleccionar formato: **CSV UTF-8 (delimitado por comas)** - en Excel: "CSV (delimitado por comas)".
5. Confirmar los mensajes de advertencia.
6. Verificar el archivo resultante en un editor de texto (Notepad++ o VS Code) para asegurarse de que:
   - Los campos vacios aparecen como comas consecutivas: `,,`
   - Los textos con comas internas estan entre comillas: `"texto, con coma"`
   - No hay caracteres extranios ni BOM (Byte Order Mark)

**Configuracion recomendada**:
- Delimitador: `,` (coma)
- Encapsulador: `"` (comilla doble)
- Terminador de linea: `\n` (Unix) o `\r\n` (Windows - ambos funcionan en phpMyAdmin)
- Encoding: UTF-8

### 6.2 Orden Obligatorio de Importacion (por Foreign Keys)

El orden es CRITICO. Si se importa en orden incorrecto, MySQL rechazara las filas por violacion de foreign key.

```
ORDEN DE IMPORTACION:
1. parts              (no tiene dependencias externas)
2. prices             (depende de: parts)
3. price_tiers        (depende de: prices)
4. standards          (depende de: parts, tables, semi__automatics, machines)
5. standard_configurations (depende de: standards)
```

### 6.3 Pasos Detallados en phpMyAdmin

#### Paso 1: Acceder a phpMyAdmin

1. Abrir: `http://localhost/phpmyadmin`
2. Seleccionar la base de datos: `flexcon_tracker` (o el nombre configurado en `.env`).

#### Paso 2: Deshabilitar Temporalmente los Triggers (SOLO para importacion masiva)

El trigger `check_unique_active_price_before_insert` bloqueara la importacion si hay precios activos duplicados. Para importacion limpia (base de datos vacia o nueva):

```sql
-- Deshabilitar triggers temporalmente (solo en la sesion actual)
SET @TRIGGER_CHECKS = FALSE;
-- Nota: MySQL no tiene SET TRIGGER_CHECKS. Usar alternativa:

-- Opcion A: Desactivar trigger antes de importar prices
DROP TRIGGER IF EXISTS check_unique_active_price_before_insert;
DROP TRIGGER IF EXISTS check_unique_active_price_before_update;

-- [IMPORTAR prices aqui]

-- Recrear triggers despues (copiar el SQL de la migracion 2026_01_22)
DELIMITER //
CREATE TRIGGER check_unique_active_price_before_insert
BEFORE INSERT ON prices
FOR EACH ROW
BEGIN
    IF NEW.active = 1 THEN
        IF EXISTS (
            SELECT 1 FROM prices
            WHERE part_id = NEW.part_id
            AND workstation_type = NEW.workstation_type
            AND active = 1
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe un precio activo para este tipo de estacion de trabajo';
        END IF;
    END IF;
END//

CREATE TRIGGER check_unique_active_price_before_update
BEFORE UPDATE ON prices
FOR EACH ROW
BEGIN
    IF NEW.active = 1 THEN
        IF EXISTS (
            SELECT 1 FROM prices
            WHERE part_id = NEW.part_id
            AND workstation_type = NEW.workstation_type
            AND active = 1
            AND id != NEW.id
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe un precio activo para este tipo de estacion de trabajo';
        END IF;
    END IF;
END//
DELIMITER ;
```

**Alternativa sin deshabilitar triggers**: Importar un solo precio activo por parte por tipo. Si hay multiples precios para la misma parte+tipo, marcar los anteriores como `active=0` y solo el mas reciente como `active=1`.

#### Paso 3: Importar la Tabla `parts`

1. En phpMyAdmin, clic en la tabla `parts` en el panel izquierdo.
2. Clic en la pestana **Importar**.
3. Configurar:
   - **Archivo a importar**: Seleccionar `import_parts_YYYY-MM-DD.csv`
   - **Formato**: CSV
   - **Opciones CSV**:
     - Columnas separadas por: `,`
     - Columnas encerradas con: `"`
     - Columnas escapeadas con: `\`
     - Filas terminadas en: `AUTO`
     - Nombre de las columnas: `number,item_number,description,unit_of_measure,active,is_crimp,label_spec,notes`
     - Marcar **La primera linea del archivo contiene los nombres de columna de la tabla**
4. Clic en **Continuar** (o **Go**).
5. Verificar el mensaje de exito: "X filas insertadas."

**Verificacion post-importacion**:
```sql
SELECT COUNT(*) FROM parts;
SELECT number, item_number, active FROM parts ORDER BY item_number LIMIT 20;
```

#### Paso 4: Importar la Tabla `prices`

1. En phpMyAdmin, seleccionar la tabla `prices`.
2. Clic en **Importar**.
3. Configurar igual que el paso anterior.
4. Columnas: `part_id,sample_price,workstation_type,effective_date,active,comments`
5. Marcar la primera linea como encabezado.
6. Continuar.

**Verificacion**:
```sql
SELECT p.id, pt.item_number, p.workstation_type, p.sample_price, p.active, p.effective_date
FROM prices p
JOIN parts pt ON p.part_id = pt.id
ORDER BY pt.item_number, p.workstation_type
LIMIT 20;
```

#### Paso 5: Obtener price_ids y Preparar el CSV de Tiers

```sql
-- Ejecutar esta consulta y exportar resultado a CSV
SELECT
    p.id AS price_id,
    pt.item_number,
    p.workstation_type,
    p.effective_date,
    p.sample_price
FROM prices p
JOIN parts pt ON p.part_id = pt.id
ORDER BY pt.item_number, p.workstation_type;
```

Con este resultado, completar el archivo `import_price_tiers.csv` usando los `price_id` correctos.

#### Paso 6: Importar la Tabla `price_tiers`

1. Seleccionar tabla `price_tiers`.
2. Importar `import_price_tiers.csv`.
3. Columnas: `price_id,min_quantity,max_quantity,tier_price`

**Verificacion**:
```sql
SELECT
    pt.tier_price,
    pt.min_quantity,
    pt.max_quantity,
    p.workstation_type,
    pa.item_number
FROM price_tiers pt
JOIN prices p ON pt.price_id = p.id
JOIN parts pa ON p.part_id = pa.id
ORDER BY pa.item_number, p.workstation_type, pt.min_quantity
LIMIT 30;
```

#### Paso 7: Importar la Tabla `standards`

1. Seleccionar tabla `standards`.
2. Importar `import_standards.csv`.
3. Columnas: `part_id,work_table_id,semi_auto_work_table_id,machine_id,units_per_hour,persons_1,persons_2,persons_3,active,is_migrated,description`

#### Paso 8: Importar `standard_configurations` (si aplica)

Solo si se uso la estrategia de migracion nueva con `is_migrated = 1`.

---

## 7. Analisis de Impactos y Riesgos

### 7.1 Riesgo CRITICO: Duplicados en Numeros de Parte

**Tabla afectada**: `parts`
**Nivel de riesgo**: ALTO

**Descripcion**: Las columnas `number` e `item_number` tienen indices UNIQUE. Si el archivo CSV contiene un valor que ya existe en la BD (ya sea en un registro activo o en uno con soft-delete), MySQL rechazara toda la importacion con error:

```
ERROR 1062 (23000): Duplicate entry '189-10004' for key 'parts_item_number_unique'
```

**Escenario de soft-delete**: Una parte borrada con SoftDelete sigue ocupando el espacio en el indice UNIQUE. Si se intenta insertar una parte con el mismo `item_number` que una ya borrada, el error ocurrira de igual forma.

**Mitigacion**:
```sql
-- Pre-verificacion: buscar conflictos antes de importar
SELECT
    p.item_number,
    p.number,
    p.deleted_at,
    CASE WHEN p.deleted_at IS NOT NULL THEN 'SOFT DELETED' ELSE 'ACTIVE' END AS estado
FROM parts p
WHERE p.item_number IN (
    -- Lista de item_numbers del archivo CSV
    '189-10004', '189-10005', '189-10006'
);

-- Si hay soft-deleted que conflictuan, decidir:
-- Opcion 1: Restaurar el registro existente y actualizar
UPDATE parts SET deleted_at = NULL WHERE item_number = '189-10004' AND deleted_at IS NOT NULL;

-- Opcion 2: Eliminar permanentemente el registro soft-deleted
DELETE FROM parts WHERE item_number = '189-10004' AND deleted_at IS NOT NULL;
```

---

### 7.2 Riesgo CRITICO: Violacion del Trigger de Precios Unicos

**Tabla afectada**: `prices`
**Nivel de riesgo**: ALTO

**Descripcion**: El trigger `check_unique_active_price_before_insert` impedira insertar un precio con `active=1` si ya existe otro precio activo para la misma combinacion `(part_id, workstation_type)`.

**Error que se produce**:
```
ERROR 1644 (45000): Ya existe un precio activo para este tipo de estacion de trabajo
```

**Escenarios de riesgo**:
1. La parte ya tiene un precio activo tipo `table` y se intenta importar otro.
2. Se importan dos filas del CSV para la misma parte y mismo tipo con `active=1`.
3. El CSV tiene multiples variantes de precio para el mismo item_number (como en el Excel original donde `189-10004` aparece varias veces).

**Mitigacion**:
```sql
-- Pre-verificacion de precios activos existentes
SELECT p.part_id, pa.item_number, p.workstation_type, p.active
FROM prices p
JOIN parts pa ON p.part_id = pa.id
WHERE p.active = 1
ORDER BY pa.item_number, p.workstation_type;

-- Desactivar precios existentes antes de importar nuevos precios activos
UPDATE prices SET active = 0
WHERE part_id IN (SELECT id FROM parts WHERE item_number IN ('189-10004','189-10005'))
  AND workstation_type = 'table'
  AND active = 1;
```

---

### 7.3 Riesgo ALTO: Ruptura de Foreign Keys en Standards

**Tablas afectadas**: `standards`, `standard_configurations`
**Nivel de riesgo**: ALTO

**Descripcion**: Si el `part_id`, `work_table_id`, `semi_auto_work_table_id` o `machine_id` en el CSV no corresponden a IDs existentes en sus respectivas tablas, MySQL rechazara la insercion.

**Error tipico**:
```
ERROR 1452 (23000): Cannot add or update a child row: a foreign key constraint fails
```

**Mitigacion**:
```sql
-- Verificar que todos los part_id existen
SELECT id FROM parts WHERE id IN (1, 2, 3, 4, 5) AND deleted_at IS NULL;

-- Verificar que todos los work_table_id existen
SELECT id, number FROM tables WHERE id IN (1, 2, 3) AND deleted_at IS NULL;

-- Verificar que todos los machine_id existen
SELECT id, name FROM machines WHERE id IN (1, 2) AND deleted_at IS NULL;
```

---

### 7.4 Riesgo MEDIO: Impacto en Precios Existentes en Produccion

**Tablas afectadas**: `prices`, `price_tiers`
**Nivel de riesgo**: MEDIO

**Descripcion**: Si la aplicacion ya esta en produccion y se importan precios nuevos para partes existentes, los precios viejos que queden activos SEGUIRAN siendo usados por la logica de negocio (`Part::activePrice()` busca el mas reciente por `effective_date`).

**Logica de `activePrice()`**:
```php
// En Part.php
return $this->prices()
    ->where('active', true)
    ->where('effective_date', '<=', now())
    ->orderBy('effective_date', 'desc')
    ->first();
```

**Riesgo especifico**: Si se importa un precio con `effective_date` anterior al precio existente, el precio nuevo NUNCA sera seleccionado aunque este activo.

**Mitigacion**:
- Siempre usar `effective_date` igual o mayor al precio actual.
- O desactivar (`active=0`) los precios viejos antes de importar los nuevos.

---

### 7.5 Riesgo MEDIO: Impacto en Standards Existentes

**Tabla afectada**: `standards`
**Nivel de riesgo**: MEDIO

**Descripcion**: Si ya existen standards activos para una parte y se importan nuevos, ambos coexistiran. El calculo de capacidad en Work Orders usara el standard activo mas reciente, pero si hay dos standards activos para la misma parte, el comportamiento depende de la logica de consulta.

**Verificacion de standards duplicados**:
```sql
SELECT part_id, COUNT(*) AS total_activos
FROM standards
WHERE active = 1 AND deleted_at IS NULL
GROUP BY part_id
HAVING total_activos > 1;
```

---

### 7.6 Riesgo BAJO: Datos Historicos en Work Orders

**Tablas afectadas**: `work_orders`, `productions`
**Nivel de riesgo**: BAJO

**Descripcion**: Las Work Orders en produccion o completadas tienen referencias a `part_id` y `price_id`. La importacion masiva de nuevas partes no afecta a los WO existentes, ya que estos referencian registros especificos por ID.

**Sin embargo**: Si se eliminan o modifican partes que tienen WO asociados, se producira inconsistencia en reportes historicos.

**Recomendacion**: NUNCA eliminar partes con WO asociados. Solo usar soft-delete o marcar como `active=0`.

---

### 7.7 Riesgo BAJO: Constraint UNIQUE en `price_tiers`

**Tabla afectada**: `price_tiers`
**Nivel de riesgo**: BAJO

**Descripcion**: El indice `price_tier_unique` sobre `(price_id, min_quantity, max_quantity)` impedira duplicar tiers identicos para el mismo precio.

**Mitigacion**: Asegurarse de que cada `price_id` en el CSV de tiers no tenga filas duplicadas con el mismo `min_quantity` y `max_quantity`.

---

### 7.8 Riesgo BAJO: Constraint UNIQUE en `standard_configurations`

**Tabla afectada**: `standard_configurations`
**Nivel de riesgo**: BAJO

**Descripcion**: El indice `unique_standard_config` sobre `(standard_id, workstation_type, persons_required)` impedira duplicar configuraciones identicas.

---

### 7.9 Resumen de Riesgos

| Riesgo                                    | Nivel | Tabla Afectada     | Probabilidad |
|-------------------------------------------|-------|---------------------|--------------|
| Duplicados en number/item_number de partes| ALTO  | parts               | Alta         |
| Violacion trigger precios unicos           | ALTO  | prices              | Alta         |
| Ruptura de FK en standards                | ALTO  | standards           | Media        |
| Impacto en precios en produccion           | MEDIO | prices, price_tiers | Media        |
| Standards duplicados activos               | MEDIO | standards           | Baja         |
| FK en price_tiers (price_id invalido)     | MEDIO | price_tiers         | Baja         |
| Datos historicos en WOs                   | BAJO  | work_orders         | Baja         |
| Duplicados en price_tiers                 | BAJO  | price_tiers         | Baja         |
| Duplicados en standard_configurations     | BAJO  | standard_configurations| Baja      |

---

## 8. Recomendaciones Pre-Importacion

### 8.1 Backup Obligatorio

**ANTES de cualquier importacion masiva**:

```sql
-- Opcion 1: Backup via phpMyAdmin
-- Ir a: phpMyAdmin -> [base_de_datos] -> Exportar -> Quick -> Go
-- Guardar el archivo .sql en lugar seguro

-- Opcion 2: Backup via linea de comandos (Windows XAMPP)
-- En cmd.exe o PowerShell como Administrador:
C:\xampp\mysql\bin\mysqldump.exe -u root -p flexcon_tracker > "C:\backup\flexcon_tracker_2026-03-25.sql"

-- Opcion 3: Backup selectivo (solo tablas relevantes)
C:\xampp\mysql\bin\mysqldump.exe -u root -p flexcon_tracker parts prices price_tiers standards standard_configurations > "C:\backup\flexcon_catalog_2026-03-25.sql"
```

### 8.2 Validaciones Previas en la BD

Ejecutar ANTES de importar:

```sql
-- 1. Estado actual de la base de datos
SELECT
    'parts' AS tabla, COUNT(*) AS total, SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS activos
FROM parts
UNION ALL
SELECT 'prices', COUNT(*), SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) FROM prices
UNION ALL
SELECT 'price_tiers', COUNT(*), COUNT(*) FROM price_tiers
UNION ALL
SELECT 'standards', COUNT(*), SUM(CASE WHEN active = 1 AND deleted_at IS NULL THEN 1 ELSE 0 END) FROM standards
UNION ALL
SELECT 'standard_configurations', COUNT(*), COUNT(*) FROM standard_configurations;

-- 2. Verificar integridad referencial actual
SELECT COUNT(*) AS prices_sin_part FROM prices WHERE part_id NOT IN (SELECT id FROM parts);
SELECT COUNT(*) AS tiers_sin_price FROM price_tiers WHERE price_id NOT IN (SELECT id FROM prices);
SELECT COUNT(*) AS standards_sin_part FROM standards WHERE part_id NOT IN (SELECT id FROM parts);

-- 3. Verificar que los triggers existen
SHOW TRIGGERS LIKE 'prices';
-- Debe mostrar: check_unique_active_price_before_insert y check_unique_active_price_before_update
```

### 8.3 Validacion del Archivo CSV Antes de Importar

```sql
-- Script para validar el CSV de parts antes de importar
-- Crear tabla temporal de staging

CREATE TEMPORARY TABLE staging_parts (
    number VARCHAR(255),
    item_number VARCHAR(255),
    description TEXT,
    unit_of_measure VARCHAR(255),
    active TINYINT DEFAULT 1,
    is_crimp TINYINT DEFAULT 1,
    label_spec VARCHAR(150),
    notes VARCHAR(255)
);

-- Importar el CSV a la tabla temporal en lugar de la tabla real
-- [Usar phpMyAdmin -> Importar -> staging_parts]

-- Luego verificar:
-- a) Duplicados internos en el CSV
SELECT item_number, COUNT(*) AS repeticiones
FROM staging_parts
GROUP BY item_number
HAVING repeticiones > 1;

-- b) Conflictos con datos existentes en BD
SELECT s.item_number, p.id, p.deleted_at
FROM staging_parts s
JOIN parts p ON s.item_number = p.item_number;

-- c) Limpiar tabla temporal despues de validar
DROP TEMPORARY TABLE staging_parts;
```

### 8.4 Orden de Operaciones Recomendado

```
FASE 1: PREPARACION (1-2 dias antes)
  1.1 Generar backup completo
  1.2 Ejecutar validaciones de estado actual
  1.3 Preparar y validar el CSV de parts (en staging)
  1.4 Preparar y validar el CSV de prices (en staging)
  1.5 Calcular los price_ids esperados

FASE 2: IMPORTACION (ventana de mantenimiento recomendada)
  2.1 Notificar a usuarios que el sistema estara en mantenimiento
  2.2 Hacer backup final justo antes de empezar
  2.3 Importar parts
  2.4 Verificar importacion de parts
  2.5 Importar prices (con triggers deshabilitados si es necesario)
  2.6 Verificar importacion de prices
  2.7 Obtener price_ids generados
  2.8 Preparar y validar CSV de price_tiers
  2.9 Importar price_tiers
  2.10 Verificar importacion de tiers
  2.11 Importar standards
  2.12 Verificar importacion de standards
  2.13 Restaurar triggers si fueron eliminados

FASE 3: VERIFICACION POST-IMPORTACION
  3.1 Ejecutar consultas de integridad referencial
  3.2 Probar la aplicacion web (buscar partes, ver precios)
  3.3 Verificar calculo de capacidad con los nuevos standards
  3.4 Confirmar que los triggers funcionan correctamente
```

### 8.5 Configuracion de phpMyAdmin para Importaciones Grandes

El archivo `php.ini` de XAMPP puede necesitar ajuste para archivos grandes:

```ini
; En C:\xampp\php\php.ini
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```

Reiniciar Apache desde el Panel de XAMPP despues de cambiar `php.ini`.

---

## 9. Plan de Rollback

### 9.1 Rollback Completo (Restaurar Backup)

Si algo sale gravemente mal, restaurar el backup completo:

```sql
-- Via linea de comandos (XAMPP, Windows):
C:\xampp\mysql\bin\mysql.exe -u root -p flexcon_tracker < "C:\backup\flexcon_tracker_2026-03-25.sql"
```

**Via phpMyAdmin**:
1. Ir a phpMyAdmin -> seleccionar la BD.
2. Pestaña **SQL** o **Importar**.
3. Cargar el archivo `.sql` del backup.
4. Ejecutar.

**Tiempo estimado de restauracion**: Depende del tamano de la BD. Para BD con hasta 10,000 registros: menos de 2 minutos.

### 9.2 Rollback Selectivo (Solo Partes Importadas)

Si la importacion de parts fue exitosa pero se desea revertir SIN restaurar el backup completo:

```sql
-- OPCION A: Soft-delete de las partes importadas
-- (las partes quedan en BD pero invisibles para la aplicacion)
UPDATE parts
SET deleted_at = NOW()
WHERE item_number IN (
    '189-10004', '189-10005', '189-10006'
    -- [lista completa de item_numbers importados]
)
AND deleted_at IS NULL;

-- OPCION B: Eliminar permanentemente las partes importadas
-- Solo si no tienen precios, standards ni WOs asociados
DELETE FROM parts
WHERE item_number IN (
    '189-10004', '189-10005', '189-10006'
)
AND id NOT IN (SELECT DISTINCT part_id FROM prices)
AND id NOT IN (SELECT DISTINCT part_id FROM standards);
```

### 9.3 Rollback de Precios Importados

```sql
-- Paso 1: Obtener los price_ids de los precios a revertir
SELECT p.id, pa.item_number, p.workstation_type
FROM prices p
JOIN parts pa ON p.part_id = pa.id
WHERE pa.item_number IN ('189-10004', '189-10005')
  AND p.created_at >= '2026-03-25 00:00:00'; -- fecha de importacion

-- Paso 2: Eliminar los tiers de esos precios
DELETE FROM price_tiers
WHERE price_id IN (
    SELECT p.id FROM prices p
    JOIN parts pa ON p.part_id = pa.id
    WHERE pa.item_number IN ('189-10004', '189-10005')
      AND p.created_at >= '2026-03-25 00:00:00'
);

-- Paso 3: Eliminar los precios
DELETE FROM prices
WHERE id IN (
    SELECT p.id FROM (SELECT * FROM prices) p
    JOIN parts pa ON p.part_id = pa.id
    WHERE pa.item_number IN ('189-10004', '189-10005')
      AND p.created_at >= '2026-03-25 00:00:00'
);

-- Nota: MySQL no permite hacer DELETE y SELECT de la misma tabla directamente.
-- Usar subconsulta con alias como se muestra arriba.
```

### 9.4 Rollback de Standards Importados

```sql
-- Soft-delete de standards importados (recomendado sobre DELETE)
UPDATE standards
SET deleted_at = NOW()
WHERE part_id IN (
    SELECT id FROM parts WHERE item_number IN ('189-10004', '189-10005')
)
AND created_at >= '2026-03-25 00:00:00';

-- O eliminar permanentemente si aun no tienen WOs asociados
DELETE FROM standards
WHERE part_id IN (
    SELECT id FROM parts WHERE item_number IN ('189-10004', '189-10005')
)
AND created_at >= '2026-03-25 00:00:00';
```

### 9.5 Restaurar Triggers Despues de Rollback

Si los triggers fueron eliminados durante la importacion, deben recrearse:

```sql
DELIMITER //

DROP TRIGGER IF EXISTS check_unique_active_price_before_insert//
CREATE TRIGGER check_unique_active_price_before_insert
BEFORE INSERT ON prices
FOR EACH ROW
BEGIN
    IF NEW.active = 1 THEN
        IF EXISTS (
            SELECT 1 FROM prices
            WHERE part_id = NEW.part_id
            AND workstation_type = NEW.workstation_type
            AND active = 1
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe un precio activo para este tipo de estacion de trabajo';
        END IF;
    END IF;
END//

DROP TRIGGER IF EXISTS check_unique_active_price_before_update//
CREATE TRIGGER check_unique_active_price_before_update
BEFORE UPDATE ON prices
FOR EACH ROW
BEGIN
    IF NEW.active = 1 THEN
        IF EXISTS (
            SELECT 1 FROM prices
            WHERE part_id = NEW.part_id
            AND workstation_type = NEW.workstation_type
            AND active = 1
            AND id != NEW.id
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe un precio activo para este tipo de estacion de trabajo';
        END IF;
    END IF;
END//

DELIMITER ;

-- Verificar que los triggers quedaron creados correctamente
SHOW TRIGGERS LIKE 'prices';
```

### 9.6 Script de Verificacion Final Post-Rollback

```sql
-- Verificar que el estado de la BD es correcto despues del rollback
SELECT
    'parts activas' AS concepto,
    COUNT(*) AS cantidad
FROM parts WHERE deleted_at IS NULL
UNION ALL
SELECT 'precios activos', COUNT(*) FROM prices WHERE active = 1
UNION ALL
SELECT 'tiers de precio', COUNT(*) FROM price_tiers
UNION ALL
SELECT 'standards activos', COUNT(*) FROM standards WHERE active = 1 AND deleted_at IS NULL
UNION ALL
SELECT 'standard configs', COUNT(*) FROM standard_configurations;

-- Verificar integridad referencial post-rollback
SELECT COUNT(*) AS tiers_huerfanos FROM price_tiers
WHERE price_id NOT IN (SELECT id FROM prices);

SELECT COUNT(*) AS standards_huerfanos FROM standards
WHERE part_id NOT IN (SELECT id FROM parts) AND deleted_at IS NULL;
```

---

## Apendice A: Scripts SQL de Diagnostico Rapido

### A.1 Estado Actual del Catalogo

```sql
-- Vista rapida del estado del catalogo
SELECT
    p.item_number,
    p.number,
    p.description,
    p.active,
    COUNT(DISTINCT pr.id) AS total_precios,
    COUNT(DISTINCT CASE WHEN pr.active = 1 THEN pr.id END) AS precios_activos,
    COUNT(DISTINCT s.id) AS total_standards,
    COUNT(DISTINCT CASE WHEN s.active = 1 THEN s.id END) AS standards_activos
FROM parts p
LEFT JOIN prices pr ON p.id = pr.part_id
LEFT JOIN standards s ON p.id = s.part_id AND s.deleted_at IS NULL
WHERE p.deleted_at IS NULL
GROUP BY p.id, p.item_number, p.number, p.description, p.active
ORDER BY p.item_number
LIMIT 50;
```

### A.2 Partes Sin Precio

```sql
SELECT p.item_number, p.number, p.description
FROM parts p
WHERE p.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM prices pr
      WHERE pr.part_id = p.id AND pr.active = 1
  )
ORDER BY p.item_number;
```

### A.3 Partes Sin Standard

```sql
SELECT p.item_number, p.number, p.description
FROM parts p
WHERE p.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM standards s
      WHERE s.part_id = p.id AND s.active = 1 AND s.deleted_at IS NULL
  )
ORDER BY p.item_number;
```

### A.4 Validar Que el Trigger Funciona

```sql
-- Este INSERT debe FALLAR si ya existe un precio activo para part_id=1, workstation_type='table'
-- Usarlo como test de verificacion del trigger
-- IMPORTANTE: NO ejecutar si no es para prueba, puede generar datos de basura
-- INSERT INTO prices (part_id, sample_price, workstation_type, effective_date, active)
-- VALUES (1, 0.1000, 'table', '2026-01-01', 1);
-- El error esperado: ERROR 1644 (45000): Ya existe un precio activo...

-- En su lugar, verificar el trigger con una consulta de lectura:
SHOW TRIGGERS WHERE `Table` = 'prices';
```

---

## Apendice B: Plantillas de Archivos CSV Listos para Usar

### B.1 Plantilla: import_parts_template.csv

```csv
number,item_number,description,unit_of_measure,active,is_crimp,label_spec,notes
FLX-XXXXX,189-XXXXX,Descripcion del producto,EA,1,1,,
```

### B.2 Plantilla: import_prices_template.csv

```csv
part_id,sample_price,workstation_type,effective_date,active,comments
0,0.0000,table,2024-01-01,1,
```

**Recordatorio de conversion de precio**:
- Precio Excel Col C = 52.71 (por 100 piezas)
- sample_price en BD = 52.71 / 100 = **0.5271**

### B.3 Plantilla: import_price_tiers_table_template.csv

```csv
price_id,min_quantity,max_quantity,tier_price
0,1,999,0.0000
0,1000,10999,0.0000
0,11000,99999,0.0000
0,100000,,0.0000
```

### B.4 Plantilla: import_price_tiers_machine_template.csv

```csv
price_id,min_quantity,max_quantity,tier_price
0,1,9999,0.0000
0,10000,49999,0.0000
0,50000,,0.0000
```

### B.5 Plantilla: import_price_tiers_semi_auto_template.csv

```csv
price_id,min_quantity,max_quantity,tier_price
0,2000,10000,0.0000
0,11000,,0.0000
```

### B.6 Plantilla: import_standards_template.csv

```csv
part_id,work_table_id,semi_auto_work_table_id,machine_id,units_per_hour,persons_1,persons_2,persons_3,active,is_migrated,description
0,,,, 100,1,2,,1,0,
```

---

## Apendice C: Checklist de Importacion

### Pre-Importacion

- [ ] Backup completo realizado y verificado
- [ ] Archivos CSV validados en tabla staging
- [ ] No hay duplicados internos en los CSVs
- [ ] No hay conflictos con datos existentes en BD
- [ ] Los IDs de foreign keys verificados (part_id, work_table_id, etc.)
- [ ] php.ini ajustado para archivos grandes (si es necesario)
- [ ] Usuarios notificados del mantenimiento

### Durante la Importacion

- [ ] Triggers verificados (mostrar/documentar estado)
- [ ] parts importadas correctamente (N filas)
- [ ] prices importadas correctamente (N filas)
- [ ] price_tiers importadas correctamente (N filas)
- [ ] standards importadas correctamente (N filas)
- [ ] standard_configurations importadas (si aplica)
- [ ] Triggers restaurados (si fueron eliminados)

### Post-Importacion

- [ ] Consultas de integridad referencial ejecutadas (0 errores)
- [ ] Aplicacion web probada (busqueda de partes)
- [ ] Precios visibles correctamente en la UI
- [ ] Standards correctos en calculo de capacidad
- [ ] Backup post-importacion generado

---

*Documento generado el 2026-03-25 por Agent Architect para el proyecto flexcon-tracker.*
*Basado en el analisis de migraciones, modelos y el archivo Excel del cliente FDI-81 Rev 4.*
