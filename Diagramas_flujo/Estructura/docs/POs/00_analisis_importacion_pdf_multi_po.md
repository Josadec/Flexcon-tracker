# Analisis Tecnico: Importacion Masiva de Purchase Orders desde PDF Multi-Hoja

**Fecha:** 2026-03-24
**Autor:** Agent Architect
**Modulo afectado:** Purchase Orders (admin/purchase-orders)
**Estado:** Borrador — pendiente de aprobacion

---

## 1. Resumen Ejecutivo

Sumitomo Electric envia a Flexcon sus Purchase Orders como archivos PDF que pueden contener N hojas, donde cada hoja representa una PO independiente. Actualmente el operador debe separar el archivo manualmente y cargar cada PO una a una mediante el formulario existente, lo que es propenso a errores y consume tiempo significativo cuando llegan lotes de 5 a 20 POs en un mismo PDF.

La solucion propuesta es agregar una nueva funcionalidad de **importacion masiva** que permita subir un solo PDF, extraer automaticamente el texto de cada pagina, identificar y parsear los campos de cada PO, validar precios contra el catalogo existente y presentar al operador un reporte de confirmacion antes de importar las POs al modulo existente. Esta solucion reutiliza los servicios actuales de validacion de precios (`POPriceDetectionService`, `PurchaseOrderService`) sin modificarlos, y agrega una capa de procesamiento PDF encima de la arquitectura existente.

---

## 2. Estructura Actual del Modulo PO

### 2.1 Modelo: `PurchaseOrder`

Ubicacion: `app/Models/PurchaseOrder.php`

| Campo | Tipo DB | Nullable | Descripcion |
|---|---|---|---|
| `id` | bigint PK | No | Identificador interno |
| `po_number` | string UNIQUE | No | Numero de orden del cliente |
| `wo` | string | Si | WO del cliente (campo de texto libre) |
| `part_id` | FK -> parts | No | Parte asociada |
| `po_date` | date | No | Fecha de emision de la PO |
| `due_date` | date | No | Fecha de entrega requerida |
| `quantity` | integer | No | Cantidad en piezas |
| `unit_price` | decimal(10,4) | No | Precio unitario |
| `status` | string | No | pending / approved / rejected / pending_correction |
| `comments` | text | Si | Observaciones libres |
| `pdf_path` | string | Si | Ruta al archivo PDF en storage |
| `deleted_at` | timestamp | Si | SoftDeletes |

**Indices existentes:** `(status, po_date)` y `(part_id, status)`.

**Constantes de estado:**
- `STATUS_PENDING` — recien creada, sin revisar
- `STATUS_APPROVED` — precio validado, WO creada
- `STATUS_REJECTED` — rechazada por el operador
- `STATUS_PENDING_CORRECTION` — precio no coincide con catalogo

**Relaciones relevantes:**
- `part()` — BelongsTo Part (contiene `number`, `description`, `item_number`)
- `workOrder()` — HasOne WorkOrder (se crea al aprobar)
- `sentLists()` — BelongsToMany SentList
- `signatures()` — HasMany DocumentSignature

### 2.2 Catalogo de Precios: `Price` y `PriceTier`

Ubicacion: `app/Models/Price.php`

La validacion de precios es por **tipo de estacion de trabajo** derivado del Standard activo de la parte:

| Tipo | Constante | Tiers de cantidad |
|---|---|---|
| Mesa de Trabajo | `WORKSTATION_TABLE` | 1-999, 1000-10999, 11000-99999, 100000+ |
| Maquina | `WORKSTATION_MACHINE` | 1-9999, 10000-49999, 50000+ |
| Semi-Automatica | `WORKSTATION_SEMI_AUTOMATIC` | 2000-10000, 11000+ |

Cada `Price` tiene un campo `active` (boolean) y puede tener multiples `PriceTier` con `min_quantity`, `max_quantity` y `tier_price`. La restriccion de unicidad de un precio activo por tipo de estacion se garantiza mediante **triggers en la base de datos** (creados en la migracion `2026_01_22`).

### 2.3 Servicios Existentes

**`POPriceDetectionService`** (`app/Services/POPriceDetectionService.php`):
- `detectPrice(PurchaseOrder $po)` — dado un PO instanciado, detecta el precio correcto buscando el Standard activo de la parte y mapeando su `assembly_mode` al `workstation_type`.
- `detectPriceForPart(int $partId, int $quantity)` — version sin PO creado, util para validaciones en tiempo real en formularios.
- Retorna un objeto `PriceDetectionResult` con `found`, `price`, `workstationType`, `error`.

**`PurchaseOrderService`** (`app/Services/PurchaseOrderService.php`):
- `validatePrice(PurchaseOrder $po)` — delega a `POPriceDetectionService` y retorna `[valid, expected_price, message]`.
- `markAsPendingCorrection(PurchaseOrder $po, string $reason)` — cambia status y guarda el motivo en comments.
- `approve(PurchaseOrder $po)` — valida precio y cambia a `approved`.
- `approveAndCreateWO(PurchaseOrder $po)` — aprueba y crea Work Order en una transaccion.
- `getExpectedPrice(int $partId, int $quantity)` — metodo de conveniencia sin PO creado.

**`InvalidPOPriceException`** (`app/Exceptions/InvalidPOPriceException.php`):
- Excepcion tipada HTTP 422 para precios invalidos, con soporte JSON y redirect.

### 2.4 Componentes Livewire Actuales

| Componente | Ruta | Responsabilidad |
|---|---|---|
| `POCreate` | `/purchase-orders/create` | Formulario de creacion manual, 1 PO |
| `POEdit` | `/purchase-orders/{id}/edit` | Edicion de una PO |
| `POList` | `/purchase-orders` | Listado paginado con filtros y acciones |
| `POShow` | `/purchase-orders/{id}` | Vista detalle con validacion de precio |

**Flujo actual de creacion manual:**
1. Operador navega a `/admin/purchase-orders/create`.
2. Llena manualmente: numero de PO, WO del cliente, selecciona la parte del catalogo, fechas, cantidad, precio, sube el PDF.
3. Al cambiar `part_id`, `quantity` o `unit_price` el componente llama a `detectPriceForPart()` en tiempo real y muestra retroalimentacion visual (verde/naranja).
4. Al guardar, el servicio valida el precio y asigna `status = pending` o `status = pending_correction` automaticamente.

### 2.5 Rutas Disponibles

El modulo de POs vive bajo el middleware `role:admin` en `routes/admin.php`. Las rutas actuales son:

```
GET  /admin/purchase-orders              -> POList
GET  /admin/purchase-orders/create       -> POCreate
GET  /admin/purchase-orders/{po}         -> POShow
GET  /admin/purchase-orders/{po}/edit    -> POEdit
```

No existe todavia ninguna ruta de importacion masiva.

### 2.6 Almacenamiento de PDFs

Los PDFs se guardan en `storage/app/public/purchase-orders/` mediante `$file->store('purchase-orders', 'public')`. El campo `pdf_path` en la tabla guarda la ruta relativa al disco `public`.

---

## 3. Estructura del PDF Recibido

### 3.1 Ejemplo Analizado

El archivo `Flexcon PO 50297 - 50298.pdf` disponible en `docs/POs/` contiene dos hojas. A continuacion se describe la estructura visual de cada hoja basada en el contexto del negocio:

**Cabecera del documento (compartida, aparece en cada hoja):**
- Nombre del cliente: Sumitomo Electric Interconnect Products, Inc.
- Direccion: San Marcos, CA 92069
- Vendor Number: 8694

**Cuerpo de cada hoja (una PO por hoja):**

| Etiqueta en PDF | Ejemplo Hoja 1 | Ejemplo Hoja 2 |
|---|---|---|
| PO Number | 50297 | 50298 |
| Item / Description | STS H-MB-401T | STS H-C-2-2 |
| Part Number (Vendor) | 189-10436*OP10 | 189-10629*OP10 |
| Quantity | 1000 PC | 1000 PC |
| Unit Price | $0.2352 | $0.1070 |
| Amount / Total | $235.20 | $107.00 |
| Due Date | 02/20/26 | 03/27/26 |
| Branch | 300 | 300 |
| WO (del cliente) | 2032495 | 2032508 |

### 3.2 Consideraciones de Formato

- **Fecha de emision de la PO:** El PDF muestra la fecha de emision general (puede estar en la cabecera o en cada hoja). Es posible que sea la misma para todo el lote; es un campo que puede no estar claramente diferenciado por pagina.
- **Unidad de medida:** Aparece junto a la cantidad ("PC"). El sistema siempre trabaja en piezas; es un validador de consistencia, no un campo a almacenar.
- **Branch:** Codigo interno de Sumitomo. No existe campo equivalente en `purchase_orders`. Se podria almacenar en `comments` o ignorar, pero debe documentarse.
- **Amount:** Es el resultado de `Quantity * Unit Price`. Se puede calcular; no requiere campo propio en la DB.
- **Separacion por paginas:** El texto extraido por una libreria PDF incluye algun separador de pagina (caracter `\f` o salto de linea largo). La logica de extraccion debe dividir el texto total por pagina antes de parsear.

---

## 4. Analisis de Brechas

### Lo que el sistema actual tiene

| Capacidad | Estado |
|---|---|
| Creacion de una PO a la vez (manual) | Disponible |
| Validacion de precio contra catalogo | Disponible (`POPriceDetectionService`) |
| Estado `pending_correction` para precio incorrecto | Disponible |
| Busqueda de parte por `number` o `item_number` | Disponible via Eloquent |
| Almacenamiento de PDF original | Disponible (storage/public) |
| Aprobacion y creacion de WO automatica | Disponible (`PurchaseOrderService::approveAndCreateWO`) |

### Lo que NO tiene el sistema actual

| Brecha | Impacto |
|---|---|
| Extraccion de texto desde PDF | Critico — no existe ninguna libreria de procesamiento PDF |
| Parseo de campos desde texto libre del PDF | Critico — requiere logica de reconocimiento de patrones |
| Importacion de multiples POs en un solo flujo | Critico — no existe componente ni ruta |
| Tabla intermedia para POs extraidas pendientes de confirmacion | Critico — necesaria para el flujo de revision antes de importar |
| Identificacion automatica de `part_id` a partir del Part Number del PDF | Critico — el PDF contiene el numero de parte del vendedor, el sistema usa IDs internos |
| Modelo/vista para el reporte de analisis de precios por lote | Critico — actualmente solo existe por PO individual |
| Manejo de PDFs con multiples POs (1 PDF -> N registros) | Critico — el campo `pdf_path` es 1:1 con una PO |
| Validacion de duplicados antes de importar (`po_number` UNIQUE) | Importante — si la misma PO llega en dos envios, el sistema debe detectarlo |
| Campo para almacenar `Branch` del cliente | Menor — se puede usar `comments` transitoriamente |

---

## 5. Arquitectura Propuesta

### 5.1 Vision General de Componentes Nuevos

```
PDF Multi-Hoja
      |
      v
[POImportUpload]  <-- Livewire Component (nuevo)
      |
      v
[PDFExtractorService]  <-- Servicio nuevo
      |  Extrae texto por pagina
      v
[POPdfParserService]  <-- Servicio nuevo
      |  Parsea campos de cada pagina
      v
[POImportBatch]  <-- Modelo nuevo (tabla intermedia)
      |
      v
[POImportItem]   <-- Modelo nuevo (una fila por PO extraida)
      |
      v
[POImportReviewComponent]  <-- Livewire Component (nuevo)
      |  El operador revisa el reporte de precios
      v
[PurchaseOrderService::createFromImport()]  <-- Metodo nuevo en servicio existente
      |  Solo las POs sin discrepancia o con confirmacion del operador
      v
[purchase_orders]  <-- Tabla existente, sin cambios
```

### 5.2 Servicio de OCR para Extraccion de Texto

Dado que los PDFs recibidos son **100% escaneados** (imagenes rasterizadas dentro del contenedor PDF sin capa de texto), `smalot/pdfparser` queda **completamente descartado**. La extraccion de texto requiere un motor OCR real. Ver Seccion 12 para el analisis completo de todos los escenarios evaluados.

**Solucion elegida: Google Cloud Vision API**

- No requiere instalacion de binarios en Windows/XAMPP.
- Acepta el archivo PDF directamente via API — no se necesita conversion previa a imagen.
- Precision de 98-99% en documentos impresos de calidad media/alta.
- Costo real para este proyecto: $0.00/mes (dentro del tier gratuito de 1,000 unidades/mes).
- Integracion mediante el cliente HTTP de Laravel — sin SDK complejo.
- Retorna el texto de cada pagina del PDF en una sola llamada.

**Paquete PHP:**
```
composer require google/cloud-vision
```

**Variables de entorno requeridas (agregar a `.env`):**
```
GOOGLE_CLOUD_VISION_API_KEY=tu_api_key_aqui
PDF_OCR_MIN_TEXT_CONFIDENCE=70
```

**Alternativa documentada para uso sin internet:**
Si el entorno no tiene acceso a internet o se requiere procesamiento offline, se puede sustituir la implementacion de `GoogleVisionOCRDriver` por `TesseractOCRDriver` sin cambiar el resto de la arquitectura. Ver Seccion 12 para los requisitos de instalacion de Tesseract en Windows.

### 5.3 Nuevo Servicio: `PDFExtractorService`

Responsabilidad unica: recibir la ruta a un archivo PDF almacenado y retornar un array de strings, uno por pagina, utilizando OCR.

La implementacion sigue el **patron Strategy** para poder intercambiar el motor OCR sin modificar el servicio consumidor. El motor activo se configura via `.env`.

```
// Contrato del driver OCR
interface OCRDriverInterface
{
    public function extractPagesFromPdf(string $pdfPath): array
        // Retorna array de OCRPageResult, uno por pagina del PDF
}

// Resultado por pagina
class OCRPageResult
{
    public string  $text;           // Texto extraido
    public int     $pageNumber;     // Numero de pagina (1-based)
    public string  $method;         // 'google_vision' | 'tesseract' | 'failed'
    public int     $confidence;     // 0-100
    public ?string $error;          // Mensaje si fallo
}

// Implementacion principal
class GoogleVisionOCRDriver implements OCRDriverInterface
{
    public function extractPagesFromPdf(string $pdfPath): array
        // Envia el PDF (en base64) a la Vision API
        // Usa feature DOCUMENT_TEXT_DETECTION (optimizado para documentos densos)
        // Retorna texto por pagina

    private function callVisionApi(string $pdfBase64, int $totalPages): array
        // Llama a: POST https://vision.googleapis.com/v1/files:annotate
        // Con inputConfig.mimeType = 'application/pdf'
        // Con feature.type = 'DOCUMENT_TEXT_DETECTION'
}

// Alternativa offline
class TesseractOCRDriver implements OCRDriverInterface
{
    public function extractPagesFromPdf(string $pdfPath): array
        // 1. Convierte cada pagina del PDF a imagen PNG (300 DPI) usando Ghostscript
        // 2. Pasa cada imagen a Tesseract via thiagoalessio/tesseract_ocr
        // 3. Retorna el texto por pagina
}

// Servicio principal (sin cambios en su contrato publico)
class PDFExtractorService
{
    public function extractPages(string $pdfPath): array
        // Delega al OCRDriverInterface activo
        // Retorna array de OCRPageResult
        // Lanza PDFExtractionException si el archivo no es legible o la API falla

    public function countPages(string $pdfPath): int
        // Cuenta paginas usando finfo/metadata del PDF antes de llamar a OCR
        // Util para decidir si procesar de forma sincrona o asincrona
}
```

### 5.4 Nuevo Servicio: `POPdfParserService`

Responsabilidad: recibir el texto de una pagina y extraer los campos de una PO. Utiliza expresiones regulares calibradas para el formato de Sumitomo Electric.

```
class POPdfParserService
{
    public function parse(string $pageText): POPdfParseResult
        // Retorna objeto con campos extraidos y confianza de extraccion

    public function canParse(string $pageText): bool
        // Detecta si la pagina contiene una PO valida (vs. pagina de indice, portada, etc.)
}

class POPdfParseResult
{
    public ?string $poNumber;
    public ?string $wo;
    public ?string $partNumber;   // numero del vendedor, p.ej. "189-10436*OP10"
    public ?string $itemDescription; // descripcion del item
    public ?int    $quantity;
    public ?float  $unitPrice;
    public ?float  $amount;
    public ?string $dueDate;      // string crudo, p.ej. "02/20/26"
    public ?string $branch;
    public bool    $isComplete;   // todos los campos obligatorios presentes
    public array   $missingFields;
    public string  $rawText;      // texto original para auditoria
}
```

### 5.5 Nuevo Servicio: `POImportService`

Orquesta el flujo completo de importacion. Agrupa `PDFExtractorService`, `POPdfParserService` y la resolucion de partes.

```
class POImportService
{
    public function processPdf(string $pdfStoragePath, int $userId): POImportBatch
        // 1. Extrae paginas
        // 2. Parsea cada pagina
        // 3. Resuelve part_id por part number del vendedor
        // 4. Valida precio usando POPriceDetectionService
        // 5. Crea y persiste POImportBatch + POImportItems
        // 6. Retorna el batch para que el componente Livewire muestre el reporte

    public function confirmImport(POImportBatch $batch, array $selectedItemIds): array
        // Importa solo los items seleccionados/confirmados
        // Crea registros en purchase_orders
        // Retorna [created: [...], skipped: [...], errors: [...]]

    private function resolvePartId(string $vendorPartNumber): ?int
        // Busca en parts por number o item_number
}
```

### 5.6 Nuevas Tablas en Base de Datos

#### Tabla: `po_import_batches`

Representa una sesion de importacion (un PDF subido).

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | - |
| `user_id` | FK -> users | Quien subio el PDF |
| `original_filename` | string | Nombre original del archivo subido |
| `pdf_storage_path` | string | Ruta en storage del PDF cargado |
| `total_pages` | integer | Cuantas paginas tenia el PDF |
| `total_pos_found` | integer | Cuantas POs se identificaron |
| `status` | enum | `processing`, `pending_review`, `completed`, `failed` |
| `error_message` | text NULL | Error general si fallo la extraccion |
| `created_at` | timestamp | - |
| `updated_at` | timestamp | - |

#### Tabla: `po_import_items`

Representa una PO individual extraida de un batch.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | bigint PK | - |
| `po_import_batch_id` | FK -> po_import_batches | Batch padre |
| `page_number` | integer | Numero de pagina de origen en el PDF |
| `po_number_extracted` | string NULL | Numero de PO tal como aparece en el PDF |
| `wo_extracted` | string NULL | WO del cliente extraida |
| `part_number_extracted` | string NULL | Part number del vendedor extraido |
| `item_description_extracted` | string NULL | Descripcion extraida |
| `quantity_extracted` | integer NULL | Cantidad extraida |
| `unit_price_extracted` | decimal(10,4) NULL | Precio extraido del PDF |
| `due_date_extracted` | date NULL | Fecha de entrega extraida |
| `branch_extracted` | string NULL | Branch extraido |
| `part_id` | FK -> parts NULL | Parte resuelta en el catalogo (NULL si no encontrada) |
| `expected_price` | decimal(10,4) NULL | Precio esperado segun catalogo |
| `price_status` | enum | `ok`, `mismatch`, `no_price`, `no_part`, `parse_error` |
| `price_message` | text NULL | Descripcion del resultado de validacion |
| `import_status` | enum | `pending`, `imported`, `skipped`, `error` |
| `purchase_order_id` | FK -> purchase_orders NULL | PO creada tras confirmacion |
| `parse_confidence` | tinyint | 0-100, porcentaje de confianza del parser |
| `raw_text` | text NULL | Texto crudo de la pagina (auditoria) |
| `created_at` | timestamp | - |
| `updated_at` | timestamp | - |

**Indices propuestos:**
- `(po_import_batch_id, price_status)` — para filtrar por resultado en el reporte
- `(po_import_batch_id, import_status)` — para saber cuantas se importaron
- `(po_number_extracted)` — para detectar duplicados rapido
- `(part_id, price_status)` — para analisis

#### Modelo: `POImportBatch`

Relaciones:
- `items()` — HasMany POImportItem
- `user()` — BelongsTo User

#### Modelo: `POImportItem`

Relaciones:
- `batch()` — BelongsTo POImportBatch
- `part()` — BelongsTo Part (nullable)
- `purchaseOrder()` — BelongsTo PurchaseOrder (nullable, post-importacion)

### 5.7 Nuevo Componente Livewire: `POImportUpload`

Ruta propuesta: `GET /admin/purchase-orders/import`

Responsabilidades:
- Recibe el archivo PDF mediante `WithFileUploads`.
- Valida: tipo PDF, tamanio maximo (sugerido 20MB para lotes grandes).
- Al subir, llama a `POImportService::processPdf()` de forma asincrona o sincrona segun el numero de paginas.
- Redirige al componente de revision con el `batch_id`.

Propiedades:
```
$pdf_file         -- archivo subido temporalmente
$processing       -- bool, muestra spinner
$error_message    -- string, error general
```

### 5.8 Nuevo Componente Livewire: `POImportReview`

Ruta propuesta: `GET /admin/purchase-orders/import/{batch}`

Responsabilidades:
- Muestra una tabla con todas las `POImportItem` del batch.
- Para cada item muestra: estado del precio (ok/mismatch/no_part), datos extraidos, precio esperado vs. extraido, delta porcentual.
- Permite al operador:
  - Seleccionar/deseleccionar que POs importar.
  - Editar manualmente campos de items con `parse_confidence` bajo (parte no encontrada, fecha invalida, etc.).
  - Confirmar la importacion de las POs seleccionadas.
- Llama a `POImportService::confirmImport()` y muestra resumen final.

Propiedades sugeridas:
```
$batch              -- POImportBatch con items cargados
$selectedItems      -- array de IDs a importar
$showRawText        -- bool, toggle para ver texto crudo de cada pagina
$filterStatus       -- 'all' | 'ok' | 'mismatch' | 'no_part'
```

---

## 6. Mapeo de Campos PDF a Modelo PurchaseOrder

| Campo en PDF | Valor Ejemplo | Campo en `purchase_orders` | Transformacion requerida |
|---|---|---|---|
| PO Number | `50297` | `po_number` | Directo (string) |
| WO (del cliente) | `2032495` | `wo` | Directo (string) |
| Part Number | `189-10436*OP10` | `part_id` | Buscar en `parts.number` o `parts.item_number` -> obtener ID |
| Item Description | `STS H-MB-401T` | No almacenado directamente | Usar como ayuda para identificar la parte; se puede guardar en `comments` |
| Quantity | `1000 PC` | `quantity` | Extraer numero, descartar "PC" |
| Unit Price | `$0.2352` | `unit_price` | Remover simbolo "$", convertir a decimal |
| Amount | `$235.20` | No almacenado | Calcular y usar como validacion cruzada: `quantity * unit_price = amount` |
| Due Date | `02/20/26` | `due_date` | Convertir de MM/DD/YY a fecha Carbon (Y-m-d) |
| Branch | `300` | Sin campo propio | Almacenar en `comments` o ignorar |
| Fecha de emision PO | En cabecera | `po_date` | Extraer fecha del encabezado del documento; si no hay, usar `today()` |
| Vendor Number | `8694` | Sin campo | Validacion de que el documento pertenece a Sumitomo |
| Nombre del cliente | Sumitomo Electric... | Sin campo | Validacion de proveedor |

**Campos que no tienen equivalente en el PDF pero son requeridos por el modelo:**
- `status` — se asigna automaticamente segun resultado de validacion de precio.
- `pdf_path` — el PDF original del batch se puede vincular a cada PO (todas comparten el mismo PDF fisico).
- `comments` — se puede usar para almacenar Branch y la descripcion del item importado.

**Campos requeridos por el modelo que el formulario actual exige pero el PDF no siempre expone claramente:**
- `po_date` — la fecha de emision de la PO puede estar solo en la cabecera general, no en cada hoja. Se necesita estrategia de fallback.

---

## 7. Flujo de Usuario Propuesto

### Paso 1 — Acceso a la funcionalidad

El operador ve un nuevo boton "Importar desde PDF" en la pagina de listado de POs (`/admin/purchase-orders`), junto al boton "Nueva PO" existente.

### Paso 2 — Carga del archivo

El operador navega a `/admin/purchase-orders/import`. Ve una pantalla simple con:
- Un area de drag & drop para subir el PDF.
- Indicacion de limites: "Maximo 20MB, formato PDF. Se admiten documentos digitales y escaneados."
- Boton "Analizar PDF".

### Paso 3 — Procesamiento (extraccion y validacion)

El sistema muestra un spinner mientras:
1. Guarda el PDF temporalmente en storage.
2. Extrae el texto de cada pagina.
3. Parsea los campos de cada PO encontrada.
4. Busca cada `part_number` en el catalogo.
5. Valida el precio contra el catalogo usando `POPriceDetectionService`.
6. Persiste el `POImportBatch` y los `POImportItem` en la base de datos.

Si el proceso tarda mas de 30 segundos (lotes grandes), se recomienda procesamiento asincrono mediante un Job de Laravel. En la primera version puede ser sincrono con un limite practico de ~20 paginas.

### Paso 4 — Reporte de revision

El sistema redirige a `/admin/purchase-orders/import/{batch_id}`. El operador ve una tabla con N filas (una por PO extraida) con columnas:

| Pagina | # PO | Parte encontrada | Qty | Precio PDF | Precio Catalogo | Diferencia | Estado | Accion |
|---|---|---|---|---|---|---|---|---|
| 1 | 50297 | 189-10436*OP10 (encontrada) | 1,000 | $0.2352 | $0.2352 | $0.00 | Precio OK | [x] Importar |
| 2 | 50298 | 189-10629*OP10 (encontrada) | 1,000 | $0.1070 | $0.1100 | -$0.0030 | Precio Incorrecto | [ ] Importar |

Codificacion de colores:
- Verde — precio coincide, parte encontrada.
- Naranja — precio no coincide con catalogo (discrepancia).
- Rojo — parte no encontrada en catalogo, o error de parseo.

El operador puede:
- Seleccionar las POs a importar individualmente.
- Ver el texto crudo de cada pagina (boton "Ver texto extraido") para verificar el parseo.
- Para items con error de parseo o parte no encontrada: editar manualmente los campos antes de importar.

### Paso 5 — Confirmacion

El operador hace clic en "Importar seleccionadas". El sistema:
1. Crea un registro en `purchase_orders` por cada item seleccionado.
2. Asigna `status = pending` si el precio es correcto, `status = pending_correction` si no coincide.
3. Vincula el PDF original del batch como `pdf_path` de cada PO creada.
4. Actualiza el `import_status` de cada `POImportItem` a `imported` o `skipped`.
5. Muestra un resumen: "X POs importadas correctamente, Y omitidas, Z con error".

### Paso 6 — Flujo normal

Las POs importadas aparecen en el listado normal de `/admin/purchase-orders`. Las que tienen `status = pending_correction` son visibles con su badge naranja y el operador puede gestionarlas desde el flujo existente.

---

## 8. Riesgos y Consideraciones

### 8.1 Variaciones en el Formato del PDF

**Riesgo alto.** Los PDFs de Sumitomo pueden variar entre versiones del sistema que los genera (SAP, Oracle, etc.). Un cambio de formato puede romper el parser.

- **Mitigacion:** El campo `raw_text` en `po_import_items` almacena el texto original de cada pagina, lo que permite re-parsear sin volver a subir el PDF. Disenar el `POPdfParserService` con multiples estrategias de regex y un sistema de confianza (`parse_confidence`).
- **Mitigacion adicional:** Permitir edicion manual de cualquier campo en la pantalla de revision. Si el parser falla parcialmente, el operador puede corregir sin rehacer todo el proceso.

### 8.2 PDFs Escaneados — Resuelto mediante Google Cloud Vision OCR

**Estado:** RESUELTO a nivel arquitectural. Los PDFs de Sumitomo son 100% escaneados (confirmado). La arquitectura propuesta en la Seccion 5 utiliza Google Cloud Vision API como motor OCR, eliminando este riesgo como factor bloqueante.

**Diseno de mitigacion implementado:**
- `PDFExtractorService` delega a `GoogleVisionOCRDriver` (implementacion de `OCRDriverInterface`).
- Vision API acepta el PDF escaneado directamente y retorna texto reconocido por pagina con nivel de confianza.
- Las paginas donde Vision retorna confianza menor al umbral configurable (`PDF_OCR_MIN_TEXT_CONFIDENCE`) se marcan con `extraction_method = 'failed'` y el operador las ve con un badge de advertencia en la pantalla de revision.
- El patron Strategy permite sustituir `GoogleVisionOCRDriver` por `TesseractOCRDriver` via variable de entorno sin modificar otros servicios.

Ver Seccion 12 para el analisis completo de todos los escenarios OCR evaluados y la justificacion de la eleccion. Ver Seccion 13 para los pasos de configuracion del entorno.

### 8.3 Parte No Encontrada en el Catalogo

**Riesgo alto.** El PDF contiene el `Part Number` del vendedor. En el catalogo del sistema, las partes se identifican por `parts.number` o `parts.item_number`, que pueden no coincidir exactamente con el formato del PDF (prefijos, sufijos, asteriscos como en `189-10436*OP10`).

- **Mitigacion:** El `resolvePartId()` debe implementar busqueda fuzzy: primero exacta, luego sin caracteres especiales, luego por subcadena. Si no se encuentra, marcar el item como `price_status = no_part` y requerir seleccion manual de la parte por el operador en la pantalla de revision.
- **Consideracion adicional:** El campo `item_number` en el modelo `Part` parece ser el campo destinado a almacenar el numero del cliente. Verificar que los datos esten cargados correctamente antes de la primera importacion.

### 8.4 Multiples Items por PO

**Riesgo medio.** El modelo actual de `PurchaseOrder` soporta exactamente **un item por PO** (un solo `part_id`, una sola `quantity`, un solo `unit_price`). Si Sumitomo llegara a emitir POs con multiples lineas de producto en una misma hoja, el modelo actual no puede representarlo.

- **Estado actual:** Basado en el ejemplo analizado, cada hoja contiene una sola linea de item. Asumir este formato como norma.
- **Mitigacion:** El `POPdfParserService` debe detectar si una pagina tiene multiples lineas de item y marcarla con `parse_confidence` bajo, generando una advertencia al operador.
- **Consideracion futura:** Si se confirma que existen POs multi-linea, se requeriria un cambio de modelo mayor (agregar tabla `purchase_order_lines`), lo que esta fuera del alcance de esta implementacion.

### 8.5 Duplicados de PO Number

**Riesgo alto.** Si el operador sube el mismo PDF dos veces, o si una PO del PDF ya fue cargada manualmente, se intentaria insertar un `po_number` repetido.

- **Estado del constraint (actualizado 2026-08-08):** la restriccion UNIQUE simple sobre `po_number` fue reemplazada en `2026_05_21_000000` por un indice compuesto `(po_number, deleted_at)` para permitir reutilizar un numero despues de un soft delete. Ese indice **no** bloquea duplicados entre POs activos, porque MySQL admite multiples filas con NULL en una columna de un indice UNIQUE. La migracion `2026_08_08_000000` restablece la garantia con una columna generada `po_number_active` (el numero cuando `deleted_at IS NULL`, NULL cuando esta borrado) mas un indice unico sobre ella. **Conclusion: la base de datos si rebota duplicados activos, pero solo desde esa migracion en adelante — no asumir el UNIQUE simple original.**
- **Mitigacion:** Antes de la pantalla de confirmacion, verificar cuales `po_number_extracted` ya existen en `purchase_orders` **entre los registros activos** (`whereNull('deleted_at')`, o el scope por defecto del modelo). Marcar esos items como `price_status = duplicate` y excluirlos del checkbox de importacion por defecto. El operador puede ver el link a la PO existente.
- **Mitigacion en escritura:** el importador debe capturar `UniqueConstraintViolationException` por fila y reportar esa PO como duplicada en el resumen del batch, en lugar de abortar la importacion completa. Un chequeo previo no basta: entre la verificacion y el insert otra sesion puede tomar el numero.

### 8.6 Formato de Fechas

**Riesgo bajo-medio.** Las fechas en el PDF aparecen en formato MM/DD/YY (`02/20/26`). El modelo espera fechas en formato Y-m-d. El anio de dos digitos requiere interpretacion correcta (2026, no 1926).

- **Mitigacion:** Usar `Carbon::createFromFormat('m/d/y', $dateString)` que interpreta YY como 2000+YY para valores >= 00. Validar que la fecha resultante sea razonable (no antes de 2020, no despues de 2030+5 anios).

### 8.7 Rendimiento con PDFs Grandes

**Riesgo bajo.** Un PDF de 50 paginas con texto denso puede tardar varios segundos en extraerse y parsearse.

- **Mitigacion Fase 1:** Limite de 30 paginas por upload con mensaje claro al usuario.
- **Mitigacion Fase 2:** Usar `php artisan queue` con un Job `ProcessPOImportBatch` para procesar en segundo plano, y usar Livewire polling o Laravel Echo para notificar al usuario cuando este listo.

### 8.8 PDF Compartido entre Multiples POs

El campo `pdf_path` en `purchase_orders` almacena la ruta del PDF. Si un PDF de 10 hojas genera 10 POs, todas apuntarian al mismo archivo fisico. Esto es correcto desde el punto de vista de auditoria, pero implica que si el archivo se elimina, las 10 POs pierden su PDF.

- **Mitigacion:** No permitir eliminar el PDF del batch mientras existan POs importadas que lo referencian. Agregar esta logica al modelo `POImportBatch`.

### 8.9 Seguridad

- Validar que el archivo subido sea realmente un PDF (no solo por extension, sino verificando el magic number `%PDF` al inicio del archivo).
- Limitar el tamanio maximo del upload.
- El procesamiento del PDF ocurre en el servidor con permisos del proceso PHP/Apache, nunca ejecutando el contenido del PDF.

### 8.10 Firma por Hoja: Colision entre Firma del Cliente y Firma Interna de Flexcon

**Riesgo alto.** Cada hoja del PDF de Sumitomo ya contiene una firma autorizada del cliente al pie de la pagina. El sistema actual de firmas de Flexcon (`SignatureService`, `SignatureModal`, `DocumentSignature`) esta disenado para que **un operador interno de Flexcon firme la PO digitalmente** como confirmacion de recepcion y aceptacion. Esto genera una ambiguedad conceptual: al importar masivamente, las POs ya vienen con evidencia de autorizacion del cliente, pero el sistema las trata como si estuviesen sin firma.

- **Mitigacion:** Ver Seccion 10 (Consideracion — Firma por Hoja en PDF) para el analisis completo y la decision de diseno.

### 8.11 Impacto en Manager PO por Importacion Masiva

**Riesgo medio.** El modulo Manager PO (listado `/admin/purchase-orders`, componente `POList`) muestra todas las POs con sus estados y acciones de aprobacion/rechazo. Una importacion de 20 POs en un solo batch puede inundar el listado con POs en estado `pending` o `pending_correction` de forma repentina, sin que el operador del Manager PO tenga contexto de que provienen de un batch importado.

- **Mitigacion:** Ver Seccion 11 (Impacto en Manager PO) para el analisis completo y los cambios propuestos.

---

## 9. Plan de Implementacion Sugerido

Las fases estan ordenadas por dependencia logica. Cada fase es un PR independiente y deployable.

### Fase 1 — Infraestructura de Base de Datos

**Objetivo:** Crear las tablas que soportan el proceso de importacion.

Tareas:
1. Crear migracion para `po_import_batches`.
2. Crear migracion para `po_import_items`.
3. Crear modelos `POImportBatch` y `POImportItem` con relaciones y enums de estado.
4. Crear factories para testing.

Entregable: Migraciones ejecutadas, modelos disponibles, tests de modelos pasan.

### Fase 2 — Configuracion OCR y Servicios de Extraccion y Parseo de PDF

**Objetivo:** Configurar el acceso a Google Cloud Vision API, implementar la arquitectura de drivers OCR, y el servicio de parseo de campos.

Tareas:
1. Crear cuenta de servicio en Google Cloud Console y obtener API key para Cloud Vision API.
2. Instalar el SDK: `composer require google/cloud-vision`.
3. Agregar variables de entorno al `.env` y `.env.example` (ver Seccion 13 — Configuracion de Entorno Requerida).
4. Crear la interfaz `OCRDriverInterface` y el DTO `OCRPageResult`.
5. Implementar `GoogleVisionOCRDriver` con llamada a `files:annotate` endpoint.
6. Implementar `PDFExtractorService` delegando al driver activo, con conteo de paginas previo y manejo de errores de API.
7. Implementar `POPdfParserService` con expresiones regulares calibradas para el formato de Sumitomo. El OCR de Vision produce texto de alta calidad por lo que las regex son las mismas del diseno original, sin necesidad de ser tolerantes a errores tipograficos.
8. Escribir tests unitarios cubriendo: campo presente, campo ausente, formato de fecha, precio con simbolo de dolar, cantidad con unidad "PC", part number con caracteres especiales.
9. Implementar logica de `parse_confidence` basada en cuantos campos obligatorios se extrajeron y en el `confidence` reportado por Vision API por pagina.
10. Implementar `TesseractOCRDriver` como fallback documentado (opcional en esta fase, util para desarrollo offline).

Entregable: `PDFExtractorService::extractPages()` retorna correctamente las 2 paginas del PDF de Sumitomo de ejemplo, con texto reconocido por Vision API. `POPdfParserService::parse()` extrae los campos correctos de cada pagina. Los tests pasan.

### Fase 3 — Servicio de Orquestacion `POImportService`

**Objetivo:** Conectar extraccion, parseo, resolucion de partes y validacion de precios.

Tareas:
1. Implementar `POImportService::processPdf()` completo.
2. Implementar `resolvePartId()` con busqueda exacta y fuzzy.
3. Integrar `POPriceDetectionService::detectPriceForPart()` para validacion por item.
4. Detectar y marcar duplicados de `po_number`.
5. Tests de integracion usando el PDF real: verificar que se crean correctamente el batch y 2 items.
6. Implementar `POImportService::confirmImport()` con transaccion de base de datos.

Entregable: Dado el PDF de ejemplo, el servicio genera un `POImportBatch` con 2 `POImportItem` con los campos correctos y el resultado de validacion de precio.

### Fase 4 — Componente Livewire de Carga

**Objetivo:** Interfaz de usuario para subir el PDF.

Tareas:
1. Crear `app/Livewire/Admin/PurchaseOrders/POImportUpload.php`.
2. Crear vista `resources/views/livewire/admin/purchase-orders/po-import-upload.blade.php`.
3. Agregar ruta en `routes/admin.php`: `GET /purchase-orders/import`.
4. Agregar boton "Importar desde PDF" en `po-list.blade.php`.
5. Validaciones de frontend: tipo PDF, tamanio, feedback visual durante procesamiento.

Entregable: El operador puede subir un PDF y es redirigido a la pantalla de revision.

### Fase 5 — Componente Livewire de Revision e Importacion

**Objetivo:** Interfaz de revision del resultado del parseo y confirmacion de importacion.

Tareas:
1. Crear `app/Livewire/Admin/PurchaseOrders/POImportReview.php`.
2. Crear vista `resources/views/livewire/admin/purchase-orders/po-import-review.blade.php`.
3. Agregar ruta: `GET /purchase-orders/import/{batch}`.
4. Tabla de items con codificacion de colores por `price_status`.
5. Checkboxes de seleccion con "Seleccionar todos los validos" por defecto.
6. Toggle para ver texto crudo de cada pagina.
7. Edicion inline de campos para items con errores (parte no encontrada, fecha invalida).
8. Boton de confirmacion con resumen previo.

Entregable: El operador puede revisar el reporte, seleccionar POs y confirmar la importacion. Las POs aparecen en el listado normal.

### Fase 6 — Pulido, Historial y Observabilidad

**Objetivo:** Hacer el sistema robusto y auditable en produccion.

Tareas:
1. Agregar ruta de historial: `GET /purchase-orders/import/history` con listado de batches pasados.
2. Agregar logs con `Log::info/warning/error` en cada paso del `POImportService`.
3. Implementar el Job `ProcessPOImportBatch` para procesamiento asincrono si el PDF tiene mas de 10 paginas.
4. Agregar tests de feature completos (upload -> review -> confirm -> po_created).
5. Documentar las expresiones regulares del parser en un comentario de bloque con ejemplos.

Entregable: Sistema completo, auditabile, con historial de importaciones y procesamiento asincrono para lotes grandes.

### Fase 7 — Adaptaciones en Manager PO

**Objetivo:** Adaptar el modulo Manager PO para gestionar correctamente las POs importadas desde batch.

Tareas:
1. Agregar columna visual de origen (`manual` / `importada`) en el listado de POList.
2. Agregar filtro por `batch_id` o por origen de importacion en el listado.
3. Agregar accion de aprobacion en lote (bulk approve) para POs provenientes del mismo batch con precio OK.
4. Mostrar en la vista `POShow` el badge de "Importada desde PDF" con link al batch de origen cuando `po_import_item_id` este presente.
5. Ajustar el flujo de firma (`SignatureModal`) para manejar el estado `client_signed` segun la decision de diseno de la Seccion 10.
6. Agregar columna `po_import_item_id` (FK nullable) al modelo `PurchaseOrder` mediante nueva migracion.

Entregable: El Manager PO distingue visualmente las POs importadas de las manuales, permite aprobacion en lote y vincula cada PO a su batch de origen.

---

## Apendice A — Archivos que se Crean (sin modificar los existentes)

| Tipo | Ruta |
|---|---|
| Migracion | `database/migrations/XXXX_create_po_import_batches_table.php` |
| Migracion | `database/migrations/XXXX_create_po_import_items_table.php` |
| Modelo | `app/Models/POImportBatch.php` |
| Modelo | `app/Models/POImportItem.php` |
| Configuracion | `config/ocr.php` |
| Interfaz OCR | `app/Services/OCR/OCRDriverInterface.php` |
| DTO OCR | `app/Services/OCR/OCRPageResult.php` |
| Driver principal | `app/Services/OCR/GoogleVisionOCRDriver.php` |
| Driver alternativo | `app/Services/OCR/TesseractOCRDriver.php` |
| Servicio | `app/Services/PDFExtractorService.php` |
| Servicio | `app/Services/POPdfParserService.php` |
| Servicio | `app/Services/POImportService.php` |
| DTO | `app/Services/POPdfParseResult.php` |
| Livewire | `app/Livewire/Admin/PurchaseOrders/POImportUpload.php` |
| Livewire | `app/Livewire/Admin/PurchaseOrders/POImportReview.php` |
| Vista | `resources/views/livewire/admin/purchase-orders/po-import-upload.blade.php` |
| Vista | `resources/views/livewire/admin/purchase-orders/po-import-review.blade.php` |
| Factory | `database/factories/POImportBatchFactory.php` |
| Factory | `database/factories/POImportItemFactory.php` |

## Apendice B — Archivos que se Modifican

| Archivo | Cambio |
|---|---|
| `routes/admin.php` | Agregar 2-3 rutas de importacion bajo el grupo `role:admin` |
| `resources/views/livewire/admin/purchase-orders/po-list.blade.php` | Agregar boton "Importar desde PDF" y columna de origen en la tabla |
| `app/Models/PurchaseOrder.php` | Agregar relacion `importItem()` (BelongsTo POImportItem, nullable) y columna `po_import_item_id` |
| `app/Livewire/Admin/PurchaseOrders/POList.php` | Agregar filtro por origen y soporte para aprobacion en lote |
| `app/Livewire/Admin/PurchaseOrders/POShow.php` | Mostrar badge de origen e informacion del batch cuando la PO es importada |
| `resources/views/livewire/admin/purchase-orders/po-show.blade.php` | Mostrar seccion de "Importada desde PDF" con link al batch |

## Apendice C — Archivos que NO se Modifican

Los siguientes archivos existentes no requieren cambios en ninguna fase. La arquitectura propuesta es en su mayoria aditiva:

- `app/Services/PurchaseOrderService.php`
- `app/Services/POPriceDetectionService.php`
- `app/Exceptions/InvalidPOPriceException.php`
- `app/Livewire/Admin/PurchaseOrders/POCreate.php`
- `app/Livewire/Admin/PurchaseOrders/POEdit.php`
- `app/Livewire/Admin/SignatureModal.php` (comportamiento interno sin cambios; la logica de firma por hoja se maneja a nivel de estado de la PO)
- `app/Services/SignatureService.php`
- Todas las migraciones existentes de `purchase_orders`

## Apendice D — Dependencias Composer

| Paquete | Comando | Proposito | Estado |
|---|---|---|---|
| `google/cloud-vision` | `composer require google/cloud-vision` | Driver OCR principal (Google Vision API) | Nuevo — requerido |
| `thiagoalessio/tesseract_ocr` | `composer require thiagoalessio/tesseract_ocr` | Driver OCR alternativo (Tesseract local) | Nuevo — opcional (fallback offline) |
| `setasign/fpdi` | Ya instalado | Separacion de paginas PDF en confirmImport | Existente — sin cambios |
| `setasign/fpdf` | Ya instalado | Generacion de PDFs individuales por pagina | Existente — sin cambios |
| `smalot/pdfparser` | N/A | DESCARTADO — no funciona con PDFs escaneados | No instalar |

---

*Fin del documento base. Las secciones 10 y 11 amplian el analisis con los temas de firma por hoja y Manager PO.*

---

## 10. Consideracion — Firma por Hoja en PDF

### 10.1 Descripcion del Hallazgo

Al revisar el PDF de ejemplo (`Flexcon PO 50297 - 50298.pdf`) se confirma que **cada pagina del documento ya contiene una firma autorizada de Sumitomo Electric** al pie. Esta firma en el PDF representa la autorizacion formal del cliente para la orden de compra. No es una firma electronica criptografica sino una imagen de firma impresa en el documento, incluida por el sistema ERP de Sumitomo al generar el PDF.

Esto introduce una distincion importante que el analisis anterior no consideraba:

- **Firma del cliente (en el PDF):** Autorizacion de Sumitomo Electric. Certifica que la PO es valida y fue emitida oficialmente. Esta presente en cada hoja del PDF importado.
- **Firma interna de Flexcon:** Autorizacion del operador/supervisor de Flexcon de que la PO fue recibida, revisada y aceptada. Es la firma que genera el `SignatureModal` y se almacena en `document_signatures`.

Estas dos firmas tienen propositos distintos y **ambas deben coexistir**.

### 10.2 Arquitectura Actual del Sistema de Firmas

El sistema de firmas de Flexcon funciona de la siguiente manera:

**Tabla `document_signatures`:**

| Campo | Descripcion |
|---|---|
| `purchase_order_id` | FK a la PO firmada |
| `user_id` | Operador interno de Flexcon que firmo |
| `signature_path` | Imagen PNG de la firma del operador (en `storage/public/signatures/`) |
| `signed_pdf_path` | PDF con la firma del operador superpuesta (en `storage/public/purchase-orders/signed/`) |
| `signed_at` | Timestamp de la firma interna |
| `ip_address` | IP desde donde se firmo |

**Flujo actual:**
1. El operador abre una PO en `POShow`.
2. Hace clic en "Firmar documento" (disparado por `SignatureModal`).
3. Dibuja o usa su firma guardada.
4. `SignatureService::signDocument()` toma el PDF de la PO (campo `pdf_path`) y genera un nuevo PDF con la firma del operador superpuesta **solo en la ultima pagina**.
5. Se crea un registro en `document_signatures` con `signed_pdf_path`.

**Problema critico con importacion masiva:** `SignatureService::generateSignedPdf()` itera todas las paginas del PDF original y agrega la firma solo en la ultima. Si el `pdf_path` de la PO importada apunta al **PDF multi-hoja del batch** (que contiene N paginas de N POs distintas), el PDF "firmado" resultante contendra todas las POs del batch con la firma solo en la ultima pagina. Esto es incorrecto tanto conceptualmente como legalmente.

### 10.3 Impacto en el Flujo de Importacion

#### Escenario A — PDF compartido (estado actual del diseno)

En el plan original (seccion 7, Paso 5), se propuso que el `pdf_path` de cada PO importada apunte al mismo archivo fisico del batch (el PDF multi-hoja). Esto funciona para visualizacion pero **rompe el flujo de firma** porque `SignatureService` processaria el PDF completo del batch y produciria un documento firmado que incluye POs ajenas a la que se esta firmando.

#### Escenario B — PDF por pagina (ajuste necesario)

Durante la importacion, el `POImportService` debe extraer cada pagina del PDF del batch como un **PDF individual de una sola hoja** y asignarlo como `pdf_path` de la PO correspondiente. Esto resuelve el problema de la firma y tambien es correcto semanticamente: cada PO tiene su propio PDF.

**Este es el diseno recomendado.** Implica que la implementacion de `POImportService::confirmImport()` debe incluir un paso adicional de separacion de paginas del PDF antes de crear cada `PurchaseOrder`.

### 10.4 La Firma del Cliente como Evidencia

Dado que el PDF de cada pagina ya contiene la imagen de la firma autorizada de Sumitomo (visible al pie), Flexcon debe decidir si necesita **capturar o registrar** esa firma como metadato estructurado del sistema, o si es suficiente conservar el PDF de la pagina como evidencia.

**Recomendacion:** No crear un campo especifico para la firma del cliente en la base de datos en esta fase. El PDF de la pagina almacenado en `pdf_path` ya es la evidencia de la firma del cliente. Lo que si debe documentarse es el **estado de firma dual** de cada PO:

| Estado | Significado |
|---|---|
| Sin firma interna | PO importada, PDF del cliente presente, firma de Flexcon pendiente |
| Con firma interna | PO importada y firmada por operador de Flexcon (flujo normal posterior a importacion) |

No se requiere un nuevo estado en el modelo `PurchaseOrder` para esto. El metodo `isSigned()` del modelo ya retorna `true` si existe al menos un registro en `document_signatures`, lo que equivale a "Flexcon firmo la PO".

### 10.5 Flujo de Firma para POs Importadas

El flujo de firma para POs importadas debe ser **identico al flujo actual** una vez que la PO ya existe como registro independiente con su propio PDF de una sola hoja:

```
PDF Multi-Hoja (N paginas)
          |
          v (POImportService::confirmImport)
 Separacion por pagina -> N PDFs de 1 pagina
          |
          v
 Cada PDF se guarda como pdf_path de su PO
          |
          v
 POs creadas en estado 'pending' o 'pending_correction'
          |
          v (flujo normal existente, sin cambios)
 Operador abre POShow -> SignatureModal -> firma interna
          |
          v
 DocumentSignature creado con signed_pdf_path correcto (1 hoja)
```

### 10.6 Herramienta para Separacion de Paginas

La libreria `smalot/pdfparser` permite leer paginas pero **no genera PDFs**. Para separar el PDF en paginas individuales se puede usar `setasign/fpdi`, que ya esta instalada en el proyecto (confirmado por su uso en `SignatureService::generateSignedPdf()`).

El metodo de separacion seria:

```
// Pseudocodigo — no codigo de implementacion
foreach pagina en PDF_batch:
    crear nuevo FPDI con 1 sola pagina importada
    guardar en storage/public/purchase-orders/{po_number}.pdf
    asignar ruta como pdf_path de la PO
```

Esto no requiere instalar ninguna dependencia adicional.

### 10.7 Cambios Adicionales al Diseno

La separacion de paginas introduce una nueva responsabilidad en `POImportService::confirmImport()` y un ajuste menor en la tabla de `po_import_items`:

**Nuevo campo recomendado en `po_import_items`:**

| Campo | Tipo | Descripcion |
|---|---|---|
| `page_pdf_path` | string NULL | Ruta al PDF de una sola hoja extraida del batch para esta PO. Se llena al confirmar la importacion. |

Este campo permite rastrear que pagina fisica del batch corresponde a cada item, y sirve como referencia de auditoria incluso si el PDF del batch original se elimina en el futuro.

---

## 11. Impacto en Manager PO

### 11.1 Que es el Manager PO

El "Manager PO" es el modulo de gestion de Purchase Orders accesible en la ruta `/admin/purchase-orders`. Esta compuesto por:

| Componente | Archivo | Responsabilidad |
|---|---|---|
| `POList` | `app/Livewire/Admin/PurchaseOrders/POList.php` | Listado paginado con filtros por estado, busqueda, ordenamiento y acciones de aprobacion/rechazo/eliminacion |
| Vista de lista | `resources/views/livewire/admin/purchase-orders/po-list.blade.php` | Tabla con badges de estado y botones de accion por fila |
| `POShow` | `app/Livewire/Admin/PurchaseOrders/POShow.php` | Vista de detalle con validacion de precio en tiempo real y botones Aprobar/Rechazar |
| `POCreate` | `app/Livewire/Admin/PurchaseOrders/POCreate.php` | Formulario de creacion manual de una PO |
| `POEdit` | `app/Livewire/Admin/PurchaseOrders/POEdit.php` | Edicion de una PO existente |

El modulo esta restringido al rol `admin` (ver `routes/admin.php`, grupo `role:admin`).

### 11.2 Estado Actual del Flujo de Aprobacion en Manager PO

El flujo actual en `POList` y `POShow` funciona de la siguiente manera:

```
PO en estado 'pending'
        |
        v
Operador hace clic en "Aprobar" (desde POList o POShow)
        |
        v
PurchaseOrderService::approveAndCreateWO()
        |
        +-- validatePrice() -> precio OK?
        |       Si: status = 'approved', se crea WorkOrder
        |       No: status = 'pending_correction'
        |
        v
PO en estado 'approved' o 'pending_correction'
```

Los contadores en el listado (`POList::render()`) muestran:
- `$totalPOs` — total de POs en el sistema
- `$pendingPOs` — POs en estado `pending`
- `$approvedPOs` — POs en estado `approved`
- `$pendingCorrectionPOs` — POs en estado `pending_correction`

### 11.3 Como Llegan las POs Importadas al Manager PO

Cuando `POImportService::confirmImport()` crea los registros en `purchase_orders`, estas POs aparecen **inmediatamente** en el listado de Manager PO porque la consulta en `POList::render()` hace `PurchaseOrder::with('part')->search()->filterByStatus()->paginate()` sin ningun filtro por origen.

Esto significa que:

1. Una importacion de 15 POs agrega 15 filas nuevas al listado de golpe, todas en estado `pending` (o `pending_correction` para las de precio incorrecto).
2. El operador del Manager PO no tiene ningun indicador visual de que esas POs son importadas desde un batch PDF vs. creadas manualmente.
3. No existe actualmente una forma de aprobar multiples POs en un solo clic (no hay bulk action).

### 11.4 Problema: Ausencia de Contexto de Origen

El operador del Manager PO que ve 15 nuevas POs en estado `pending` puede no saber:
- Si provienen de un batch importado (y por tanto ya fueron revisadas en la pantalla de `POImportReview`).
- Si el precio fue validado durante la importacion y el operador de importacion ya lo confirmo.
- A cual batch pertenecen si necesita consultar el PDF original.

### 11.5 Cambios Necesarios en Manager PO

#### 11.5.1 Nuevo Campo en `purchase_orders`: `po_import_item_id`

Para vincular cada PO importada con su item del batch, se agrega una FK nullable:

```
// Nueva migracion: add_po_import_item_id_to_purchase_orders
$table->foreignId('po_import_item_id')
      ->nullable()
      ->constrained('po_import_items')
      ->nullOnDelete();
```

Con este campo, el modelo `PurchaseOrder` puede exponer:

```php
// Nueva relacion en PurchaseOrder.php
public function importItem(): BelongsTo
{
    return $this->belongsTo(POImportItem::class, 'po_import_item_id');
}

// Nuevo accessor
public function isImported(): bool
{
    return $this->po_import_item_id !== null;
}
```

#### 11.5.2 Indicador Visual de Origen en POList

En la vista de lista, agregar una columna o badge que distinga el origen:

- **Manual:** Sin indicador adicional (comportamiento actual).
- **Importada:** Badge gris/azul con texto "PDF" y link al batch de origen (`/admin/purchase-orders/import/{batch_id}`).

#### 11.5.3 Filtro por Origen en POList

Agregar al componente `POList` un filtro adicional:

```
$filterOrigin: 'all' | 'manual' | 'imported'
```

El scope correspondiente en `PurchaseOrder`:

```php
// Nuevo scope en PurchaseOrder.php
public function scopeFilterByOrigin(Builder $query, ?string $origin): Builder
{
    return match($origin) {
        'manual'   => $query->whereNull('po_import_item_id'),
        'imported' => $query->whereNotNull('po_import_item_id'),
        default    => $query,
    };
}
```

#### 11.5.4 Aprobacion en Lote (Bulk Approve)

Para evitar que el operador tenga que aprobar 15 POs una por una, agregar una accion de aprobacion en lote accesible desde `POList`:

- Checkbox de seleccion en cada fila.
- Boton "Aprobar seleccionadas" que llama a `PurchaseOrderService::approveAndCreateWO()` en un loop dentro de una transaccion.
- Solo disponible para POs en estado `pending`.
- El resultado de cada aprobacion se muestra en un resumen: "12 aprobadas, 3 marcadas como pending_correction".

Esta funcionalidad es especialmente util para lotes importados donde el precio ya fue validado durante la importacion y el operador tiene alta confianza en los datos.

#### 11.5.5 Vista POShow para POs Importadas

En `POShow`, cuando `$purchaseOrder->isImported()` retorna `true`, mostrar una seccion adicional:

```
[Importada desde PDF]
Batch: #5 — Flexcon PO 50297 - 50298.pdf
Pagina del PDF: 1
Confianza del parser: 95%
Ver batch completo -> [link]
Ver texto extraido -> [toggle]
```

Esto permite al operador que revisa la PO tener trazabilidad completa del origen del dato.

### 11.6 Relacion del Manager PO con el Modulo de Firma

El modulo `SignatureModal` se invoca desde la vista de detalle de POs (o desde el listado). Con los cambios de separacion de paginas PDF (Seccion 10.5), el flujo de firma en Manager PO **no requiere cambios funcionales**. El `SignatureService::generateSignedPdf()` recibira un `pdf_path` de una sola hoja (en lugar del PDF multi-hoja del batch), y generara el PDF firmado correctamente.

Lo unico que cambia es que el administrador que ve la PO en `POShow` tendra acceso a dos documentos:
1. El PDF original de la hoja (con la firma del cliente ya visible en el documento).
2. El PDF firmado de Flexcon (con la firma del operador superpuesta), generado por `SignatureService`.

Ambos PDF se pueden mostrar en la seccion "Documento PDF" de `po-show.blade.php` mediante dos botones separados: "Ver PO del cliente" y "Ver PO firmada por Flexcon".

### 11.7 Relacion con SentList (Lista de Envio)

Las POs importadas participan en el modulo `SentList` de la misma forma que las POs manuales, a traves de la tabla pivot `sent_list_purchase_orders`. No se requieren cambios en `SentList`, `sent_list_purchase_orders` ni en sus migraciones. La unica condicion para que una PO pueda incluirse en una `SentList` es que tenga una `WorkOrder` activa, lo cual ocurre al aprobarla mediante el flujo normal de `PurchaseOrderService::approveAndCreateWO()`.

### 11.8 Vista `pos-list.blade.php` — Nota sobre Modulo Separado

Durante la investigacion se identifico el archivo `resources/views/livewire/admin/pos/pos-list.blade.php`. Esta es una vista **diferente y separada** del Manager PO principal, aparentemente un componente Volt (`PosList`) accesible posiblemente desde otra ruta de navegacion. Su contenido actual es minimo (solo el input de busqueda y selector de `perPage`, sin tabla de datos), lo que sugiere que es un modulo en desarrollo temprano o una vista alternativa de lista de POs. Este componente **no se ve impactado** directamente por la importacion masiva en esta fase, ya que no tiene la logica de aprobacion ni los contadores de estado que si tiene `POList`. Se deja como nota para el equipo para evaluar si ambas vistas deben unificarse o si tienen propositos distintos.

### 11.9 Resumen de Cambios en Manager PO

| Componente | Tipo de Cambio | Fase |
|---|---|---|
| Migracion `add_po_import_item_id_to_purchase_orders` | Nueva migracion (campo FK nullable) | Fase 1 (ampliar) |
| `PurchaseOrder::importItem()` | Nueva relacion BelongsTo | Fase 1 (ampliar) |
| `PurchaseOrder::isImported()` | Nuevo metodo accessor | Fase 1 (ampliar) |
| `PurchaseOrder::scopeFilterByOrigin()` | Nuevo scope | Fase 7 |
| `POList` — filtro por origen | Nuevo filtro `$filterOrigin` | Fase 7 |
| `POList` — bulk approve | Nueva accion de seleccion multiple | Fase 7 |
| `po-list.blade.php` — badge de origen | Cambio visual en tabla | Fase 7 |
| `po-show.blade.php` — seccion batch de origen | Nueva seccion informativa | Fase 7 |
| `po-show.blade.php` — dos botones de PDF | Mostrar PDF cliente y PDF firmado | Fase 7 |

---

## 12. Analisis de Escenarios OCR para PDFs 100% Escaneados

**Contexto confirmado:** Todos los PDFs recibidos de Sumitomo Electric son documentos escaneados. No existe capa de texto en los archivos. `smalot/pdfparser` queda completamente descartado. Se requiere un motor OCR real como capa de extraccion.

---

### 12.1 La Naturaleza del Problema

Un PDF escaneado es internamente una o varias imagenes rasterizadas (JPEG/PNG/TIFF) empaquetadas dentro del contenedor PDF. No existe capa de texto codificado. El motor OCR debe "leer" la imagen como lo haria un ser humano y convertir los pixeles en caracteres.

```
PDF Escaneado de Sumitomo
      |
      [Contenedor PDF]
           └─ [Imagen JPEG/PNG — fotografia del documento impreso]
                  (mapa de pixeles, sin texto codificado)
                  "PO Number: 50297"  <- existe VISUALMENTE pero no como dato
                  "Unit Price: $0.2352"   <- el OCR debe reconocerlo
```

**Implicaciones directas para el diseno:**
1. El primer paso del pipeline NO puede ser parsear texto — debe ser reconocer texto desde imagen.
2. La precision del OCR determina directamente si los numeros criticos (precios de 4 decimales, part numbers con caracteres especiales) se leen correctamente.
3. La conversion del PDF a imagen (si el motor no acepta PDF nativo) es una dependencia adicional que puede complicar el entorno Windows/XAMPP.

---

### 12.2 Escenario A — Tesseract OCR Local

**Descripcion:** Motor OCR open source mantenido por Google. Funciona sobre imagenes locales. Completamente gratuito y sin dependencias de red.

**Pipeline tecnico:**
```
PDF escaneado
      |
      v (Paso 1: conversion PDF -> imagen)
      |  Requiere: Ghostscript O ImageMagick + Ghostscript
      |  Comando: gs -dNOPAUSE -r300 -sDEVICE=png16m -sOutputFile=pagina_%d.png input.pdf
      v
Imagen PNG de 300 DPI por pagina
      |
      v (Paso 2: OCR sobre imagen)
      |  Requiere: tesseract.exe en PATH del sistema
      |  PHP: thiagoalessio/tesseract_ocr
      v
Texto plano de la pagina
      |
      v (Paso 3: parseo con regex — POPdfParserService sin cambios)
      v
Campos de la PO
```

**Instalacion en Windows/XAMPP — 3 componentes separados:**
1. Tesseract: instalador de UB Mannheim (`https://github.com/UB-Mannheim/tesseract/wiki`) → instalar en `C:\Program Files\Tesseract-OCR\` → agregar al PATH del sistema
2. Ghostscript: instalador oficial (`https://www.ghostscript.com/releases/`) → instalar en `C:\Program Files\gs\` → agregar al PATH
3. PHP wrapper: `composer require thiagoalessio/tesseract_ocr`

**Evaluacion de criterios:**

| Criterio | Evaluacion |
|---|---|
| Precision en documentos escaneados calidad media | 90-95% en caracteres normales; baja a 80-85% en numeros de 4 decimales con fuente pequeña |
| Precision en numeros criticos (`$0.2352` vs `$0.2852`) | RIESGO ALTO — el "3" y el "8" son altamente confundibles por Tesseract en texto escaneado |
| Instalacion en XAMPP/Windows | COMPLEJA — 3 componentes independientes, cada uno requiere PATH, posibles conflictos de version |
| Mantenimiento | MEDIO — binarios del servidor de produccion deben estar sincronizados con desarrollo |
| Costo | $0.00 siempre |
| Funcionamiento offline | Si, completo |
| Requiere internet | No |

**Veredicto:** No recomendado como opcion principal. La confusion entre digitos similares (3/8, 0/6, 1/7) en numeros decimales de 4 cifras es inaceptable para un sistema donde un precio mal leido puede generar discrepancias financieras reales. La complejidad de instalacion en Windows con 3 binarios separados agrega friccion operacional significativa.

---

### 12.3 Escenario B — Google Cloud Vision API

**Descripcion:** Servicio de reconocimiento optico de Google. Acepta PDFs directamente (sin conversion a imagen previa). Precision de nivel superior comparado con Tesseract, especialmente para numeros y caracteres especiales.

**Pipeline tecnico:**
```
PDF escaneado
      |
      v (Paso unico: llamada a Vision API)
      |  El PDF se envia en base64 directamente
      |  Vision API internamente convierte a imagen y aplica OCR de grado commercial
      |  Feature: DOCUMENT_TEXT_DETECTION (optimizado para documentos con texto denso)
      |  Retorna: texto completo del documento, organizado por pagina
      v
Texto plano por pagina (confianza reportada por Vision)
      |
      v (Parseo con regex — POPdfParserService sin cambios)
      v
Campos de la PO
```

**Llamada REST (sin SDK completo — solo Laravel HTTP client):**
```
POST https://vision.googleapis.com/v1/files:annotate?key={API_KEY}

Body:
{
  "requests": [{
    "inputConfig": {
      "content": "{base64_del_pdf}",
      "mimeType": "application/pdf"
    },
    "features": [{"type": "DOCUMENT_TEXT_DETECTION"}],
    "pages": [1, 2, 3, ...]
  }]
}

Respuesta:
{
  "responses": [
    { "fullTextAnnotation": { "text": "Texto de pagina 1..." } },
    { "fullTextAnnotation": { "text": "Texto de pagina 2..." } }
  ]
}
```

**Evaluacion de criterios:**

| Criterio | Evaluacion |
|---|---|
| Precision en documentos escaneados calidad media | 97-99% en caracteres normales |
| Precision en numeros criticos (`$0.2352` vs `$0.2852`) | MUY ALTA — Vision usa modelos neuronales entrenados especificamente para documentos financieros |
| Instalacion en XAMPP/Windows | MINIMA — solo `composer require google/cloud-vision` + API key en `.env` |
| Mantenimiento | BAJO — no hay binarios en el servidor, solo credenciales |
| Costo mensual (volumen estimado: ~100 paginas/mes) | **$0.00** — tier gratuito: 1,000 unidades/mes gratis |
| Costo mensual (volumen alto: 1,000 paginas/mes) | **$1.50 USD** |
| Requiere internet | Si — dependencia de conectividad |
| Funcionamiento offline | No |

**Calculo de costo real para este proyecto:**
- Estimacion conservadora: 3 PDFs/semana x 5 paginas promedio = 60 paginas/mes
- Estimacion alta: 10 PDFs/semana x 8 paginas = 320 paginas/mes
- Tier gratuito de Vision API (DOCUMENT_TEXT_DETECTION): **1,000 paginas/mes gratis**
- Costo esperado: **$0.00 USD/mes** en ambos escenarios
- Solo si el volumen superara 1,000 paginas/mes comenzaria a cobrar, y seria a $1.50/1000

**Veredicto:** RECOMENDADO COMO OPCION PRINCIPAL. Cero friccion operacional en XAMPP/Windows, precision superior para numeros decimales criticos, costo efectivamente gratuito para el volumen del proyecto.

---

### 12.4 Escenario C — AWS Textract

**Descripcion:** Servicio de Amazon especializado en extraccion de formularios y tablas. Puede identificar automaticamente pares clave-valor en documentos estructurados (ej: detecta "Purchase Order:" como clave y "50297" como valor).

**Evaluacion de criterios:**

| Criterio | Evaluacion |
|---|---|
| Precision en documentos escaneados | 97-99%, comparable a Vision |
| Capacidad de extraccion de formularios | MUY ALTA — AnalyzeDocument detecta campos automaticamente |
| Instalacion en XAMPP/Windows | Baja friccion — `composer require aws/aws-sdk-php` + credenciales (ya presentes en `.env.example`) |
| Costo (DetectDocumentText — solo texto) | $1.50 USD / 1,000 paginas (sin tier gratuito relevante para PDFs) |
| Costo (AnalyzeDocument — formularios/tablas) | $15.00 USD / 1,000 paginas |
| Costo mensual estimado (100 paginas/mes, texto basico) | **$0.15 USD/mes** |
| Requiere internet | Si |

**Por que no se elige como opcion principal:**
1. El beneficio principal de Textract es la extraccion automatica de formularios (identificar pares clave-valor sin regex). Sin embargo, este proyecto YA tiene un `POPdfParserService` con regex calibradas para el formato fijo de Sumitomo. La extraccion automatica de formularios no agrega valor diferencial cuando el formato es siempre conocido y fijo.
2. El tier gratuito de Textract para procesamiento de documentos es de solo 1,000 paginas en el primer mes (no recurrente). A partir del segundo mes, ya hay costo.
3. Google Vision ofrece 1,000 unidades/mes gratis de forma recurrente permanente.
4. Si el proyecto ya tiene credenciales AWS y prefiere centralizar en un proveedor, Textract es una alternativa valida con costo minimo ($0.15/mes para el volumen estimado).

**Veredicto:** Segunda opcion aceptable, especialmente si el proyecto ya usa AWS para otros servicios (`.env.example` ya tiene `AWS_ACCESS_KEY_ID`). El costo es bajo pero no gratuito de forma recurrente como Vision.

---

### 12.5 Escenario D — Pre-procesamiento con Imagick/Ghostscript antes de OCR

**Descripcion:** Antes de enviar al motor OCR, aplicar transformaciones a la imagen para mejorar la calidad del scan: deskew (corregir inclinacion), aumento de contraste, binarizacion (blanco/negro), eliminacion de ruido.

**Cuando tiene sentido:**
- Documentos escaneados con inclinacion visible (la pagina no esta perfectamente recta).
- Scans de baja calidad (fotocopias de fotocopias, manchas, sombras).
- Documentos con fondos grises o colores que reducen el contraste del texto.

**Cuando NO tiene sentido (este proyecto):**
- Google Cloud Vision API aplica internamente sus propios algoritmos de preprocesamiento y correccion de imagen antes de ejecutar OCR. Enviar la imagen preprocesada externamente puede interferir con los algoritmos de Vision y reducir la precision.
- El pre-procesamiento solo agrega valor claro cuando se usa Tesseract, que no tiene preprocesamiento interno sofisticado.
- Requiere Ghostscript + ImageMagick instalados en Windows, sumando dos dependencias de sistema operativo.

**Veredicto:** No aplica si se usa Google Cloud Vision. Aplicable como mejora opcional si se usa Tesseract y los scans son de muy baja calidad.

---

### 12.6 Escenario E — Template Matching con Coordenadas Fijas

**Descripcion:** Dado que el formato del PDF de Sumitomo es siempre el mismo (misma estructura, mismos campos en las mismas posiciones), se podria definir las coordenadas exactas de cada campo en la pagina (en pixeles o porcentaje de la imagen) y extraer solo esas regiones antes de aplicar OCR. Esto reduce el area procesada y elimina la necesidad de parsear texto libre.

**Evaluacion tecnica:**
- **Ventaja teorica:** Si se corta exactamente la region del campo `Unit Price`, el OCR solo ve "0.2352" en lugar de todo el texto de la pagina. Menos texto = menos oportunidades de confusion.
- **Problema practico:** El template matching por coordenadas es fragil ante variaciones de escaneo. Si el operador coloca el papel 2mm torcido en el scanner, las coordenadas no coinciden exactamente. Los documentos escaneados por distintos equipos tienen resoluciones y margenes ligeramente distintos.
- **Mejor alternativa:** Usar Vision API que retorna el texto completo con alta precision, y luego aplicar regex muy especificas que "anclan" el patron al contexto (`Unit Price.*?\$(\d+\.\d{4})`). Esto es equivalente al beneficio de template matching pero sin la fragilidad de coordenadas pixeles.

**Veredicto:** No recomendado como estrategia principal. Sustituido eficazmente por la combinacion de Vision API (alta precision) + regex contextuales en `POPdfParserService` (extraccion anclada al campo correcto).

---

### 12.7 Recomendacion Final: Escenario B (Google Cloud Vision) + Patron Strategy

**Decision:** Implementar **Google Cloud Vision API** como motor OCR principal, encapsulado detras de una interfaz `OCRDriverInterface` que permita sustituir el motor sin modificar el resto de la arquitectura.

**Justificacion tecnica y operacional:**

| Factor | Tesseract (A) | Google Vision (B) | AWS Textract (C) |
|---|---|---|---|
| Precision en numeros decimales criticos | Media (riesgo real de errores) | Alta (99%+) | Alta (99%+) |
| Friccion de instalacion en XAMPP/Windows | Alta (3 binarios) | Ninguna (solo API key) | Baja (SDK PHP) |
| Costo mensual real para este volumen | $0.00 | **$0.00** | ~$0.15 |
| Tier gratuito recurrente | N/A | 1,000 unidades/mes permanente | Solo primer mes |
| Complejidad de integracion PHP | Media | Baja | Media |
| Disponibilidad offline | Si | No | No |
| Riesgo de confusion 3/8, 0/6 en precios | ALTO | BAJO | BAJO |

**La precision es el factor decisivo.** Un precio leido como `0.2852` en lugar de `0.2352` introduce una discrepancia de $0.05 por pieza — en un pedido de 10,000 piezas esto son $500 USD de discrepancia falsa que requeriria investigacion manual. Google Vision elimina este riesgo con su precision documentada del 99%+ en documentos impresos.

**Arquitectura de implementacion (patron Strategy):**

```
PDFExtractorService
        |
        | usa
        v
OCRDriverInterface
        |
        +--- GoogleVisionOCRDriver  <-- ACTIVO (default en produccion)
        |
        +--- TesseractOCRDriver     <-- Fallback documentado (desarrollo offline)
```

La variable `PDF_OCR_ENGINE=google_vision` en `.env` determina que driver se instancia via el Service Container de Laravel.

---

### 12.8 Flujo Tecnico Completo: PDF Escaneado → Campos PO

```
1. CARGA
   Operador sube PDF escaneado en POImportUpload (Livewire)
         |
         v
   PDF guardado temporalmente en storage/app/temp/po-imports/{uuid}.pdf

2. LLAMADA OCR (PDFExtractorService -> GoogleVisionOCRDriver)
   PDF en base64 enviado a:
   POST https://vision.googleapis.com/v1/files:annotate?key={API_KEY}
   Body: { inputConfig: {content: base64, mimeType: "application/pdf"},
           features: [{type: "DOCUMENT_TEXT_DETECTION"}] }
         |
         v
   Vision API retorna array de respuestas, una por pagina:
   [
     { fullTextAnnotation: { text: "SUMITOMO ELECTRIC\nPO Number 50297\n..." } },
     { fullTextAnnotation: { text: "SUMITOMO ELECTRIC\nPO Number 50298\n..." } }
   ]
         |
         v
   PDFExtractorService retorna array de OCRPageResult:
   [
     { text: "..texto pagina 1..", pageNumber: 1, method: "google_vision", confidence: 98 },
     { text: "..texto pagina 2..", pageNumber: 2, method: "google_vision", confidence: 97 }
   ]

3. PARSEO (POPdfParserService::parse($pageText))
   Para cada OCRPageResult con confidence >= umbral configurable:

   Regex principales aplicadas al texto de la pagina:
   - PO Number:    /P\.?O\.?\s*(?:Number|#|No\.?)\s*[:\s]\s*(\d{4,6})/i
   - WO Number:    /W\.?O\.?\s*[:\s]\s*(\d{6,8})/i
   - Part Number:  /(\d{3}-\d{5}\*OP\d{2})/  (patron fijo Sumitomo)
   - Quantity:     /(\d[\d,]+)\s*PC/i
   - Unit Price:   /\$\s*(\d+\.\d{4})/
   - Amount:       /(?:Amount|Total)\s*[:\s]\s*\$\s*([\d,]+\.\d{2})/i
   - Due Date:     /(?:Due\s+Date|Delivery)\s*[:\s]\s*(\d{2}\/\d{2}\/\d{2})/i
   - Branch:       /Branch\s*(?:Plant)?\s*[:\s]\s*(\d{3})/i

   Validacion cruzada: quantity * unit_price ~= amount (tolerancia 0.01)

   Retorna POPdfParseResult con todos los campos y parse_confidence calculado

4. RESOLUCION DE PARTE (POImportService::resolvePartId)
   part_number_extraido = "189-10436*OP10"
   Busqueda en DB:
     1. Exacta: WHERE number = '189-10436*OP10'
     2. Sin asterisco: WHERE number LIKE '189-10436%'
     3. Por item_number: WHERE item_number = '189-10436*OP10'
   Si no encuentra: price_status = 'no_part', requiere seleccion manual

5. VALIDACION DE PRECIO (POPriceDetectionService::detectPriceForPart)
   Igual que el flujo manual existente.
   Sin cambios en el servicio.

6. PERSISTENCIA
   Se crea POImportBatch + POImportItems con todos los campos incluido
   extraction_method = 'google_vision'

7. REVISION
   Operador ve tabla en POImportReview con badge "OCR" en cada fila
   Puede ver el texto crudo reconocido por Vision para verificar

8. CONFIRMACION
   POImportService::confirmImport() separa paginas del PDF con FPDI
   (ya instalado) y crea PurchaseOrder por cada item seleccionado
```

---

### 12.9 Manejo de Multiples Paginas

Google Vision API acepta PDFs multipagina en una sola llamada y retorna los resultados en el mismo array de respuesta, en orden de pagina. No se requieren llamadas separadas por pagina.

**Limite de paginas por llamada:** La API de Vision para PDFs acepta hasta 5 paginas por llamada en modo sincrono (`files:annotate`). Para PDFs con mas de 5 paginas se debe usar el modo asincrono (`files:asyncBatchAnnotate` con output a Google Cloud Storage), lo cual es innecesario para el volumen esperado.

**Estrategia para el volumen de este proyecto (tipicamente 2-10 paginas por PDF):**
- Usar siempre la llamada sincrona `files:annotate`.
- El limite de 5 paginas del modo sincrono es suficiente para la gran mayoria de los batches.
- Si llega un PDF de mas de 5 paginas: dividir en llamadas de 5 paginas cada una, concatenar resultados. Implementar este batching dentro de `GoogleVisionOCRDriver`.

---

### 12.10 Estrategia de Parseo Post-OCR

El texto que retorna Google Vision para un documento como el de Sumitomo se ve aproximadamente asi:

```
SUMITOMO ELECTRIC INTERCONNECT PRODUCTS, INC.
5905 Sherborn Drive San Marcos CA 92069
Vendor Number: 8694

Purchase Order
PO Number: 50297
Date: 01/15/26
Branch Plant: 300

Item No.  Description     Part Number (Vendor)    Qty    Unit Price  Amount
STS H-MB-401T  WIRE HARNESS  189-10436*OP10  1000 PC  $0.2352  $235.20

WO: 2032495
Due Date: 02/20/26
```

Las regex del `POPdfParserService` deben ser **contextuales** (ancladas a la etiqueta del campo) para tolerar variaciones menores de layout que Vision puede introducir en el orden del texto extraido:

```
// Correcto — anclado al contexto
/PO\s+Number\s*[:\s]+(\d{4,6})/i   -> captura "50297" del contexto "PO Number: 50297"

// Incorrecto — busqueda de patron numerico libre (propenso a falsos positivos)
/\b(\d{5})\b/  -> podria capturar el zip code "92069" o el vendor "8694"
```

La validacion cruzada `quantity * unit_price = amount` actua como verificacion de integridad: si el resultado no coincide dentro de una tolerancia de $0.01, se marca con `parse_confidence` reducido y el operador debe verificar manualmente.

---

### 12.11 Impacto Actualizado en el Plan de Implementacion

| Fase | Tarea | Cambio vs plan original |
|---|---|---|
| **Fase 2** | Instalacion de libreria PDF | CAMBIO: `composer require google/cloud-vision` en lugar de `smalot/pdfparser` |
| **Fase 2** | Crear `OCRDriverInterface` y `OCRPageResult` | NUEVO — arquitectura de drivers |
| **Fase 2** | Implementar `GoogleVisionOCRDriver` | NUEVO — reemplaza el rol de `smalot/pdfparser` |
| **Fase 2** | Implementar `TesseractOCRDriver` | NUEVO — fallback documentado (opcional) |
| **Fase 2** | Configurar `.env` con credenciales Vision API | NUEVO — ver Seccion 13 |
| **Fase 2** | `PDFExtractorService` | CAMBIO: delega a `OCRDriverInterface`, no llama a `smalot` directamente |
| **Fase 2** | `POPdfParserService` | SIN CAMBIOS en logica de regex; el texto de entrada es de mayor calidad que con Tesseract |
| **Fase 2** | Tests de `PDFExtractorService` | CAMBIO: testear con mock de la API de Vision (no llamada real en tests unitarios) |
| **Fase 4** | `POImportUpload` — UI | CAMBIO MENOR: eliminar advertencia "solo PDFs con texto seleccionable" — ahora acepta escaneados |
| **Fase 5** | `POImportReview` — tabla | CAMBIO MENOR: mostrar badge "OCR" cuando `extraction_method = google_vision` |
| **Fase 6** | Configuracion OCR | SIMPLIFICADO: solo `GOOGLE_CLOUD_VISION_API_KEY` en `.env`, sin `PDF_OCR_ENABLED` toggle |
| **Apendice A** | Nuevos archivos | Agregar: `app/Services/OCR/OCRDriverInterface.php`, `app/Services/OCR/GoogleVisionOCRDriver.php`, `app/Services/OCR/TesseractOCRDriver.php`, `app/Services/OCR/OCRPageResult.php` |

---

## 13. Configuracion de Entorno Requerida

Esta seccion detalla los pasos exactos para configurar el entorno de desarrollo (Windows/XAMPP) y produccion para que el modulo de importacion OCR funcione correctamente.

---

### 13.1 Paso 1 — Crear Proyecto en Google Cloud y Activar Vision API

1. Acceder a `https://console.cloud.google.com/`.
2. Crear un proyecto nuevo o seleccionar el existente (ej: `flexcon-tracker`).
3. En el menu lateral: **APIs y servicios** → **Biblioteca**.
4. Buscar "Cloud Vision API" → hacer clic → **Habilitar**.
5. Ir a **APIs y servicios** → **Credenciales** → **Crear credenciales** → **Clave de API**.
6. Copiar la API key generada.
7. (Recomendado) Restringir la key: en las restricciones de API, limitar a "Cloud Vision API" unicamente.

**Nota de costos:** La cuenta de Google Cloud requiere una tarjeta de credito para activarse, pero el tier gratuito de 1,000 unidades/mes de DOCUMENT_TEXT_DETECTION no genera cargos para el volumen esperado de este proyecto. Google no cobra automaticamente hasta que se excede el tier gratuito y se habilita la facturacion manualmente.

---

### 13.2 Paso 2 — Instalar el Paquete PHP

Ejecutar en la raiz del proyecto:

```bash
composer require google/cloud-vision
```

Este paquete instala el cliente oficial de Google Cloud para PHP. Peso aproximado: ~15MB adicionales en `vendor/`.

---

### 13.3 Paso 3 — Configurar Variables de Entorno

Agregar al archivo `.env` del proyecto:

```env
# ─── Google Cloud Vision OCR ──────────────────────────────────────────────────
GOOGLE_CLOUD_VISION_API_KEY=AIzaSy...tu_clave_aqui...
PDF_OCR_ENGINE=google_vision
PDF_OCR_MIN_TEXT_CONFIDENCE=70
PDF_OCR_MAX_PAGES_SYNC=5
```

Agregar al archivo `.env.example` (para documentar sin exponer credenciales reales):

```env
# ─── Google Cloud Vision OCR ──────────────────────────────────────────────────
# Obtener en: https://console.cloud.google.com -> APIs y servicios -> Credenciales
# Activar en el proyecto: Cloud Vision API
# Tier gratuito: 1,000 unidades/mes (DOCUMENT_TEXT_DETECTION)
GOOGLE_CLOUD_VISION_API_KEY=
PDF_OCR_ENGINE=google_vision          # 'google_vision' | 'tesseract'
PDF_OCR_MIN_TEXT_CONFIDENCE=70        # 0-100, umbral minimo de confianza OCR para aceptar texto
PDF_OCR_MAX_PAGES_SYNC=5              # Paginas maximas por llamada sincrona a Vision API
```

---

### 13.4 Paso 4 — Crear Archivo de Configuracion Laravel

Crear `config/ocr.php`:

```php
<?php

return [
    'engine' => env('PDF_OCR_ENGINE', 'google_vision'),

    'min_confidence' => (int) env('PDF_OCR_MIN_TEXT_CONFIDENCE', 70),

    'max_pages_sync' => (int) env('PDF_OCR_MAX_PAGES_SYNC', 5),

    'google_vision' => [
        'api_key' => env('GOOGLE_CLOUD_VISION_API_KEY'),
        'endpoint' => 'https://vision.googleapis.com/v1/files:annotate',
    ],

    'tesseract' => [
        // Solo relevante si PDF_OCR_ENGINE=tesseract
        'binary_path' => env('TESSERACT_BINARY', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'),
        'ghostscript_binary' => env('GHOSTSCRIPT_BINARY', 'C:\\Program Files\\gs\\gs10.04.0\\bin\\gswin64c.exe'),
        'temp_dir' => storage_path('app/temp/ocr-images'),
        'dpi' => 300,
        'language' => 'eng',
    ],
];
```

---

### 13.5 Paso 5 — Registrar el Driver en el Service Container

En `app/Providers/AppServiceProvider.php`, agregar en el metodo `register()`:

```php
use App\Services\OCR\OCRDriverInterface;
use App\Services\OCR\GoogleVisionOCRDriver;
use App\Services\OCR\TesseractOCRDriver;

public function register(): void
{
    $this->app->bind(OCRDriverInterface::class, function ($app) {
        $engine = config('ocr.engine', 'google_vision');

        return match ($engine) {
            'tesseract'    => new TesseractOCRDriver(config('ocr.tesseract')),
            default        => new GoogleVisionOCRDriver(config('ocr.google_vision')),
        };
    });
}
```

---

### 13.6 Verificacion del Entorno

Despues de completar los pasos anteriores, verificar la configuracion ejecutando desde la consola de XAMPP:

```bash
php artisan tinker
```

```php
// Dentro de tinker:
$driver = app(\App\Services\OCR\OCRDriverInterface::class);
echo get_class($driver); // Debe imprimir: App\Services\OCR\GoogleVisionOCRDriver
```

Para una prueba funcional completa (requiere credenciales validas y un PDF de prueba):

```php
$service = app(\App\Services\PDFExtractorService::class);
$pages = $service->extractPages(storage_path('app/public/purchase-orders/Flexcon PO 50297 - 50298.pdf'));
dump(count($pages));       // Debe ser 2
dump($pages[0]->text);     // Debe mostrar el texto OCR de la primera pagina
dump($pages[0]->method);   // Debe ser 'google_vision'
dump($pages[0]->confidence); // Debe ser >= 70
```

---

### 13.7 Entorno de Produccion

Si el servidor de produccion es un VPS o servidor dedicado (no solo desarrollo local), los mismos pasos aplican:

1. Copiar la API key al `.env` de produccion (NO commitear al repositorio git).
2. `composer install --no-dev` incluira automaticamente `google/cloud-vision`.
3. No se requiere ninguna instalacion de binarios en el sistema operativo del servidor.
4. La unica dependencia de red es HTTPS saliente al dominio `vision.googleapis.com`.

**Si el servidor de produccion no tiene acceso a internet saliente:**
Cambiar `PDF_OCR_ENGINE=tesseract` en `.env` e instalar Tesseract + Ghostscript en el servidor. Ver Seccion 12.2 para los detalles de instalacion.

---

### 13.8 Archivos Adicionales al Apendice A (actualizados)

Los siguientes archivos se agregan a la lista de Apendice A como resultado de la arquitectura OCR:

| Tipo | Ruta |
|---|---|
| Configuracion | `config/ocr.php` |
| Interfaz | `app/Services/OCR/OCRDriverInterface.php` |
| DTO | `app/Services/OCR/OCRPageResult.php` |
| Driver principal | `app/Services/OCR/GoogleVisionOCRDriver.php` |
| Driver alternativo | `app/Services/OCR/TesseractOCRDriver.php` |

El archivo `app/Services/PDFExtractorService.php` (ya en Apendice A) ahora depende de `OCRDriverInterface` inyectada via constructor, en lugar de instanciar `smalot/pdfparser` directamente.

---

*Fin del documento principal. Ver Seccion 14 para el analisis de Python como alternativa al stack OCR actual.*

---

## 14. Opcion Alternativa: Integracion con Python

Esta seccion analiza Python como alternativa tecnologica para el procesamiento OCR, los mecanismos de integracion con el stack Laravel/Livewire existente, y una comparativa final contra la solucion Google Cloud Vision API ya documentada. El proposito es que el equipo tome una decision informada, no descartar Python por desconocimiento.

---

### 14.1 Por que Considerar Python para este Caso

PHP tiene opciones OCR limitadas: `thiagoalessio/tesseract_ocr` (wrapper de Tesseract) y llamadas HTTP a APIs externas. Python, en cambio, tiene el ecosistema OCR mas maduro del mercado, con librerias que cubren desde OCR clasico hasta modelos de deep learning especializados en documentos estructurados, todo instalable con `pip`.

**Ventajas concretas del ecosistema Python para este caso de uso:**

- Librerias de deep learning como `easyocr` y `paddleocr` superan a Tesseract en precision sobre documentos impresos, sin requerir acceso a internet ni costo por llamada.
- `pdf2image` + `Pillow` permiten conversion PDF-a-imagen de alta calidad con control granular de DPI, sin depender de Ghostscript como binario externo independiente.
- `pdfplumber` y `PyMuPDF` (fitz) pueden extraer coordenadas de caja del texto reconocido post-OCR, lo que permite implementar template matching de forma programatica (extraccion por posicion, no solo por regex).
- `OpenCV` ofrece pipeline completo de preprocesamiento: deskew, eliminacion de ruido, binarizacion adaptiva. Aplicado antes del OCR, mejora la precision en scans de baja calidad.
- El SDK oficial de Google Cloud Vision para Python (`google-cloud-vision`) es mas maduro y mejor documentado que el equivalente PHP.

**Desventaja principal:** Python no es el lenguaje del proyecto. Integrarlo implica introducir un segundo runtime, con sus propias dependencias, procesos y mecanismos de comunicacion con Laravel.

---

### 14.2 Tabla Comparativa de Librerias Python OCR

Para el caso especifico de **PDFs escaneados de Purchase Orders de Sumitomo Electric** (documentos impresos de calidad media, formato fijo, texto en ingles, numeros decimales de 4 cifras criticos):

| Libreria | Precision en numeros | Instalacion | Requiere GPU | Costo | Mejor para |
|---|---|---|---|---|---|
| **easyocr** | Alta (95-98%) — modelos neuronales por idioma | `pip install easyocr` — descarga modelos (~200MB) en primer uso | No (CPU funciona bien) | $0.00, open source | Documentos mixtos, multiples idiomas, sin Tesseract |
| **paddleocr** | Muy alta (97-99%) en documentos tabulares y formularios | `pip install paddlepaddle paddleocr` — ~500MB modelos | No (CPU por defecto, GPU opcional) | $0.00, open source | Documentos estructurados, tablas, formularios con campos etiquetados |
| **pytesseract + OpenCV** | Media-alta (88-94%) — mejora significativamente con preprocesamiento OpenCV | `pip install pytesseract opencv-python` + instalar `tesseract.exe` por separado | No | $0.00, open source | Pipeline clasico documentado, entornos con control de instalacion del sistema |
| **google-cloud-vision (SDK Python)** | Muy alta (98-99%) | `pip install google-cloud-vision` + service account JSON o API key | No — servicio en la nube | $0.00 hasta 1,000 pag/mes; $1.50/1,000 despues | Cuando ya se usa Vision API; el SDK Python es mas ergonomico que el PHP |
| **azure-ai-formrecognizer** | Muy alta (97-99%), especializado en formularios | `pip install azure-ai-formrecognizer` | No — servicio en la nube | 5,000 transacciones/mes gratis; $1.50/1,000 despues | Formularios estructurados con deteccion automatica de campos clave-valor |

**Conclusion de la tabla:** Para el escenario offline (sin dependencia de internet), `paddleocr` es la mejor opcion de Python para documentos de PO estructurados. Para el escenario con internet, el SDK Python de Google Cloud Vision ofrece la misma precision que la implementacion PHP pero con una API mas ergonomica.

---

### 14.3 Mecanismos de Integracion Python con Laravel

Existen cuatro patrones arquitecturales para integrar un proceso Python con una aplicacion Laravel existente. Se analizan a continuacion con sus implicaciones para este proyecto.

---

#### 14.3.1 Opcion 1 — Microservicio Python con FastAPI

**Descripcion:** Se crea un servicio HTTP independiente en Python usando FastAPI. Laravel llama a este servicio mediante el cliente HTTP de Laravel (`Http::post()`). El servicio recibe el PDF (en base64 o como archivo multipart), ejecuta el OCR y retorna un JSON con los campos extraidos por pagina.

**Diagrama de flujo:**

```
[Livewire POImportUpload]
        |
        | $pdf guardado en storage
        v
[PDFExtractorService (PHP)]
        |
        | Http::post('http://localhost:8001/extract-po', [...])
        v
[FastAPI Python — puerto 8001]
        |
        | paddleocr / easyocr procesa el PDF
        v
[Retorna JSON]
  {
    "pages": [
      { "page": 1, "text": "...", "confidence": 97 },
      { "page": 2, "text": "...", "confidence": 96 }
    ]
  }
        |
        v
[PDFExtractorService (PHP) construye OCRPageResult[]]
        |
        v
[POPdfParserService — sin cambios]
        |
        v
[POImportService — sin cambios]
```

**Pros:**
- Desacoplamiento total: PHP y Python se comunican por contrato HTTP, cada uno evoluciona independientemente.
- El microservicio puede escalar horizontalmente si el volumen de PDFs crece.
- FastAPI es asincrono (basado en ASGI/asyncio), puede procesar multiples PDFs en paralelo con Workers Uvicorn.
- El contrato JSON entre servicios es testeable de forma independiente.
- Si en el futuro se agrega un segundo cliente (app movil, otro sistema), el microservicio ya esta disponible.

**Contras:**
- Requiere correr dos servidores simultaneamente: Apache/PHP (XAMPP) y Uvicorn (Python).
- En Windows/XAMPP, Uvicorn debe iniciarse manualmente o configurarse como servicio de Windows. XAMPP no lo gestiona.
- Latencia de red adicional (localhost a localhost, tipicamente < 5ms, pero existe).
- Si el microservicio no esta corriendo, el proceso de importacion falla completamente. Requiere health check y manejo de error en `PDFExtractorService`.
- Agrega complejidad operacional en despliegue: dos procesos que versionar, monitorear y reiniciar.

**Complejidad de implementacion:** Alta. Requiere crear el proyecto FastAPI, configurar Uvicorn, manejar el ciclo de vida del servicio en Windows, y agregar el mecanismo de comunicacion HTTP en PHP.

**Cuando elegirlo:** Cuando el volumen de PDFs es alto y se necesita escalar el procesamiento OCR independientemente del servidor web. Cuando hay un equipo DevOps que puede gestionar multiples servicios. Cuando se anticipa que otros sistemas consumiran el mismo servicio OCR.

---

#### 14.3.2 Opcion 2 — Script Python Ejecutado via Shell desde Laravel

**Descripcion:** Laravel usa `Illuminate\Process\Process` (disponible desde Laravel 10+, que usa el componente `symfony/process`) para invocar un script Python directamente. El script lee el PDF, ejecuta el OCR, y escribe el resultado JSON por `stdout`. Laravel captura `stdout` y parsea el JSON.

**Diagrama de flujo:**

```
[PDFExtractorService (PHP)]
        |
        | Process::run(['python', 'scripts/extract_po.py', $pdfPath])
        v
[extract_po.py — proceso Python hijo]
        |
        | Lee $argv[1] (ruta al PDF)
        | Ejecuta paddleocr sobre el PDF
        | Imprime JSON en stdout:
        |   {"pages": [{"page":1,"text":"...","confidence":97},...]}
        v
[PDFExtractorService captura stdout]
        |
        | json_decode($result->output())
        v
[Construye OCRPageResult[]]
        |
        v
[POPdfParserService — sin cambios]
```

**Pros:**
- No requiere servidor adicional. Un solo proceso por llamada.
- Sin overhead de comunicacion HTTP. El intercambio es por pipe de sistema operativo.
- Facil de depurar: el script Python puede ejecutarse directamente en consola para probar.
- No hay estado compartido entre llamadas — cada invocacion es independiente y aislada.
- Integracion directa con el ecosistema Laravel via `Process::run()`, sin librerías adicionales.

**Contras:**
- Tiempo de inicio de Python por llamada: cargar el interprete + importar librerias (paddleocr importa modelos de ~500MB) puede tomar 5-15 segundos la primera vez. Esto es inaceptable para una respuesta HTTP sincrona.
- Timeout del servidor web (PHP-FPM o mod_php bajo Apache) puede interrumpir el proceso si el OCR tarda mas que `max_execution_time`.
- Si el script Python falla (excepcion, import error, modelo no descargado), el manejo de error en PHP depende de parsear stderr, lo que es fragil.
- El `$pdfPath` se pasa como argumento del sistema, lo que requiere sanitizacion cuidadosa para evitar inyeccion de comandos.
- Acoplamiento fuerte: la ruta al script Python, la version de Python y las dependencias del `venv` deben estar coordinadas con el codigo PHP.

**Complejidad de implementacion:** Baja-Media. El script Python es simple. La complejidad esta en manejar timeouts, errores y el path al ejecutable Python en Windows.

**Cuando elegirlo:** Para cargas de trabajo ligeras donde la latencia adicional es aceptable, o cuando se invoca desde un Job de Laravel en segundo plano (donde el timeout del servidor web no aplica). Util como prototipo rapido para validar la precision del OCR antes de invertir en una arquitectura mas robusta.

---

#### 14.3.3 Opcion 3 — Laravel Queue + Python Worker

**Descripcion:** Laravel encola un Job que contiene la ruta al PDF y el `batch_id`. Un proceso worker Python corre de forma independiente, consume mensajes de la cola (Redis o base de datos), procesa el PDF con OCR, y actualiza directamente los registros en la base de datos MySQL/SQLite del proyecto. Laravel monitorea el estado del batch mediante polling o websockets.

**Diagrama de flujo:**

```
[Livewire POImportUpload]
        |
        | dispatch(new ProcessOCRJob($batchId, $pdfPath))
        v
[Cola Laravel — database o Redis]
        |
        | (Job en cola — procesamiento asincrono)
        v
[Python Worker — proceso independiente]
        |
        | Consume mensaje de la cola
        | Lee PDF desde storage path
        | Ejecuta paddleocr
        | Conecta a MySQL directamente (usando pymysql o sqlalchemy)
        | Actualiza po_import_items y po_import_batches
        | Marca batch.status = 'pending_review'
        v
[Livewire POImportUpload — polling]
        |
        | Detecta batch.status = 'pending_review'
        v
[Redirige a POImportReview]
```

**Pros:**
- Procesamiento completamente asincrono: el usuario ve un spinner y la pagina se actualiza cuando el procesamiento termina. Sin riesgo de timeout HTTP.
- Ideal para PDFs grandes (20+ paginas) o picos de carga (multiples usuarios subiendo PDFs simultaneamente).
- El worker Python puede manejar su propio pool de modelos OCR cargados en memoria, eliminando el tiempo de carga por llamada del Escenario B.
- Arquitectura similar a como funcionan sistemas de procesamiento de documentos en produccion real.

**Contras:**
- Requiere Redis (o tabla de cola con polling) funcionando. El `.env.example` ya incluye configuracion de Redis, pero en XAMPP no esta instalado por defecto.
- El worker Python debe conectarse directamente a la base de datos, lo que requiere compartir credenciales y conocer el esquema de tablas. Si el esquema cambia, el worker debe actualizarse.
- Coordinacion de estado entre PHP y Python via DB introduce acoplamiento a nivel de datos.
- El worker Python debe iniciarse, monitorearse y reiniciarse si falla. En Windows, esto requiere Task Scheduler o un servicio NSSM (Non-Sucking Service Manager).
- Si el worker Python esta caido, los jobs se acumulan en la cola sin procesarse. El usuario no recibe retroalimentacion de error.

**Complejidad de implementacion:** Alta. Requiere Redis o tabla de cola bien configurada, un worker Python con cliente de base de datos, manejo de estado entre dos procesos, y mecanismo de reinicio del worker.

**Cuando elegirlo:** Cuando el volumen de PDFs es alto y la latencia de procesamiento hace inaceptable la espera sincrona. Cuando ya se usa Redis en el proyecto. Cuando se tiene infraestructura DevOps para gestionar workers.

---

#### 14.3.4 Opcion 4 — Python como Sidecar via Artisan Command

**Descripcion:** Se crea un Artisan command (`php artisan ocr:extract {pdfPath} {batchId}`) que internamente invoca el script Python via `Process::run()`. El command es invocado desde un Job de Laravel en la cola (no directamente desde la peticion HTTP). Esto combina el aislamiento de procesos del Escenario B con el procesamiento asincrono del Escenario C, pero sin requerir que Python tenga acceso directo a la base de datos.

**Diagrama de flujo:**

```
[Livewire POImportUpload]
        |
        | dispatch(new ProcessPOImportBatch($batchId))
        v
[Job Laravel — queue worker PHP]
        |
        | Artisan::call('ocr:extract', ['pdfPath' => $path, 'batchId' => $id])
        v
[OcrExtractCommand (Artisan)]
        |
        | Process::run(['python', 'scripts/extract_po.py', $pdfPath])
        v
[extract_po.py]
        |
        | OCR del PDF
        | Retorna JSON por stdout
        v
[OcrExtractCommand]
        |
        | Parsea JSON de stdout
        | Actualiza POImportBatch y POImportItems via Eloquent (PHP)
        | Toda la logica de DB permanece en PHP
        v
[Job completa — batch.status = 'pending_review']
        |
        v
[Livewire detecta cambio y redirige a POImportReview]
```

**Pros:**
- Python solo hace OCR. Toda la logica de negocio (parseo, validacion, DB) permanece en PHP/Laravel.
- No requiere que Python tenga acceso a la base de datos ni conozca el esquema.
- El procesamiento es asincrono (via Job), sin riesgo de timeout HTTP.
- El Artisan command es testeable con `$this->artisan('ocr:extract', [...])` en tests de feature.
- Bien integrado con el ecosistema Laravel: logs, manejo de errores y reintentos via la cola de Laravel.
- Si se cambia el motor OCR (de paddleocr a easyocr, o a Vision API), solo cambia el script Python, sin tocar el Artisan command ni el Job.

**Contras:**
- El tiempo de carga de Python y sus modelos OCR ocurre en cada invocacion del script (a menos que se use un servidor FastAPI que mantenga el modelo en memoria — combinacion de Opcion 1 y Opcion 4).
- Requiere que el queue worker de Laravel (`php artisan queue:work`) este corriendo. En XAMPP esto se inicia manualmente o via el script `composer dev` ya configurado en el proyecto.
- El script Python y el `venv` deben estar en una ruta conocida y accesible para el proceso PHP/Apache.

**Complejidad de implementacion:** Media. El Job y el Artisan command son codigo Laravel estandar. La complejidad esta en la gestion del `venv` Python y el manejo robusto de stdout/stderr entre procesos.

**Cuando elegirlo:** Es la opcion recomendada si se decide usar Python en este proyecto. Combina la potencia del ecosistema OCR de Python con la arquitectura Laravel existente, sin romper la separacion de responsabilidades entre los dos lenguajes.

---

### 14.4 Recomendacion para este Proyecto Especifico

**Si se decide integrar Python, la combinacion recomendada es:**

**Mecanismo:** Opcion 4 (Artisan Command como sidecar via Job de cola)
**Libreria OCR:** `paddleocr`

**Justificacion:**

`paddleocr` es la libreria Python con mejor relacion precision/complejidad para documentos de formularios estructurados. En benchmarks sobre documentos de compra impresos, supera a `pytesseract` en precision de numeros decimales y no requiere instalacion de binarios del sistema operativo (a diferencia de Tesseract). Su instalacion es completamente via `pip` dentro de un `venv` aislado.

El patron Artisan Command como sidecar mantiene toda la logica de negocio (parseo, validacion de precios, creacion de registros) en PHP, donde el equipo tiene dominio tecnico. Python solo hace lo que hace mejor: OCR. La interfaz entre los dos mundos es un contrato JSON simple por stdout, que es facil de versionar, testear y depurar.

El procesamiento asincrono via Job de Laravel aprovecha la infraestructura de cola que ya esta configurada en el proyecto (`.env.example` tiene `QUEUE_CONNECTION=database`). No requiere Redis.

**Pipeline completo con la combinacion recomendada:**

```
1. Operador sube PDF en POImportUpload (Livewire)
        |
        v
2. PDF guardado en storage — dispatch(ProcessPOImportBatch)
        |
        v
3. Job PHP ejecuta Artisan command:
   php artisan ocr:extract {storagePath} {batchId}
        |
        v
4. Artisan command invoca:
   Process::run(['python', 'scripts/extract_po.py', $pdfPath])
        |
        v
5. extract_po.py (paddleocr):
   - Convierte PDF a imagenes (pdf2image)
   - Aplica paddleocr por pagina
   - Imprime JSON con texto y confianza por pagina
        |
        v
6. Artisan command recibe JSON de stdout
   - Construye OCRPageResult[] en PHP
   - Actualiza POImportItems via Eloquent
   - Batch.status = 'pending_review'
        |
        v
7. Livewire polling detecta status = 'pending_review'
   - Redirige a POImportReview
        |
        v
8. Flujo normal — sin cambios desde la revision en adelante
```

---

### 14.5 Comparativa Final: Python vs Google Cloud Vision API

| Criterio | Python (paddleocr + Artisan sidecar) | Google Cloud Vision API (solucion actual) |
|---|---|---|
| **Precision en numeros decimales criticos** | Alta (96-98%) en documentos bien escaneados | Muy alta (98-99%), entrenado con datos financieros |
| **Precision en scans de baja calidad** | Media-alta — mejora con preprocesamiento OpenCV | Alta — Vision aplica preprocessing interno avanzado |
| **Costo mensual (volumen estimado ~100 pag/mes)** | $0.00 — completamente gratuito, sin limite | $0.00 — dentro del tier gratuito de 1,000 pag/mes |
| **Costo mensual (volumen alto ~2,000 pag/mes)** | $0.00 — sin costo sin importar el volumen | $1.50 USD — $1.50 por 1,000 pag adicionales |
| **Complejidad de instalacion en XAMPP/Windows** | Media — Python + venv + pip install paddleocr (~500MB modelos) | Minima — solo composer require + API key en .env |
| **Dependencia de internet** | Ninguna — 100% offline una vez instalado | Si — requiere HTTPS saliente a vision.googleapis.com |
| **Tiempo de implementacion estimado** | 2-3 dias (script Python + Job + Command + tests) | 1 dia (GoogleVisionOCRDriver + tests con mock) |
| **Mantenimiento a largo plazo** | Medio — actualizar paddleocr, modelos, venv | Bajo — sin binarios locales, Google gestiona el modelo |
| **Riesgo de confusion 3/8, 0/6 en precios** | Bajo con paddleocr | Muy bajo con Vision API |
| **Funciona sin cuenta externa / credenciales** | Si — autonomo en el servidor | No — requiere API key de Google Cloud |
| **Tiempo de primer procesamiento (carga de modelos)** | 10-20 segundos (primera carga del modelo en el proceso) | 1-3 segundos (llamada HTTP a API externa) |
| **Escalabilidad horizontal** | Requiere replicar el entorno Python en cada servidor | Automatica — la API de Google escala por si sola |
| **Privacidad de datos** | Total — el PDF no sale del servidor | El PDF se envia a servidores de Google para procesamiento |

**Veredicto de la comparativa:**

Google Cloud Vision API gana en facilidad de implementacion, mantenimiento y precision documentada. Python (paddleocr) gana en independencia de internet, privacidad de datos y costo sin limite de volumen.

Para el perfil de este proyecto (XAMPP Windows, equipo PHP, volumen bajo, sin restricciones de privacidad documentadas para PDFs de POs de proveedores), **Google Cloud Vision sigue siendo la recomendacion principal**. Python como alternativa es valido si el equipo tiene restricciones de conectividad a internet en el servidor de produccion, o si en el futuro el volumen supera ampliamente el tier gratuito de Vision API.

---

### 14.6 Prerequisitos de Instalacion en Windows/XAMPP para el Escenario Recomendado

Si se decide implementar la integracion Python (Opcion 4 + paddleocr), los pasos de preparacion del entorno en Windows son los siguientes:

**Paso 1 — Instalar Python 3.11 o superior:**

```
winget install Python.Python.3.11
```

Verificar instalacion:

```bash
python --version   # Debe mostrar Python 3.11.x o superior
pip --version
```

Si `python` no se encuentra en PATH despues de la instalacion: Ir a Inicio > Editar las variables de entorno del sistema > Variables de entorno > Path > Agregar `C:\Users\{usuario}\AppData\Local\Programs\Python\Python311\` y `C:\...\Python311\Scripts\`.

**Paso 2 — Crear un entorno virtual aislado en la raiz del proyecto:**

```bash
cd C:/xampp/htdocs/flexcon-tracker
python -m venv python-ocr-env
```

Activar el entorno (necesario cada vez que se instala un paquete nuevo o se ejecuta manualmente):

```bash
python-ocr-env\Scripts\activate
```

**Paso 3 — Instalar las dependencias Python:**

```bash
pip install paddlepaddle paddleocr pdf2image Pillow
```

Nota: `paddleocr` descargara los modelos de reconocimiento de texto (~500MB) en el primer uso. Asegurarse de que el servidor tenga espacio en disco disponible.

La libreria `pdf2image` requiere `poppler` para convertir PDFs a imagenes. En Windows:

```
# Descargar poppler desde: https://github.com/oschwartz10612/poppler-windows/releases
# Extraer en C:\poppler\
# Agregar C:\poppler\Library\bin al PATH del sistema
```

Alternativamente, usar `PyMuPDF` en lugar de `pdf2image` (no requiere poppler):

```bash
pip install pymupdf
```

**Paso 4 — Crear el archivo de script Python:**

Ubicacion recomendada: `C:/xampp/htdocs/flexcon-tracker/scripts/extract_po.py`

El script debe aceptar la ruta al PDF como argumento, ejecutar el OCR y escribir el JSON resultado por stdout. Esta ruta debe estar en `.gitignore` para el directorio `scripts/__pycache__/` pero el script `.py` si debe comitearse.

**Paso 5 — Configurar variables de entorno en Laravel:**

Agregar al `.env` del proyecto:

```env
# ─── Python OCR (alternativa a Google Cloud Vision) ───────────────────────────
PDF_OCR_ENGINE=python_paddle
PYTHON_BINARY=C:\xampp\htdocs\flexcon-tracker\python-ocr-env\Scripts\python.exe
PYTHON_OCR_SCRIPT=C:\xampp\htdocs\flexcon-tracker\scripts\extract_po.py
```

Agregar al `.env.example` para documentacion:

```env
# ─── Python OCR (alternativa offline a Google Cloud Vision) ───────────────────
# Requiere: Python 3.11+, venv activo con paddlepaddle + paddleocr + pymupdf
PDF_OCR_ENGINE=python_paddle      # 'google_vision' | 'tesseract' | 'python_paddle'
PYTHON_BINARY=                    # Ruta al python.exe dentro del venv
PYTHON_OCR_SCRIPT=                # Ruta al script extract_po.py
```

**Paso 6 — Registrar el nuevo driver en el Service Container:**

En `app/Providers/AppServiceProvider.php`, ampliar el `match` existente de la Seccion 13.5:

```
// Agregar al match existente:
'python_paddle' => new PythonOCRDriver(config('ocr.python')),
```

Esto no requiere modificar `PDFExtractorService`, `POPdfParserService` ni ningun otro servicio. El patron Strategy de `OCRDriverInterface` ya lo acomoda sin cambios adicionales.

**Paso 7 — Verificar la integracion:**

```bash
php artisan tinker
```

```php
$driver = app(\App\Services\OCR\OCRDriverInterface::class);
echo get_class($driver); // App\Services\OCR\PythonOCRDriver
```

---

### 14.7 Impacto en la Arquitectura Existente

**El patron Strategy documentado en la Seccion 5.3 (`OCRDriverInterface`) acomoda un driver Python sin ningun cambio en el resto del sistema.**

La interfaz ya define el contrato:

```
interface OCRDriverInterface
{
    public function extractPagesFromPdf(string $pdfPath): array;
    // Retorna OCRPageResult[], uno por pagina
}
```

Agregar soporte Python significa exclusivamente:

1. Crear `app/Services/OCR/PythonOCRDriver.php` que implementa `OCRDriverInterface`.
2. Su implementacion interna invoca el script Python via `Process::run()` y traduce el JSON de stdout a `OCRPageResult[]`.
3. Registrar el nuevo driver en el Service Container con la clave `'python_paddle'`.
4. Agregar la variable `PDF_OCR_ENGINE=python_paddle` al `.env`.

**Ningun otro archivo cambia.** `PDFExtractorService`, `POPdfParserService`, `POImportService`, los modelos, las migraciones, los componentes Livewire y las vistas son completamente ajenos al motor OCR elegido. Este es el beneficio concreto de haber disenado con el patron Strategy desde el principio.

**Mapa de impacto:**

```
OCRDriverInterface  <-- Contrato sin cambios
        |
        +--- GoogleVisionOCRDriver   (Seccion 13 — ya documentado)
        |
        +--- TesseractOCRDriver      (Seccion 12.2 — ya documentado)
        |
        +--- PythonOCRDriver         (NUEVO — esta seccion)
                |
                | Process::run(['python', PYTHON_OCR_SCRIPT, $pdfPath])
                v
              extract_po.py (paddleocr)
                |
                | JSON por stdout
                v
              OCRPageResult[] construido en PHP

PDFExtractorService     -- sin cambios
POPdfParserService      -- sin cambios
POImportService         -- sin cambios
POImportBatch / Item    -- sin cambios
Livewire components     -- sin cambios
Vistas blade            -- sin cambios
```

**Unico archivo nuevo de PHP:** `app/Services/OCR/PythonOCRDriver.php`
**Unico archivo nuevo de Python:** `scripts/extract_po.py`
**Cambio en AppServiceProvider:** Agregar un caso al `match` existente (1 linea)
**Cambio en config/ocr.php:** Agregar bloque `'python'` con las rutas de binario y script

La adicion del driver Python no rompe los drivers existentes ni requiere migration de base de datos. Se puede activar y desactivar cambiando `PDF_OCR_ENGINE` en `.env` sin reiniciar el servidor web.

---

*Fin del documento. Proxima accion: revision con el equipo para confirmar (1) disponibilidad de cuenta Google Cloud vs. restricciones de conectividad en produccion, (2) decision sobre integracion Python como alternativa o complemento, (3) separacion de paginas en confirmImport (Seccion 10.3), y (4) prioridad de la Fase 7 (Manager PO) respecto a las fases anteriores.*
