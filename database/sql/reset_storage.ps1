# ============================================================================
# RESET DE STORAGE - Flexcon Tracker
# ============================================================================
# Borra los archivos fisicos (PDFs) generados por la corrida que SI se
# persisten en disco: los PDFs de Purchase Orders (subidos y firmados).
#
# NO toca:
#   - storage/app/public/signatures/  -> contiene firmas de USUARIOS (maestro)
#   - Invoices / Packing Slips / Shipping Lists: NO se guardan en disco,
#     se generan al vuelo al descargarlos, no hay archivos que limpiar.
#
# Correr DESPUES del script SQL (reset_corrida.sql).
# Uso:  powershell -ExecutionPolicy Bypass -File database\sql\reset_storage.ps1
# ============================================================================

$ErrorActionPreference = 'Stop'

# Raiz del disco 'public' (ajusta si tu storage vive en otro lado)
$publicRoot = Join-Path $PSScriptRoot '..\..\storage\app\public'
$publicRoot = [System.IO.Path]::GetFullPath($publicRoot)

$targets = @(
    'purchase-orders'   # incluye la subcarpeta 'signed/'
)

Write-Host "Storage root: $publicRoot" -ForegroundColor Cyan

foreach ($folder in $targets) {
    $full = Join-Path $publicRoot $folder
    if (Test-Path $full) {
        # Borra TODO el contenido (archivos y subcarpetas) pero conserva la carpeta raiz
        Get-ChildItem -Path $full -Recurse -Force | Remove-Item -Recurse -Force -Confirm:$false
        Write-Host "  Limpiada: $folder" -ForegroundColor Green
    } else {
        Write-Host "  (no existe, se omite): $folder" -ForegroundColor DarkGray
    }
}

# Carpeta opcional que se crea solo si alguien sube un documento firmado manualmente
$signedDocs = Join-Path $publicRoot 'signed-documents'
if (Test-Path $signedDocs) {
    Get-ChildItem -Path $signedDocs -Recurse -Force | Remove-Item -Recurse -Force -Confirm:$false
    Write-Host "  Limpiada: signed-documents" -ForegroundColor Green
}

Write-Host "Listo. Archivos de PO eliminados. Las firmas de usuarios se conservaron." -ForegroundColor Cyan
