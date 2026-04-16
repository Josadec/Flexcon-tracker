# Plantillas de Importacion Masiva - Flexcon Tracker

**Fecha de creacion**: 2026-03-25
**Version**: 1.0
**Referencia completa**: `../00_importacion_masiva_excel_2026-03-25.md`

---

## Archivos incluidos

| Archivo | Tabla destino | Dependencias |
|---------|---------------|--------------|
| `01_parts_template.csv` | `parts` | Ninguna - importar primero |
| `02_prices_template.csv` | `prices` | Requiere: `parts` |
| `03_price_tiers_template.csv` | `price_tiers` | Requiere: `prices` |
| `04_standards_template.csv` | `standards` | Requiere: `parts`, `tables`, `semi__automatics`, `machines` |
| `05_standard_configurations_template.csv` | `standard_configurations` | Requiere: `standards` |

---

## Orden obligatorio de importacion

El orden es CRITICO. MySQL rechazara registros si se violan las restricciones de foreign key.

```
PASO 1 --> 01_parts_template.csv           (tabla: parts)
PASO 2 --> 02_prices_template.csv          (tabla: prices)
PASO 3 --> 03_price_tiers_template.csv     (tabla: price_tiers)
PASO 4 --> 04_standards_template.csv       (tabla: standards)
PASO 5 --> 05_standard_configurations_template.csv  (tabla: standard_configurations)
```

**Nota entre pasos**: Los archivos `03_price_tiers_template.csv` y `05_standard_configurations_template.csv` usan IDs autogenerados por MySQL. Antes de preparar esos archivos con datos reales, ejecutar las consultas de recuperacion de IDs descritas en cada plantilla (fila de instrucciones, columna `price_id` / `standard_id`).

---

## Como abrir en Excel (Windows)

Los archivos CSV usan **coma como separador** y codificacion **UTF-8 con BOM**. Para abrirlos correctamente en Microsoft Excel en Windows:

### Opcion A - Doble clic directo (si Excel esta configurado para UTF-8)
1. Hacer doble clic en el archivo `.csv`.
2. Si las columnas no se separan correctamente, usar la Opcion B.

### Opcion B - Importar desde Excel (recomendado)
1. Abrir Excel en blanco.
2. Ir a la pestana **Datos**.
3. Seleccionar **Obtener datos > Desde archivo > Desde texto/CSV**.
4. Seleccionar el archivo `.csv`.
5. En la ventana de configuracion:
   - **Delimitador**: Coma
   - **Origen del archivo**: UTF-8 (65001)
6. Hacer clic en **Cargar**.

### Opcion C - Cambiar extension temporalmente
1. Renombrar el archivo de `.csv` a `.txt`.
2. Abrir desde Excel (se abrira el Asistente de importacion).
3. Paso 1: seleccionar **Delimitados** y origen **65001: Unicode (UTF-8)**.
4. Paso 2: seleccionar **Coma** como delimitador.
5. Paso 3: definir el tipo de cada columna (texto para IDs y codigos, numero para precios).

---

## Estructura de cada archivo CSV

Cada archivo tiene exactamente 3 secciones:

- **Fila 1**: Nombres exactos de columnas tal como estan en la base de datos.
- **Fila 2**: Descripcion de cada columna (tipo de dato, si es obligatorio, valores permitidos). Esta fila DEBE ELIMINARSE antes de importar a phpMyAdmin.
- **Filas 3 en adelante**: Datos de ejemplo reales tomados del Excel del cliente.

---

## Advertencias criticas antes de importar

### 1. Eliminar la fila de descripciones (fila 2)

La fila 2 de cada archivo es documentacion para el usuario, no datos reales. Antes de importar a phpMyAdmin, **eliminar la fila 2 completamente**. phpMyAdmin importara todas las filas debajo del encabezado como registros de datos.

### 2. Conversion de precios (division entre 100)

Los precios en el Excel del cliente (FDI-81) estan expresados como "Cost Per 100 Pcs." (costo por 100 piezas). La base de datos guarda el precio unitario.

```
Precio en Excel:  52.71  (por 100 piezas)
Precio en BD:     0.5271 (por 1 pieza = 52.71 / 100)

Precio en Excel:  25.00  (por 100 piezas)
Precio en BD:     0.2500 (por 1 pieza = 25.00 / 100)
```

Los ejemplos en los archivos CSV ya muestran los valores convertidos (divididos entre 100).

### 3. Trigger MySQL en tabla prices (restriccion critica)

La tabla `prices` tiene dos TRIGGERS activos en MySQL:
- `check_unique_active_price_before_insert`
- `check_unique_active_price_before_update`

Estos triggers **bloquean la insercion** si ya existe un precio con `active = 1` para la misma combinacion de `(part_id, workstation_type)`. Si se intenta insertar un segundo precio activo para la misma parte y tipo de estacion, MySQL lanzara el error:

```
SQLSTATE 45000: Ya existe un precio activo para este tipo de estacion de trabajo
```

**Solucion**: Antes de importar prices, verificar que no existan precios activos para las mismas partes. Si ya existen, desactivarlos primero (`UPDATE prices SET active = 0 WHERE part_id IN (...)`).

### 4. Soft Deletes en parts y standards

Las tablas `parts` y `standards` usan Soft Delete (columna `deleted_at`). Un registro "borrado" permanece en la base de datos con `deleted_at` poblado. Los indices UNIQUE de `parts.number` y `parts.item_number` aplican incluso a registros borrados.

Si se intenta insertar una parte con `number` o `item_number` que ya existe (aunque este borrada), MySQL lanzara error de constraint UNIQUE.

**Solucion pre-importacion**: Verificar con esta consulta:

```sql
-- Detectar conflictos con partes (incluyendo soft-deleted)
SELECT number, item_number, deleted_at
FROM parts
WHERE number IN ('FLX-10004', 'FLX-10005')
   OR item_number IN ('189-10004', '189-10005');
```

### 5. Campo number en parts (numero interno Flexcon)

El campo `parts.number` es el numero interno de Flexcon y NO aparece en el Excel del cliente. Debe ser asignado manualmente. Los ejemplos en la plantilla usan el formato `FLX-XXXXX` (prefijo FLX mas el numero de 5 digitos del cliente), pero el formato real debe ser definido por el equipo de Flexcon.

### 6. IDs de estaciones de trabajo para standards

La tabla `standards` requiere los IDs numericos de las mesas, semi-automaticas y maquinas existentes en la BD. Obtenerlos antes de preparar el archivo:

```sql
-- Mesas manuales
SELECT id, number FROM tables WHERE deleted_at IS NULL ORDER BY number;

-- Mesas semi-automaticas (nota: doble guion bajo en el nombre de tabla)
SELECT id, number FROM semi__automatics WHERE deleted_at IS NULL ORDER BY number;

-- Maquinas
SELECT id, name, brand, model FROM machines WHERE deleted_at IS NULL ORDER BY name;
```

### 7. Valores "N/A" en precios de maquina

En el Excel del cliente (hoja "non lead machine"), algunos precios muestran "N/A" en columnas de tiers que no aplican para ese tipo de estacion. Estos valores deben ignorarse; no se deben importar como tiers. Solo importar los tiers con valores numericos validos.

### 8. Columna workstation_type: valores exactos requeridos

La columna `workstation_type` es un ENUM en MySQL. Los valores deben ser exactamente:

| Tabla | Valores permitidos |
|-------|--------------------|
| `prices` | `table` / `machine` / `semi_automatic` |
| `standard_configurations` | `manual` / `semi_automatic` / `machine` |

Notar que `prices` usa `table` pero `standard_configurations` usa `manual` para el mismo tipo de estacion.

---

## Proceso de importacion en phpMyAdmin

1. Abrir phpMyAdmin: `http://localhost/phpmyadmin`
2. Seleccionar la base de datos `flexcon_tracker`.
3. En el panel izquierdo, hacer clic en el nombre de la tabla destino.
4. En el menu superior, seleccionar la pestana **Importar**.
5. Configuracion de importacion:
   - **Archivo a importar**: seleccionar el CSV preparado
   - **Codificacion del archivo**: `utf-8`
   - **Formato**: `CSV`
   - **Separador de columnas**: `,`
   - **Separador de filas**: `AUTO`
   - **Nombre de la columna**: activar la casilla "La primera linea del archivo contiene los nombres de las columnas de la tabla"
   - **Reemplazar datos de la tabla**: NO activar (para evitar borrar datos existentes)
6. Hacer clic en **Importar**.
7. Verificar el mensaje de resultado: debe mostrar "X filas insertadas".

---

## Consultas de verificacion post-importacion

Ejecutar estas consultas en phpMyAdmin (pestana SQL) para verificar que la importacion fue exitosa:

```sql
-- Verificar parts importadas
SELECT id, number, item_number, description, active, is_crimp
FROM parts
WHERE deleted_at IS NULL
ORDER BY item_number
LIMIT 20;

-- Verificar prices importadas
SELECT p.id, pt.item_number, p.workstation_type, p.sample_price, p.active, p.effective_date
FROM prices p
JOIN parts pt ON p.part_id = pt.id
ORDER BY pt.item_number, p.workstation_type
LIMIT 20;

-- Verificar price_tiers importados
SELECT pt.id, pr.id AS price_id, pa.item_number, pr.workstation_type,
       pt.min_quantity, pt.max_quantity, pt.tier_price
FROM price_tiers pt
JOIN prices pr ON pt.price_id = pr.id
JOIN parts pa ON pr.part_id = pa.id
ORDER BY pa.item_number, pr.workstation_type, pt.min_quantity
LIMIT 30;

-- Verificar conteos totales
SELECT
  (SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL) AS total_parts,
  (SELECT COUNT(*) FROM prices WHERE active = 1) AS total_prices_activos,
  (SELECT COUNT(*) FROM price_tiers) AS total_tiers,
  (SELECT COUNT(*) FROM standards WHERE deleted_at IS NULL) AS total_standards;
```

---

## Plan de rollback (deshacer importacion)

Si la importacion produce errores o datos incorrectos, eliminar los registros insertados:

```sql
-- ADVERTENCIA: Ejecutar solo si es necesario deshacer la importacion
-- Reemplazar los rangos de IDs con los reales de la importacion

-- 1. Eliminar price_tiers (primero por FK)
DELETE FROM price_tiers WHERE price_id IN (SELECT id FROM prices WHERE part_id IN (
  SELECT id FROM parts WHERE number LIKE 'FLX-%'
));

-- 2. Eliminar prices
DELETE FROM prices WHERE part_id IN (
  SELECT id FROM parts WHERE number LIKE 'FLX-%'
);

-- 3. Eliminar standard_configurations
DELETE FROM standard_configurations WHERE standard_id IN (
  SELECT id FROM standards WHERE part_id IN (
    SELECT id FROM parts WHERE number LIKE 'FLX-%'
  )
);

-- 4. Eliminar standards
DELETE FROM standards WHERE part_id IN (
  SELECT id FROM parts WHERE number LIKE 'FLX-%'
);

-- 5. Eliminar parts (al final)
DELETE FROM parts WHERE number LIKE 'FLX-%';
```

---

## Referencia

Reporte completo de analisis tecnico:
`C:/xampp/htdocs/flexcon-tracker/Diagramas_flujo/DB/00_importacion_masiva_excel_2026-03-25.md`

Excel fuente del cliente:
`C:/xampp/htdocs/flexcon-tracker/Diagramas_flujo/DB/diversos_exceles/FDI-81  Price List Flexcon (+20_ - 2024) Rev 4 - (05-20-2025).xlsx`
