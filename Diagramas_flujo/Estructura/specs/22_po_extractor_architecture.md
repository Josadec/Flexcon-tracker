# 22 — PO Extractor: Arquitectura e Integración

## Resumen del Requerimiento

Ensambles Formula recibe periódicamente lotes de PDFs escaneados (imágenes, no texto seleccionable) que contienen Purchase Orders — típicamente 40-50 páginas, una PO por página. Cada semana también reciben el **FPL-02 Lista de Envío** con los WO# que deben procesar.

**Flujo manual actual (doloroso):**
1. Ver FPL-02 → identificar qué WO# toca enviar esa semana
2. Buscar manualmente en el PDF de POs cuál página contiene ese WO# (campo "DELIVERY INSTRUCTIONS")
3. Imprimir esa hoja específica → firmarla en el sistema

**Obstáculos:** PDFs de 40-50 páginas, escaneados como imagen → imposible Ctrl+F. Toma mucho tiempo manualmente.

**Solución:** Módulo web integrado en Flexcon Tracker que usa OCR (Tesseract) para indexar los PDFs y extraer las páginas exactas listas para firmar.

> **Estado:** En pausa hasta que el servidor físico esté disponible con Ubuntu (Proxmox).  
> El análisis está completo — al tener Ubuntu listo se procede directamente a la implementación.

---

## Script Python (ya existe y es funcional)

El script `flexcon_po_lookup.py` fue desarrollado y probado previamente. Sus operaciones:

```bash
# Indexar un lote de PDFs (una sola vez por lote recibido)
python flexcon_po_lookup.py index --pdf_dir ./lote_enero

# Extraer por WO#
python flexcon_po_lookup.py extract --wo 2040057,2040081 --merge

# Extraer por rango de PO
python flexcon_po_lookup.py extract --po 50455-50500 --merge

# Extraer cruzando con FPL-02
python flexcon_po_lookup.py extract --fpl_pdf FPL-02_mayo.pdf --output_dir ./semana20

# Extraer desde lista mixta (.txt con WOs y POs)
python flexcon_po_lookup.py extract --list_file semana_20.txt --merge

# Búsqueda rápida sin extraer
python flexcon_po_lookup.py search --query 2040081
```

**Key de cruce:** El WO# que aparece en "DELIVERY INSTRUCTIONS" dentro de cada página de PO es exactamente el mismo WO# que lista el FPL-02.

---

## Archivos a Crear

```
app/
  Jobs/
    ProcessPoIndexJob.php
    ProcessPoExtractJob.php
  Livewire/
    Admin/
      PoExtractor/
        PoIndexManager.php
        PoExtract.php
  Models/
    PoIndexJob.php
  Services/
    PoExtractorService.php
  Http/
    Controllers/
      PoExtractorDownloadController.php
  Console/
    Commands/
      CleanPoExtractorResultsCommand.php

resources/
  views/
    livewire/
      admin/
        po-extractor/
          po-index-manager.blade.php
          po-extract.blade.php

database/
  migrations/
    2026_05_12_000001_create_po_index_jobs_table.php

scripts/
  po_extractor/
    flexcon_po_lookup.py
    requirements.txt

config/
  po_extractor.php

storage/app/private/po_extractor/
  uploads/     ← PDFs subidos (temporal, se limpian tras indexar)
  index/       ← po_index.json (persistente, acumulativo)
  results/     ← PDFs extraídos listos para descargar (TTL 24h)
```

## Modificaciones a Archivos Existentes

| Archivo | Cambio |
|---------|--------|
| `routes/admin.php` | Agregar grupo de rutas con middleware `role:admin\|Materiales` |
| `sidebar.blade.php` | Agregar item "PO Extractor" en el bloque de Materiales |
| `config/queue.php` | Agregar conexión `po-extractor` con `retry_after: 700` |
| `.env` / `.env.example` | Agregar `PO_EXTRACTOR_PYTHON_BIN` y `PO_EXTRACTOR_TESSERACT_PATH` |

---

## Migración: `po_index_jobs`

```php
Schema::create('po_index_jobs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('operation', ['index', 'extract']);
    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
          ->default('pending')->index();
    $table->json('input_params');                  // modo, WOs, POs, rango, etc.
    $table->json('input_file_paths')->nullable();  // paths en storage/uploads/
    $table->string('result_file_path')->nullable();
    $table->string('result_filename')->nullable();
    $table->string('progress_message')->nullable();
    $table->text('error_output')->nullable();
    $table->unsignedInteger('pages_indexed')->nullable();
    $table->unsignedInteger('pages_extracted')->nullable();
    $table->string('laravel_job_id')->nullable();
    $table->timestamps();
});
```

---

## Flujo de la UI

### Pantalla 1: Gestión del Índice (`/admin/po-extractor`)

- Panel de estado del índice: fecha de última indexación, total de POs/WOs indexados
- Tabla de los últimos 10 jobs de tipo `index` con estado (badge), páginas procesadas y usuario
- File input múltiple (solo `.pdf`) con `Livewire\WithFileUploads`
- Al indexar: crea `PoIndexJob` → despacha `ProcessPoIndexJob` → activa `wire:poll.3s` para mostrar progreso

### Pantalla 2: Extracción (`/admin/po-extractor/extract`)

Tabs con Alpine.js `x-data`:

| Tab | Input | Equivalente CLI |
|-----|-------|----------------|
| Por WO# | Texto libre, separado por coma o salto de línea | `--wo` |
| Por PO# | POs individuales o rango `50455-50500` | `--po` |
| Desde FPL-02 | Upload del PDF del FPL-02 escaneado | `--fpl_pdf` |
| Lista mixta | Textarea con mezcla de WOs y POs | `--list_file` |

**Opciones comunes:**
- Checkbox "Combinar en un solo PDF" (`--merge`)
- Checkbox "Un PDF por PO" (default sin merge)

**Flujo de extracción:** crea `PoIndexJob` con `operation=extract` → despacha `ProcessPoExtractJob` → `wire:poll.2s` durante procesamiento → botón "Descargar PDF" al completarse → `PoExtractorDownloadController` verifica `user_id` antes de servir el archivo.

---

## Infraestructura Requerida

### Servidor Ubuntu (objetivo)

```bash
sudo apt-get install tesseract-ocr tesseract-ocr-spa poppler-utils python3-pip
pip3 install pytesseract pdf2image pypdf Pillow
```

### Variables de entorno

```env
PO_EXTRACTOR_PYTHON_BIN=python3
PO_EXTRACTOR_TESSERACT_PATH=/usr/bin/tesseract
```

### Queue worker dedicado

```php
// ProcessPoIndexJob.php
public $queue = 'po-extractor';
public $timeout = 600;   // 10 minutos (OCR de 50 páginas puede tardar 2-8 min)
public $tries = 1;       // Sin reintentos automáticos
```

```bash
php artisan queue:work --queue=po-extractor,default --timeout=650
```

### Llamada al script desde Laravel

```php
// PoExtractorService.php
$result = Process::timeout(600)
    ->path(storage_path('app/private/po_extractor'))
    ->run([
        config('po_extractor.python_bin'),
        base_path('scripts/po_extractor/flexcon_po_lookup.py'),
        'extract',
        '--wo', $params['wo'],
        '--merge',
        '--output_dir', $outputDir,
    ]);

if ($result->failed() || !file_exists($resultFile)) {
    throw new \RuntimeException($result->errorOutput());
}
```

> **Criterio de fallo:** Exit code != 0 **O** el archivo resultado no existe al terminar. La existencia del archivo es el criterio primario.

### Limpieza automática

- `CleanPoExtractorResultsCommand` (artisan: `po-extractor:cleanup`)
- Registrado en `routes/console.php` con `Schedule::command('po-extractor:cleanup')->daily()`
- Elimina archivos en `results/` con `updated_at > 24h`
- Elimina registros `PoIndexJob` completed con `updated_at > 7 días`
- El `po_index.json` es **persistente** — nunca se borra automáticamente

---

## Riesgos y Decisiones

| # | Riesgo | Resolución |
|---|--------|-----------|
| 1 | **Entorno objetivo** | Solo Ubuntu (Proxmox local). XAMPP descartado por limitaciones y rendimiento |
| 2 | **Queue timeout** (OCR > 90s default) | Conexión `po-extractor` con `retry_after: 700` en `config/queue.php` |
| 3 | **Exit code 0 sin archivo resultado** | Verificar existencia del archivo, no solo exit code |
| 4 | **Descarga sin verificar ownership** | `PoExtractorDownloadController` valida `$job->user_id === auth()->id()` o rol `admin` |
| 5 | **Tamaño de PDFs (15-80 MB)** | Ajustar `upload_max_filesize=100M` y `client_max_body_size 150m` (Nginx) |

---

## Orden de Implementación

1. Instalar dependencias en Ubuntu (`tesseract-ocr`, `poppler-utils`, `python3-pip`, paquetes pip)
2. Probar el script Python en CLI directamente en el servidor
3. Migración + Modelo `PoIndexJob`
4. `PoExtractorService` con lógica de construcción de comandos
5. `ProcessPoIndexJob` + `ProcessPoExtractJob`
6. `PoExtractorDownloadController`
7. Componentes Livewire `PoIndexManager` + `PoExtract` con sus vistas
8. Rutas en `admin.php` + item en sidebar
9. `CleanPoExtractorResultsCommand` + scheduler
10. Ajustar `php.ini` / Nginx y configuración del queue worker

---

*Análisis generado: 2026-05-11 | Pendiente de implementación hasta disponibilidad de servidor Ubuntu (Proxmox)*
