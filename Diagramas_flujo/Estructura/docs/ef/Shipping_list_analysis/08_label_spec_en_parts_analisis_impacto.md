# Analisis Tecnico: Agregar `label_spec` a la Tabla `parts` — Impacto Completo

**Fecha:** 2026-03-11
**Elaborado por:** Arquitecto de Software - FlexCon Tracker
**Version:** 1.0
**Proposito:** Documentar el impacto arquitectural completo de agregar el campo `label_spec` a la tabla `parts`, como evolución de la decision D-06-02 registrada en `06_impacto_respuestas_pendientes_y_ajustes.md`. Incluye los cambios exactos requeridos en cada capa (BD, modelo, CRUD, factory, seeder y modulo de Packing Slip) para que un desarrollador pueda implementarlos sin ambiguedad.

**Documentos previos:**
- `01_shipping_list_analysis.md` — Estructura del Packing Slip FPL-10
- `02_invoice_analysis.md` — Invoice FPL-12 y relacion 1:1 con el PS
- `03_field_mapping_lista_envio_to_packing_slip.md` — Mapeo de campos
- `04_empaque_to_shipping_list_transition.md` — Opciones de diseno
- `05_decisiones_confirmadas_y_plan_implementacion.md` — Plan de implementacion v1.0
- `06_impacto_respuestas_pendientes_y_ajustes.md` — Ajustes al plan y decision D-06-02
- `07_fpl10_cumplimiento_vs_implementacion.md` — Cumplimiento FPL-10 vs codigo

---

## 1. Contexto y Motivacion

### 1.1 Origen de la Decision

En el documento `06_impacto_respuestas_pendientes_y_ajustes.md`, seccion 2.2, se registro la decision D-06-02:

> En esta fase `label_spec` es ingreso manual en el paso 2 del wizard. Campo nullable en `packing_slip_items`. Sin vinculo con la tabla `parts` hasta nueva instruccion del cliente.
>
> Ruta de migracion futura (si el cliente decide moverlo a `parts`):
> 1. Agregar campo `label_spec` (varchar 150, nullable) a la tabla `parts`.
> 2. El wizard pre-llenara el campo con `part.label_spec` si existe, permitiendo sobreescritura manual.
> 3. El snapshot en `packing_slip_items.label_spec` no cambia (sigue siendo texto libre al momento del PS).

El cliente ha instruido que se proceda con esta migracion. El presente documento analiza el impacto completo.

### 1.2 Motivacion de Negocio

El campo `label_spec` representa la especificacion de etiqueta militar o aeronautica asociada a un numero de parte (ejemplos: `M83519/2-8`, `SAE AS81824/1-2`, `NAS1745-15`). Esta especificacion es un atributo del numero de parte en si mismo, no del lote ni del envio particular. Moverla a `parts` elimina la necesidad de que el usuario escriba manualmente el mismo valor cada vez que ese part number aparece en un Packing Slip.

---

## 2. Estado Actual

### 2.1 Estructura Actual de la Tabla `parts`

Archivo de migracion: `database/migrations/2025_12_10_051116_create_parts_table.php`

| Columna | Tipo | Restriccion | Notas |
|---|---|---|---|
| `id` | bigint unsigned | PK, auto increment | |
| `number` | varchar(255) | NOT NULL, UNIQUE | Numero de parte FlexCon |
| `item_number` | varchar(255) | NOT NULL, UNIQUE | Numero de item S.E.I.P. |
| `unit_of_measure` | varchar(255) | nullable | PZA, KG, M, etc. |
| `active` | tinyint | NOT NULL, default 1 | Bandera activo/inactivo |
| `label_spec` | varchar(150) | nullable | **NUEVO** — Especificacion de etiqueta militar/aeronautica |
| `is_crimp` | tinyint | NOT NULL, default 1 | Agregado en migracion 2026_02_19 |
| `description` | text | nullable | Descripcion del part |
| `notes` | varchar(255) | nullable | Notas adicionales |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | nullable | |
| `updated_at` | timestamp | nullable | |

Indice compuesto: `(number, active, item_number)`.

La columna `is_crimp` existe en el modelo (`$fillable` y `$casts`) pero no en la migracion original documentada. Se incluye en el modelo porque fue agregada posteriormente. El campo `label_spec` no existe actualmente.

### 2.2 Manejo Actual de `label_spec`

En el estado actual:
- `label_spec` solo existe en `packing_slip_items` (varchar 50, nullable).
- En `PackingSlipCreate.php`, `labelSpecs[$lotId]` se inicializa en `''` (cadena vacia) en dos puntos:
  - Metodo `toggleLot()` linea 56: `$this->labelSpecs[$lotId] = '';`
  - Metodo `render()` linea 123: `$this->labelSpecs[$lot->id] = '';`
- En `PackingSlipShow.php`, cuando se agrega un nuevo lote via `toggleLot()` linea 141: `$this->labelSpecs[$lotId] = '';`
- En `PackingSlipShow::initLotSelection()` linea 41, se recupera el valor guardado en el item: `$this->labelSpecs[$item->lot_id] = $item->label_spec ?? '';`
- El usuario escribe el valor manualmente en el formulario del wizard.

---

## 3. Cambio Propuesto en Base de Datos

### 3.1 Nueva Migracion

Nombre del archivo: `database/migrations/YYYY_MM_DD_HHMMSS_add_label_spec_to_parts_table.php`

Sustituir `YYYY_MM_DD_HHMMSS` con la marca de tiempo generada por `php artisan make:migration`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->string('label_spec', 150)
                  ->nullable()
                  ->after('active')
                  ->comment('Especificacion de etiqueta militar/aeronautica (ej: M83519/2-8, SAE AS81824/1-2)');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('label_spec');
        });
    }
};
```

Justificacion del posicionamiento `->after('active')`: El campo `label_spec` es un atributo de identificacion y clasificacion del part number, al igual que `active` e `is_crimp`. No es un campo de texto libre como `description` o `notes`. Posicionarlo inmediatamente despues de `active` agrupa los campos de clasificacion (`active`, `label_spec`, `is_crimp`) en un bloque coherente, separados de los campos de texto descriptivo (`description`, `notes`).

### 3.2 Impacto en Datos Existentes

Todos los registros existentes en `parts` tendran `label_spec = NULL` despues de ejecutar la migracion. Esto es el comportamiento esperado y no requiere migracion de datos, ya que:
1. El campo es nullable por diseno.
2. El valor `NULL` en `parts.label_spec` produce el mismo resultado que el estado actual (campo en blanco en el wizard).

### 3.3 Impacto en Migraciones Existentes

#### 3.3.1 La nueva migracion es independiente de `create_packing_slip_items_table`

La migracion `add_label_spec_to_parts_table` opera exclusivamente sobre la tabla `parts`. No toca, no referencia ni modifica la tabla `packing_slip_items` en ninguna de sus operaciones (`up()` ni `down()`). Ambas migraciones son completamente independientes entre si.

#### 3.3.2 `packing_slip_items.label_spec` permanece sin cambios

El campo `packing_slip_items.label_spec` (varchar 50, nullable, definido en `2026_03_08_100003_create_packing_slip_items_table.php` con la decision D-06-02) NO se modifica como parte de este desarrollo. Son dos campos con el mismo nombre en tablas distintas, con propositos distintos:

| Campo | Tabla | Tipo | Proposito |
|---|---|---|---|
| `label_spec` | `parts` | varchar(150), nullable | Valor maestro del part number; editable en el CRUD de partes |
| `label_spec` | `packing_slip_items` | varchar(50), nullable | Snapshot inmutable del valor confirmado por el usuario al momento de generar el PS; no se recalcula |

La existencia de `parts.label_spec` (nuevo) no altera la semantica ni el comportamiento de `packing_slip_items.label_spec` (existente).

#### 3.3.3 Verificacion del orden de migraciones

La nueva migracion `add_label_spec_to_parts_table` depende unicamente de que la tabla `parts` ya exista. No tiene ninguna dependencia con `create_packing_slip_items_table`. El orden de ejecucion es correcto:

| Archivo de migracion | Accion | Dependencia satisfecha |
|---|---|---|
| `2025_12_10_051116_create_parts_table.php` | Crea la tabla `parts` | — (primera) |
| `2026_02_19_063152_add_is_crimp_to_parts_table.php` | Agrega `is_crimp` a `parts` | `parts` existe |
| `NUEVA: add_label_spec_to_parts_table` (timestamp posterior a 2026_02_19) | Agrega `label_spec` a `parts` | `parts` existe |
| `2026_03_08_100003_create_packing_slip_items_table.php` | Crea `packing_slip_items` con su propio `label_spec` (independiente) | `parts` existe (FK) |

El archivo de la nueva migracion debe tener un timestamp posterior a `2026_02_19_063152`. Al generarse con `php artisan make:migration`, Laravel asigna automaticamente la marca de tiempo del momento de ejecucion, lo que garantiza el orden correcto.

#### 3.3.4 Ninguna migracion existente se modifica

El desarrollo consiste en crear UNA sola migracion nueva (`add_label_spec_to_parts_table`). Las migraciones `create_parts_table`, `add_is_crimp_to_parts_table` y `create_packing_slip_items_table` no se modifican.

#### 3.3.5 Impacto en Factories y Seeders

- **`PartFactory.php`**: actualmente NO incluye `is_crimp` en su `definition()`. Agregar `label_spec` al factory es seguro porque el campo es nullable. Los tests existentes que usan `PartFactory` siguen funcionando sin modificaciones porque Laravel no requiere que los campos nullable esten presentes en el array de creacion.
- **`PartSeeder.php`**: actualmente vacio. Sin cambio obligatorio como resultado de este desarrollo.
- Ningun factory ni seeder de otras tablas (`PackingSlip`, `PackingSlipItem`, etc.) se ve afectado.

---

## 4. Impacto en el Modelo `Part`

Archivo: `app/Models/Part.php`

### 4.1 Cambio en `$fillable`

Estado actual:
```php
protected $fillable = [
    'number',
    'item_number',
    'unit_of_measure',
    'active',
    'is_crimp',
    'description',
    'notes',
];
```

Estado requerido (agregar `'label_spec'` despues de `'notes'`):
```php
protected $fillable = [
    'number',
    'item_number',
    'unit_of_measure',
    'active',
    'is_crimp',
    'description',
    'notes',
    'label_spec',
];
```

### 4.2 Cambio en `$casts`

No se requiere ningun cambio en `$casts`. El campo `label_spec` es `string` y Laravel lo manejara correctamente sin cast explicito.

### 4.3 Cambio en `scopeSearch`

No se requiere cambio en `scopeSearch`. El campo `label_spec` no debe ser buscable desde la lista de partes en esta fase.

---

## 5. Impacto en el CRUD de Parts

### 5.1 `PartCreate.php`

Archivo: `app/Livewire/Admin/Parts/PartCreate.php`

**Propiedad a agregar** (despues de `public string $notes = ''`):

```php
public string $label_spec = '';
```

**Regla de validacion a agregar** en el metodo `rules()` (despues de la regla de `notes`):

```php
'label_spec' => 'nullable|string|max:150',
```

**Array de creacion en `savePart()`** — agregar entrada al array pasado a `Part::create()`:

```php
'label_spec' => $this->label_spec ?: null,
```

El bloque completo del array quedaria:

```php
Part::create([
    'number'          => $this->number,
    'item_number'     => $this->item_number,
    'unit_of_measure' => $this->unit_of_measure ?: null,
    'description'     => $this->description ?: null,
    'notes'           => $this->notes ?: null,
    'label_spec'      => $this->label_spec ?: null,
    'active'          => $this->active,
    'is_crimp'        => $this->is_crimp,
]);
```

### 5.2 `PartEdit.php`

Archivo: `app/Livewire/Admin/Parts/PartEdit.php`

**Propiedad a agregar** (despues de `public string $notes = ''`):

```php
public string $label_spec = '';
```

**Inicializacion en `mount()`** (despues de `$this->notes = $part->notes ?? ''`):

```php
$this->label_spec = $part->label_spec ?? '';
```

**Regla de validacion a agregar** en el metodo `rules()` (despues de la regla de `notes`):

```php
'label_spec' => 'nullable|string|max:150',
```

**Array de actualizacion en `updatePart()`** — agregar entrada al array pasado a `$this->part->update()`:

```php
'label_spec' => $this->label_spec ?: null,
```

El bloque completo del array quedaria:

```php
$this->part->update([
    'number'          => $this->number,
    'item_number'     => $this->item_number,
    'unit_of_measure' => $this->unit_of_measure ?: null,
    'description'     => $this->description ?: null,
    'notes'           => $this->notes ?: null,
    'label_spec'      => $this->label_spec ?: null,
    'active'          => $this->active,
    'is_crimp'        => $this->is_crimp,
]);
```

### 5.3 `PartList.php`

Archivo: `app/Livewire/Admin/Parts/PartList.php`

No se requieren cambios. `PartList` no muestra ni manipula `label_spec`. La columna no necesita aparecer en la tabla de listado.

### 5.4 `PartShow.php`

Archivo: `app/Livewire/Admin/Parts/PartShow.php`

No se requieren cambios en la clase PHP. El unico cambio es en la vista blade (ver seccion 6.4).

---

## 6. Impacto en las Vistas Blade de Parts

### 6.1 `part-create.blade.php`

Archivo: `resources/views/livewire/admin/parts/part-create.blade.php`

Agregar un bloque de campo de formulario despues del bloque de `notes` (lineas 45-50 del archivo actual) y antes del bloque de checkboxes:

```blade
<div>
    <label for="label_spec" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Label Spec</label>
    <input wire:model="label_spec" id="label_spec" type="text" placeholder="Ej: M83519/2-8, SAE AS81824/1-2"
        class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Especificacion de etiqueta militar/aeronautica. Maximo 150 caracteres.</p>
    @error('label_spec')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
</div>
```

### 6.2 `part-edit.blade.php`

Archivo: `resources/views/livewire/admin/parts/part-edit.blade.php`

Agregar el mismo bloque de campo de formulario que en `part-create.blade.php`, en la misma posicion relativa (despues del bloque de `notes`, antes de los checkboxes):

```blade
<div>
    <label for="label_spec" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Label Spec</label>
    <input wire:model="label_spec" id="label_spec" type="text" placeholder="Ej: M83519/2-8, SAE AS81824/1-2"
        class="block w-full px-3 py-2 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Especificacion de etiqueta militar/aeronautica. Maximo 150 caracteres.</p>
    @error('label_spec')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
</div>
```

### 6.3 `part-list.blade.php`

Archivo: `resources/views/livewire/admin/parts/part-list.blade.php`

No se requieren cambios. El campo `label_spec` no agrega valor al listado y agregar una columna adicional deterioraria la legibilidad de la tabla. Si en el futuro se requiere buscarlo o filtrarlo, se tratara en un documento aparte.

### 6.4 `part-show.blade.php`

Archivo: `resources/views/livewire/admin/parts/part-show.blade.php`

Agregar un bloque de visualizacion dentro de la seccion "Informacion de la parte" (el grid de `md:grid-cols-2` que va desde la linea 24 hasta la linea 53 del archivo actual). Colocarlo despues del bloque de `notes` (lineas 49-52 del archivo actual):

```blade
<div class="md:col-span-2">
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Label Spec</p>
    <p class="text-gray-900 dark:text-white">{{ $part->label_spec ?? '—' }}</p>
</div>
```

---

## 7. Impacto en `PartSeeder` y `PartFactory`

### 7.1 `PartSeeder.php`

Archivo: `database/seeders/PartSeeder.php`

El seeder actual esta vacio (cuerpo del metodo `run()` sin contenido). No se requiere cambio obligatorio. Sin embargo, si se decide poblar el seeder con datos de ejemplo, cada registro de tipo parte real deberia incluir el campo `label_spec` con su valor real, o `null` si la parte no tiene especificacion de etiqueta.

Ejemplo de como se veria si el seeder fuera a insertar datos:

```php
public function run(): void
{
    $parts = [
        [
            'number'          => 'PART-001',
            'item_number'     => '189-00001',
            'unit_of_measure' => 'PZA',
            'active'          => true,
            'is_crimp'        => true,
            'description'     => 'Connector, Circular',
            'notes'           => null,
            'label_spec'      => 'M83519/2-8',
        ],
        [
            'number'          => 'PART-002',
            'item_number'     => '189-00002',
            'unit_of_measure' => 'PZA',
            'active'          => true,
            'is_crimp'        => false,
            'description'     => 'Backshell Assembly',
            'notes'           => null,
            'label_spec'      => 'SAE AS81824/1-2',
        ],
        [
            'number'          => 'PART-003',
            'item_number'     => '189-00003',
            'unit_of_measure' => 'PZA',
            'active'          => true,
            'is_crimp'        => false,
            'description'     => 'Insert, Connector',
            'notes'           => null,
            'label_spec'      => null, // Sin especificacion de etiqueta
        ],
    ];

    foreach ($parts as $data) {
        Part::updateOrCreate(['number' => $data['number']], $data);
    }
}
```

Valores de ejemplo para `label_spec` con formato real de especificaciones militares/aeronauticas:
- `'M83519/2-8'` — Especificacion MIL (prefijo M)
- `'SAE AS81824/1-2'` — Especificacion SAE Aerospace Standard
- `'NAS1745-15'` — Especificacion NAS (National Aerospace Standard)
- `'MS27488-20'` — Especificacion MS (Military Standard)
- `null` — Parte sin especificacion de etiqueta asignada

### 7.2 `PartFactory.php`

Archivo: `database/factories/PartFactory.php`

Agregar la definicion de `label_spec` en el metodo `definition()`. El campo debe ser opcional con probabilidad del 60% de tener valor (para simular que no todas las partes tienen especificacion de etiqueta):

```php
public function definition(): array
{
    $labelSpecs = [
        'M83519/2-8',
        'M83519/2-12',
        'SAE AS81824/1-2',
        'SAE AS81824/2-4',
        'NAS1745-15',
        'NAS1745-20',
        'MS27488-20',
        'MS27488-16',
    ];

    return [
        'number'          => 'PART-' . $this->faker->unique()->numerify('######'),
        'item_number'     => 'ITEM-' . $this->faker->unique()->numerify('######'),
        'unit_of_measure' => $this->faker->randomElement(['PZA', 'KG', 'M', 'L', 'UN']),
        'active'          => $this->faker->boolean(80),
        'description'     => $this->faker->sentence(),
        'notes'           => $this->faker->optional()->sentence(),
        'label_spec'      => $this->faker->optional(0.6)->randomElement($labelSpecs),
    ];
}
```

El cambio minimo obligatorio es agregar la linea:
```php
'label_spec' => $this->faker->optional(0.6)->randomElement($labelSpecs),
```

junto con la definicion del array `$labelSpecs` dentro del metodo `definition()`.

---

## 8. Impacto en el Modulo de Packing Slip

### 8.1 Comportamiento Antes del Cambio

En `PackingSlipCreate.php`:

- `labelSpecs[$lotId]` se inicializa en `''` (cadena vacia) al seleccionar un lote.
- El usuario escribe manualmente el valor en el campo del formulario.
- Sin pre-llenado automatico.

En `PackingSlipShow.php`:

- Al agregar un lote nuevo via `toggleLot()`: `$this->labelSpecs[$lotId] = ''` (cadena vacia).
- Al editar lotes existentes del PS: se recupera el snapshot guardado en `packing_slip_items.label_spec`.

### 8.2 Comportamiento Despues del Cambio

Con `parts.label_spec` disponible, el wizard pre-llenara el campo con el valor del part number si existe, dejandolo editable para que el usuario pueda sobreescribirlo.

La cadena de relaciones para obtener la parte desde un lote es:

```
Lot -> workOrder (WorkOrder) -> purchaseOrder (PurchaseOrder) -> part (Part) -> label_spec
```

Esta cadena ya esta cargada con eager loading en ambos componentes:
- `PackingSlipCreate::render()`: `Lot::with(['workOrder.purchaseOrder.part'])`
- `PackingSlipShow::render()`: `Lot::with(['workOrder.purchaseOrder.part'])`

### 8.3 Cambios Exactos en `PackingSlipCreate.php`

Archivo: `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php`

**Cambio 1 — Metodo `toggleLot()`**

Linea actual (56):
```php
$this->labelSpecs[$lotId] = '';
```

Reemplazar con:
```php
$lot = Lot::with('workOrder.purchaseOrder.part')->find($lotId);
$this->labelSpecs[$lotId] = $lot?->workOrder?->purchaseOrder?->part?->label_spec ?? '';
```

Nota: El objeto `$lot` ya se busca en la linea 59 para `dateSpecs`. Consolidar las dos busquedas en una sola llamada:

```php
} else {
    $this->selectedLotIds[] = $lotId;
    $lot = Lot::with('workOrder.purchaseOrder.part')->find($lotId);
    $this->labelSpecs[$lotId] = $lot?->workOrder?->purchaseOrder?->part?->label_spec ?? '';
    // Pre-llenar Date con lot_number como valor provisional (D-06-01)
    if (!array_key_exists($lotId, $this->dateSpecs) || $this->dateSpecs[$lotId] === '') {
        $this->dateSpecs[$lotId] = $lot?->lot_number ?? '';
    }
}
```

**Cambio 2 — Metodo `render()`**

Linea actual (123):
```php
$this->labelSpecs[$lot->id] = '';
```

Reemplazar con:
```php
$this->labelSpecs[$lot->id] = $lot->workOrder?->purchaseOrder?->part?->label_spec ?? '';
```

El objeto `$lot` en este contexto ya proviene del eager load `Lot::with(['workOrder.purchaseOrder.part'])`, por lo que no se requiere una consulta adicional. La relacion ya esta cargada en memoria.

### 8.4 Cambios Exactos en `PackingSlipShow.php`

Archivo: `app/Livewire/Admin/PackingSlips/PackingSlipShow.php`

**Cambio 1 — Metodo `toggleLot()`**

Linea actual (140-141):
```php
if (!isset($this->labelSpecs[$lotId])) {
    $this->labelSpecs[$lotId] = '';
}
```

Reemplazar con:
```php
if (!isset($this->labelSpecs[$lotId])) {
    $lot = Lot::with('workOrder.purchaseOrder.part')->find($lotId);
    $this->labelSpecs[$lotId] = $lot?->workOrder?->purchaseOrder?->part?->label_spec ?? '';
}
```

Nota: El objeto `$lot` ya se busca en la linea 145 para `dateSpecs`. Consolidar las dos busquedas:

```php
} else {
    $this->selectedLotIds[] = $lotId;
    if (!isset($this->labelSpecs[$lotId])) {
        $lot = Lot::with('workOrder.purchaseOrder.part')->find($lotId);
        $this->labelSpecs[$lotId] = $lot?->workOrder?->purchaseOrder?->part?->label_spec ?? '';
        // Pre-llenar Date con lot_number como valor provisional (D-06-01)
        if (!isset($this->dateSpecs[$lotId]) || $this->dateSpecs[$lotId] === '') {
            $this->dateSpecs[$lotId] = $lot?->lot_number ?? '';
        }
    }
}
```

**Metodo `initLotSelection()` — Sin cambio**

El metodo `initLotSelection()` (lineas 33-44) ya carga correctamente el valor desde el snapshot guardado en `packing_slip_items.label_spec`:

```php
$this->labelSpecs[$item->lot_id] = $item->label_spec ?? '';
```

Este comportamiento es correcto y no debe cambiar: al inicializar la edicion de un PS existente, se muestra el snapshot guardado (no el valor actual de `parts.label_spec`). El usuario puede ver y modificar ese snapshot.

---

## 9. El Snapshot en `packing_slip_items.label_spec` No Cambia

Este punto es critico para la integridad historica del PS.

El campo `packing_slip_items.label_spec` es un snapshot inmutable del valor que el usuario confirmo al momento de crear o editar el PS. Fue definido como `varchar(50) nullable` en la migracion `2026_03_08_100003_create_packing_slip_items_table.php` bajo la decision D-06-02. Su tipo, longitud y comportamiento NO se modifican como parte de este desarrollo.

El nuevo campo `parts.label_spec` es `varchar(150)` en una tabla diferente. Los dos campos coexisten con propositos distintos:

- `parts.label_spec` (varchar 150): valor maestro administrado en el CRUD de partes.
- `packing_slip_items.label_spec` (varchar 50): snapshot del valor que el usuario confirmo en el wizard al momento de generar el PS. No se recalcula ni se actualiza automaticamente.

El comportamiento del modulo de PS antes y despues del cambio:

1. **Antes del cambio:** el usuario escribe manualmente el valor, el campo se guarda como snapshot.
2. **Despues del cambio:** el campo viene pre-llenado desde `parts.label_spec`, el usuario puede modificarlo, y el valor que el usuario confirma se guarda como snapshot en `packing_slip_items.label_spec` (varchar 50).

Si en el futuro el administrador cambia `parts.label_spec` para un numero de parte, los PS ya generados conservan el valor que estaba vigente al momento de su creacion. El pre-llenado solo aplica al momento de crear o editar los lotes de un PS.

Esto replica el mismo patron que ya se usa para `wo_number_ps` (snapshot del codigo de WO al momento del PS).

**Nota sobre diferencia de longitud (riesgo menor):** Si un administrador registra un `parts.label_spec` con mas de 50 caracteres, el wizard pre-llenara el campo correctamente en el formulario. Sin embargo, al guardar el PS, el snapshot se almacena en `packing_slip_items.label_spec` (varchar 50), lo que provocara truncamiento silencioso en la base de datos o un error de validacion si Laravel valida antes de insertar. El usuario debe acortar el valor en el formulario del wizard para que quepa en los 50 caracteres del snapshot. Ver seccion 11.6 para el analisis completo de este riesgo y la recomendacion de mitigacion.

---

## 10. Plan de Implementacion Paso a Paso

Los pasos estan ordenados para minimizar el riesgo de inconsistencias. Ejecutar en este orden exacto.

**Paso 1 — Crear la migracion de base de datos**

```bash
php artisan make:migration add_label_spec_to_parts_table
```

Abrir el archivo generado en `database/migrations/` y escribir el contenido exacto descrito en la seccion 3.1.

```bash
php artisan migrate
```

Verificar que la columna aparece en la tabla `parts`:
```sql
DESCRIBE parts;
```

**Paso 2 — Actualizar el modelo `Part`**

Archivo: `app/Models/Part.php`

Agregar `'label_spec'` al array `$fillable` en la posicion descrita en la seccion 4.1.

**Paso 3 — Actualizar `PartCreate.php`**

Archivo: `app/Livewire/Admin/Parts/PartCreate.php`

- Agregar propiedad `public string $label_spec = ''`.
- Agregar regla `'label_spec' => 'nullable|string|max:150'` en `rules()`.
- Agregar `'label_spec' => $this->label_spec ?: null` en el array de `Part::create()`.

**Paso 4 — Actualizar `PartEdit.php`**

Archivo: `app/Livewire/Admin/Parts/PartEdit.php`

- Agregar propiedad `public string $label_spec = ''`.
- Agregar `$this->label_spec = $part->label_spec ?? ''` en `mount()`.
- Agregar regla `'label_spec' => 'nullable|string|max:150'` en `rules()`.
- Agregar `'label_spec' => $this->label_spec ?: null` en el array de `$this->part->update()`.

**Paso 5 — Actualizar la vista `part-create.blade.php`**

Archivo: `resources/views/livewire/admin/parts/part-create.blade.php`

Agregar el bloque de input para `label_spec` descrito en la seccion 6.1.

**Paso 6 — Actualizar la vista `part-edit.blade.php`**

Archivo: `resources/views/livewire/admin/parts/part-edit.blade.php`

Agregar el bloque de input para `label_spec` descrito en la seccion 6.2.

**Paso 7 — Actualizar la vista `part-show.blade.php`**

Archivo: `resources/views/livewire/admin/parts/part-show.blade.php`

Agregar el bloque de visualizacion para `label_spec` descrito en la seccion 6.4.

**Paso 8 — Actualizar `PartFactory.php`**

Archivo: `database/factories/PartFactory.php`

Agregar el array `$labelSpecs` y la linea `'label_spec' => $this->faker->optional(0.6)->randomElement($labelSpecs)` en el metodo `definition()` segun la seccion 7.2.

**Paso 9 — Actualizar `PartSeeder.php` (si aplica)**

Archivo: `database/seeders/PartSeeder.php`

Si el seeder se utiliza para poblar datos de partes reales, agregar el campo `label_spec` a cada registro segun la seccion 7.1. Si el seeder permanece vacio, no se requiere cambio.

**Paso 10 — Actualizar `PackingSlipCreate.php`**

Archivo: `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php`

Aplicar los dos cambios descritos en la seccion 8.3:
- Metodo `toggleLot()`: consolidar la busqueda de `$lot` e inicializar `labelSpecs` con `part->label_spec ?? ''`.
- Metodo `render()`: inicializar `labelSpecs` con `$lot->workOrder?->purchaseOrder?->part?->label_spec ?? ''`.

**Paso 11 — Actualizar `PackingSlipShow.php`**

Archivo: `app/Livewire/Admin/PackingSlips/PackingSlipShow.php`

Aplicar el cambio descrito en la seccion 8.4:
- Metodo `toggleLot()`: consolidar la busqueda de `$lot` e inicializar `labelSpecs` con `part->label_spec ?? ''`.

**Paso 12 — Pruebas manuales**

1. Crear una parte nueva con `label_spec` poblado. Verificar que se guarda.
2. Editar la parte. Verificar que el campo pre-llena correctamente y que la actualizacion persiste.
3. Limpiar `label_spec` de la parte y guardar. Verificar que queda `NULL` en BD.
4. Crear un Packing Slip seleccionando un lote cuya parte tiene `label_spec`. Verificar que el campo aparece pre-llenado.
5. Crear un Packing Slip seleccionando un lote cuya parte tiene `label_spec = NULL`. Verificar que el campo aparece en blanco (comportamiento identico al estado anterior).
6. Sobreescribir el pre-llenado en el wizard y guardar. Verificar que el snapshot usa el valor sobreescrito, no el de `parts`.
7. Editar lotes de un PS existente (via `PackingSlipShow`). Verificar que al agregar un lote nuevo, `labelSpecs` se pre-llena con `parts.label_spec`.

---

## 11. Riesgos y Consideraciones

### 11.1 Parts Existentes con `label_spec = NULL`

Todas las partes existentes en BD tendran `label_spec = NULL` tras la migracion. El operador del Packing Slip experimentara el campo en blanco (cadena vacia en el formulario), que es exactamente el mismo comportamiento que tenia antes. No hay regresion ni cambio de comportamiento para parts sin `label_spec`.

### 11.2 Validacion: Opcional en `parts`, Opcional en `packing_slip_items`

El campo es opcional (`nullable`) tanto en `parts` como en `packing_slip_items`. No hay ninguna regla de negocio conocida que lo haga obligatorio. Si en el futuro el cliente indica que ciertas partes siempre deben tenerlo, se puede agregar validacion condicional en el CRUD de partes (por ejemplo, requerir `label_spec` si `is_crimp = true`), pero eso es fuera del alcance de este documento.

### 11.3 Longitud: 150 Caracteres

La longitud de 150 caracteres cubre ampliamente el rango de especificaciones reales observadas y deja margen para evoluciones futuras:
- `M83519/2-8` = 11 caracteres
- `SAE AS81824/1-2` = 16 caracteres
- `NAS1745-15` = 10 caracteres
- `MS27488-20` = 10 caracteres

La especificacion mas larga conocida en el sistema es de 16 caracteres. Se usa 150 caracteres para dar margen amplio a futuras especificaciones mas largas o compuestas (por ejemplo, especificaciones con sufijos adicionales, revisiones o identificadores de sub-componentes concatenados), manteniendo consistencia con posibles evoluciones del campo sin necesidad de una migracion adicional.

### 11.4 Consistencia del Eager Loading

La cadena de relaciones `workOrder.purchaseOrder.part` ya se usa en varios puntos del codigo de Packing Slip. El nuevo uso en `toggleLot()` de ambos componentes requiere agregar la carga de `workOrder.purchaseOrder.part` a la consulta de `Lot::find()` en ese metodo especifico. La alternativa de buscar el part en una consulta separada (`Part::find($lot->workOrder->purchaseOrder->part_id)`) es equivalente pero menos eficiente. Se recomienda usar el eager load como se muestra en la seccion 8.3.

### 11.5 Impacto en Tests

Si existen tests unitarios o de feature que creen instancias de `Part` via `PartFactory` o directamente, estos continuaran funcionando sin cambios porque:
1. `label_spec` es nullable: no rompe `Part::create()` sin el campo.
2. `PartFactory::definition()` podra incluir el campo pero con `optional(0.6)`, por lo que no es obligatorio en los tests.

### 11.6 Diferencia de longitud entre `parts.label_spec` y `packing_slip_items.label_spec`

| Campo | Tabla | Longitud maxima |
|---|---|---|
| `label_spec` | `parts` | varchar(150) |
| `label_spec` | `packing_slip_items` | varchar(50) |

Si el administrador registra un valor en `parts.label_spec` que supera los 50 caracteres, el wizard lo mostrara correctamente pre-llenado en el formulario. Sin embargo, al guardar el PS, la base de datos intentara almacenar ese valor en `packing_slip_items.label_spec` (varchar 50), lo que tiene dos posibles consecuencias:

- **Si MySQL esta en modo estricto (`STRICT_TRANS_TABLES`):** lanzara un error de datos y la transaccion fallara.
- **Si MySQL no esta en modo estricto:** truncara el valor silenciosamente a 50 caracteres, produciendo un snapshot incorrecto sin notificar al usuario.

**Recomendacion de mitigacion:** En los componentes Livewire de Packing Slip, la regla de validacion del campo `labelSpecs[*]` debe usar `max:50` (no `max:150`) para rechazar valores superiores a 50 caracteres antes de intentar la insercion. Esto previene el truncamiento y muestra al usuario un error de validacion claro. Verificar y ajustar las reglas de validacion en:

- `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php` — regla para `labelSpecs.*`
- `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` — regla para `labelSpecs.*`

Este riesgo es de severidad baja en la practica, dado que las especificaciones militares/aeronauticas reales observadas tienen como maximo 16 caracteres (ver seccion 11.3). Sin embargo, debe documentarse y mitigarse con validacion para evitar sorpresas si en el futuro se ingresan especificaciones compuestas o con sufijos mas largos.

---

## 12. Tabla de Decisiones

| ID | Decision | Estado |
|---|---|---|
| D-08-01 | Agregar `label_spec varchar(150) nullable` a la tabla `parts` con `->after('active')` | PROPUESTO |
| D-08-02 | Pre-llenar `labelSpecs[$lotId]` en wizard con `part->label_spec ?? ''` si la relacion existe | PROPUESTO |
| D-08-03 | `label_spec` en `parts` es opcional (nullable); no se agrega validacion `required` | PROPUESTO |
| D-08-04 | El snapshot en `packing_slip_items.label_spec` permanece sin cambios; es el valor confirmado por el usuario | PROPUESTO |
| D-08-05 | `PartList` no muestra columna `label_spec`; visible solo en `PartShow` y formularios de alta/edicion | PROPUESTO |
| D-08-06 | `PartFactory` incluye `label_spec` con `optional(0.6)` y muestra de valores reales de especificaciones militares | PROPUESTO |
| D-08-07 | `packing_slip_items.label_spec` permanece varchar(50) sin cambios; es snapshot independiente de `parts.label_spec` varchar(150) | CONFIRMADO |
| D-06-02 | (Supersedida) `label_spec` era ingreso manual sin vinculo a `parts`. Ahora se mueve a `parts` por instruccion del cliente. | CERRADA — reemplazada por D-08-01 a D-08-05 |

---

## 13. Resumen de Archivos Afectados

| Archivo | Tipo de cambio | Prioridad |
|---|---|---|
| `database/migrations/XXXXXX_add_label_spec_to_parts_table.php` | Nuevo archivo — agrega columna `label_spec varchar(150) nullable` con `->after('active')` | Alta |
| `app/Models/Part.php` | Modificacion (`$fillable`) | Alta |
| `app/Livewire/Admin/Parts/PartCreate.php` | Modificacion (propiedad, regla, array create) | Alta |
| `app/Livewire/Admin/Parts/PartEdit.php` | Modificacion (propiedad, mount, regla, array update) | Alta |
| `resources/views/livewire/admin/parts/part-create.blade.php` | Modificacion (nuevo input) | Alta |
| `resources/views/livewire/admin/parts/part-edit.blade.php` | Modificacion (nuevo input) | Alta |
| `resources/views/livewire/admin/parts/part-show.blade.php` | Modificacion (nuevo campo de visualizacion) | Media |
| `database/factories/PartFactory.php` | Modificacion (agregar `label_spec` a definition) | Media |
| `app/Livewire/Admin/PackingSlips/PackingSlipCreate.php` | Modificacion (pre-llenado en toggleLot y render) | Alta |
| `app/Livewire/Admin/PackingSlips/PackingSlipShow.php` | Modificacion (pre-llenado en toggleLot) | Alta |
| `database/seeders/PartSeeder.php` | Modificacion condicional (solo si se usa el seeder) | Baja |
| `app/Livewire/Admin/Parts/PartList.php` | Sin cambios | — |
| `resources/views/livewire/admin/parts/part-list.blade.php` | Sin cambios | — |
| `database/migrations/2026_03_08_100003_create_packing_slip_items_table.php` | Sin cambios — snapshot independiente; `label_spec varchar(50)` permanece tal cual (D-08-07) | — |
| `database/migrations/2025_12_10_051116_create_parts_table.php` | Sin cambios — la nueva migracion es un archivo separado adicional, no una modificacion de este | — |
