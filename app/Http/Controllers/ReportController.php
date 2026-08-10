<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    // =========================================================
    // Validación de fechas compartida
    // =========================================================

    private function dates(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }

    // =========================================================
    // PRODUCCIÓN
    // =========================================================

    public function produccionPdf(Request $request): \Illuminate\Http\Response
    {
        $v    = $this->dates($request);
        $data = $this->service->getProduccionReport($v['start_date'] ?? null, $v['end_date'] ?? null);
        $data['logoPath'] = public_path('flexcon.png');

        return Pdf::loadView('pdf.report-produccion', $data)
            ->setPaper('letter', 'landscape')
            ->download('reporte-produccion.pdf');
    }

    public function produccionExcel(Request $request): StreamedResponse
    {
        $v    = $this->dates($request);
        $data = $this->service->getProduccionReport($v['start_date'] ?? null, $v['end_date'] ?? null);

        return $this->streamXlsx(
            $this->buildProduccionSheet($data),
            'reporte-produccion.xlsx'
        );
    }

    // =========================================================
    // MATERIALES
    // =========================================================

    public function materialesPdf(Request $request): \Illuminate\Http\Response
    {
        $v    = $this->dates($request);
        $data = $this->service->getMaterialesReport($v['start_date'] ?? null, $v['end_date'] ?? null);
        $data['logoPath'] = public_path('flexcon.png');

        return Pdf::loadView('pdf.report-materiales', $data)
            ->setPaper('letter', 'landscape')
            ->download('reporte-materiales.pdf');
    }

    public function materialesExcel(Request $request): StreamedResponse
    {
        $v    = $this->dates($request);
        $data = $this->service->getMaterialesReport($v['start_date'] ?? null, $v['end_date'] ?? null);

        return $this->streamXlsx(
            $this->buildMaterialesSheet($data),
            'reporte-materiales.xlsx'
        );
    }

    // =========================================================
    // CALIDAD
    // =========================================================

    public function calidadPdf(Request $request): \Illuminate\Http\Response
    {
        $v    = $this->dates($request);
        $data = $this->service->getCalidadReport($v['start_date'] ?? null, $v['end_date'] ?? null);
        $data['logoPath'] = public_path('flexcon.png');

        return Pdf::loadView('pdf.report-calidad', $data)
            ->setPaper('letter', 'landscape')
            ->download('reporte-calidad.pdf');
    }

    public function calidadExcel(Request $request): StreamedResponse
    {
        $v    = $this->dates($request);
        $data = $this->service->getCalidadReport($v['start_date'] ?? null, $v['end_date'] ?? null);

        return $this->streamXlsx(
            $this->buildCalidadSheet($data),
            'reporte-calidad.xlsx'
        );
    }

    // =========================================================
    // EMPAQUES
    // =========================================================

    public function empaquesPdf(Request $request): \Illuminate\Http\Response
    {
        $v    = $this->dates($request);
        $data = $this->service->getEmpaquesReport($v['start_date'] ?? null, $v['end_date'] ?? null);
        $data['logoPath'] = public_path('flexcon.png');

        return Pdf::loadView('pdf.report-empaques', $data)
            ->setPaper('letter', 'landscape')
            ->download('reporte-empaques.pdf');
    }

    public function empaquesExcel(Request $request): StreamedResponse
    {
        $v    = $this->dates($request);
        $data = $this->service->getEmpaquesReport($v['start_date'] ?? null, $v['end_date'] ?? null);

        return $this->streamXlsx(
            $this->buildEmpaquesSheet($data),
            'reporte-empaques.xlsx'
        );
    }

    // =========================================================
    // GENERAL
    // =========================================================

    public function generalPdf(Request $request): \Illuminate\Http\Response
    {
        $v    = $this->dates($request);
        $data = $this->service->getGeneralReport($v['start_date'] ?? null, $v['end_date'] ?? null);
        $data['logoPath'] = public_path('flexcon.png');

        return Pdf::loadView('pdf.report-general', $data)
            ->setPaper('letter', 'landscape')
            ->download('reporte-general.pdf');
    }

    public function generalExcel(Request $request): StreamedResponse
    {
        $v    = $this->dates($request);
        $data = $this->service->getGeneralReport($v['start_date'] ?? null, $v['end_date'] ?? null);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->addSheetFromData($spreadsheet, $this->buildProduccionSheet($data['produccion']), 'Producción');
        $this->addSheetFromData($spreadsheet, $this->buildMaterialesSheet($data['materiales']), 'Materiales');
        $this->addSheetFromData($spreadsheet, $this->buildCalidadSheet($data['calidad']), 'Calidad');
        $this->addSheetFromData($spreadsheet, $this->buildEmpaquesSheet($data['empaques']), 'Empaques');
        $spreadsheet->setActiveSheetIndex(0);

        return $this->streamXlsx($spreadsheet, 'reporte-general.xlsx');
    }

    // =========================================================
    // Excel builders — PRODUCCIÓN
    // =========================================================

    private function buildProduccionSheet(array $data): Spreadsheet
    {
        $sp    = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Producción');

        [$hBg, $sBg, $odd, $white, $fw] = $this->colors();

        $this->headerRow($sheet, 'REPORTE DE PRODUCCIÓN', $data, 7, $hBg, $sBg, $fw);

        // Stats
        $row = 4;
        $this->sectionTitle($sheet, $row, 'RESUMEN', $hBg, $fw, 7);
        $row++;
        $stats = [
            ['Registros de pesada',   $data['stats']['total_registros']],
            ['Total piezas',          $data['stats']['total_piezas']],
            ['Piezas buenas',         $data['stats']['total_buenas']],
            ['Piezas malas',          $data['stats']['total_malas']],
            ['Lotes afectados',       $data['stats']['lotes_afectados']],
            ['Tasa de calidad (%)',   $data['stats']['tasa_calidad'] . '%'],
        ];
        foreach ($stats as $i => $s) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $sheet->setCellValue("A{$row}", $s[0]);
            $sheet->setCellValue("B{$row}", $s[1]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $row++;
        }
        $row++;

        // Detalle
        $this->sectionTitle($sheet, $row, 'DETALLE DE PESADAS', $hBg, $fw, 7);
        $row++;
        $headers = ['#', 'Fecha', 'Lote', 'Work Order', 'Kit', 'Piezas Total', 'Buenas', 'Malas', 'Operador'];
        $widths  = [5, 16, 14, 14, 12, 12, 10, 10, 22];
        $this->tableHeader($sheet, $row, $headers, $sBg, $fw);
        $row++;
        foreach ($data['weighings'] as $i => $w) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $cells = [
                $i + 1,
                $w->weighed_at?->format('d/m/Y H:i') ?? 'N/A',
                $w->lot?->lot_number ?? 'N/A',
                $w->lot?->workOrder?->wo_number ?? 'N/A',
                $w->kit?->kit_number ?? '—',
                $w->quantity,
                $w->good_pieces,
                $w->bad_pieces,
                $w->weighedBy?->full_name ?? 'N/A',
            ];
            $this->dataRow($sheet, $row, $cells, $bg);
            $row++;
        }
        $this->applyWidths($sheet, count($headers), $widths);
        return $sp;
    }

    // =========================================================
    // Excel builders — MATERIALES
    // =========================================================

    private function buildMaterialesSheet(array $data): Spreadsheet
    {
        $sp    = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Materiales');

        [$hBg, $sBg, $odd, $white, $fw] = $this->colors();

        $this->headerRow($sheet, 'REPORTE DE MATERIALES', $data, 8, $hBg, $sBg, $fw);

        $row = 4;
        $this->sectionTitle($sheet, $row, 'RESUMEN', $hBg, $fw, 8);
        $row++;
        // El flujo migró de Kits a lotes de CRIMP y el PDF se actualizó, pero
        // esta hoja se quedó leyendo claves que ReportService ya no devuelve:
        // descargar Materiales o General en Excel reventaba con
        // "Undefined array key". Ahora usa las mismas claves que el PDF.
        $stats = [
            ['Total lotes',          $data['stats']['total_lotes']],
            ['Lotes pendientes',     $data['stats']['lotes_pendientes']],
            ['Lotes liberados',      $data['stats']['lotes_liberados']],
            ['Lotes rechazados',     $data['stats']['lotes_rechazados']],
            ['Total lotes de CRIMP', $data['stats']['total_crimp_lots']],
            ['Piezas de CRIMP',      $data['stats']['total_crimp_piezas']],
        ];
        foreach ($stats as $i => $s) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $sheet->setCellValue("A{$row}", $s[0]);
            $sheet->setCellValue("B{$row}", $s[1]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $row++;
        }
        $row++;

        // Lotes
        $this->sectionTitle($sheet, $row, 'LOTES', $hBg, $fw, 8);
        $row++;
        $hLots = ['#', 'Lote', 'Work Order', 'Cantidad', 'Proveedor', 'Fecha Recepción', 'Vencimiento', 'Estatus Material'];
        $wLots = [5, 14, 14, 10, 20, 16, 16, 18];
        $this->tableHeader($sheet, $row, $hLots, $sBg, $fw);
        $row++;
        foreach ($data['lots'] as $i => $lot) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $this->dataRow($sheet, $row, [
                $i + 1,
                $lot->lot_number,
                $lot->workOrder?->wo_number ?? 'N/A',
                $lot->quantity,
                $lot->supplier_name ?? 'N/A',
                $lot->receipt_date?->format('d/m/Y') ?? 'N/A',
                $lot->expiration_date?->format('d/m/Y') ?? 'N/A',
                ucfirst($lot->material_status ?? 'N/A'),
            ], $bg);
            $row++;
        }
        $row++;

        // Lotes de CRIMP (antes eran Kits, que ya no se usan)
        $this->sectionTitle($sheet, $row, 'LOTES DE CRIMP', $hBg, $fw, 8);
        $row++;
        $hCrimp = ['#', 'Lote de CRIMP', 'Viajero', 'Work Order', 'Cantidad', 'Lote fabricante', 'Registrado'];
        $this->tableHeader($sheet, $row, $hCrimp, $sBg, $fw);
        $row++;
        foreach ($data['crimp_lots'] as $i => $crimpLot) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $this->dataRow($sheet, $row, [
                $i + 1,
                $crimpLot->crimp_lot_number,
                $crimpLot->lot?->lot_number ?? 'N/A',
                $crimpLot->lot?->workOrder?->wo_number ?? 'N/A',
                $crimpLot->quantity ?? 0,
                $crimpLot->lote_fabricante ?? '—',
                $crimpLot->created_at?->format('d/m/Y H:i') ?? '—',
            ], $bg);
            $row++;
        }
        $this->applyWidths($sheet, 8, array_merge($wLots, []));
        return $sp;
    }

    // =========================================================
    // Excel builders — CALIDAD
    // =========================================================

    private function buildCalidadSheet(array $data): Spreadsheet
    {
        $sp    = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Calidad');

        [$hBg, $sBg, $odd, $white, $fw] = $this->colors();

        $this->headerRow($sheet, 'REPORTE DE CALIDAD', $data, 9, $hBg, $sBg, $fw);

        $row = 4;
        $this->sectionTitle($sheet, $row, 'RESUMEN', $hBg, $fw, 9);
        $row++;
        $stats = [
            ['Total registros',        $data['stats']['total_registros']],
            ['Total inspeccionadas',   $data['stats']['total_inspeccionadas']],
            ['Piezas buenas',          $data['stats']['total_buenas']],
            ['Piezas malas',           $data['stats']['total_malas']],
            ['Lotes afectados',        $data['stats']['lotes_afectados']],
            ['Tasa de aprobación (%)', $data['stats']['tasa_aprobacion'] . '%'],
            ['Para scrap',             $data['stats']['para_scrap']],
            ['Para rework',            $data['stats']['para_rework']],
            ['Rework pendiente',       $data['stats']['rework_pendiente']],
            ['Rework completado',      $data['stats']['rework_completado']],
        ];
        foreach ($stats as $i => $s) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $sheet->setCellValue("A{$row}", $s[0]);
            $sheet->setCellValue("B{$row}", $s[1]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $row++;
        }
        $row++;

        $this->sectionTitle($sheet, $row, 'DETALLE DE INSPECCIONES', $hBg, $fw, 9);
        $row++;
        $headers = ['#', 'Fecha', 'Lote', 'Work Order', 'Kit', 'Piezas Prod.', 'Buenas', 'Malas', 'Disposición', 'Rework Status', 'Inspector'];
        $widths  = [5, 16, 14, 14, 12, 12, 10, 10, 14, 18, 22];
        $this->tableHeader($sheet, $row, $headers, $sBg, $fw);
        $row++;
        foreach ($data['registros'] as $i => $r) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $this->dataRow($sheet, $row, [
                $i + 1,
                $r->weighed_at?->format('d/m/Y H:i') ?? 'N/A',
                $r->lot?->lot_number ?? 'N/A',
                $r->lot?->workOrder?->wo_number ?? 'N/A',
                $r->kit?->kit_number ?? '—',
                $r->production_good_pieces,
                $r->good_pieces,
                $r->bad_pieces,
                $r->disposition ? ucfirst($r->disposition) : '—',
                $r->rework_status ? ucfirst(str_replace('_', ' ', $r->rework_status)) : '—',
                $r->weighedBy?->full_name ?? 'N/A',
            ], $bg);
            $row++;
        }
        $this->applyWidths($sheet, count($headers), $widths);
        return $sp;
    }

    // =========================================================
    // Excel builders — EMPAQUES
    // =========================================================

    private function buildEmpaquesSheet(array $data): Spreadsheet
    {
        $sp    = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Empaques');

        [$hBg, $sBg, $odd, $white, $fw] = $this->colors();

        $this->headerRow($sheet, 'REPORTE DE EMPAQUES', $data, 8, $hBg, $sBg, $fw);

        $row = 4;
        $this->sectionTitle($sheet, $row, 'RESUMEN', $hBg, $fw, 8);
        $row++;
        $stats = [
            ['Registros de empaque',    $data['stats']['total_registros']],
            ['Piezas disponibles',      $data['stats']['total_disponibles']],
            ['Piezas empacadas',        $data['stats']['total_empacadas']],
            ['Sobrante total',          $data['stats']['total_sobrante']],
            ['Lotes procesados',        $data['stats']['lotes_procesados']],
            ['Packing Slips Total',     $data['stats']['total_packing_slips']],
            ['PS Borradores',           $data['stats']['ps_borradores']],
            ['PS Pendientes',           $data['stats']['ps_pendientes']],
            ['PS Despachados',          $data['stats']['ps_despachados']],
            ['PS Cancelados',           $data['stats']['ps_cancelados']],
        ];
        foreach ($stats as $i => $s) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $sheet->setCellValue("A{$row}", $s[0]);
            $sheet->setCellValue("B{$row}", $s[1]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
            $row++;
        }
        $row++;

        // Registros de empaque
        $this->sectionTitle($sheet, $row, 'REGISTROS DE EMPAQUE', $hBg, $fw, 8);
        $row++;
        $hRec = ['#', 'Fecha Empaque', 'Lote', 'Work Order', 'Kit', 'Disponibles', 'Empacadas', 'Sobrante', 'Operador'];
        $wRec = [5, 16, 14, 14, 12, 12, 12, 12, 22];
        $this->tableHeader($sheet, $row, $hRec, $sBg, $fw);
        $row++;
        foreach ($data['records'] as $i => $rec) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $this->dataRow($sheet, $row, [
                $i + 1,
                $rec->packed_at?->format('d/m/Y H:i') ?? 'N/A',
                $rec->lot?->lot_number ?? 'N/A',
                $rec->lot?->workOrder?->wo_number ?? 'N/A',
                $rec->kit?->kit_number ?? '—',
                $rec->available_pieces,
                $rec->packed_pieces,
                $rec->surplus_pieces,
                $rec->packedBy?->full_name ?? 'N/A',
            ], $bg);
            $row++;
        }
        $row++;

        // Packing Slips
        $this->sectionTitle($sheet, $row, 'PACKING SLIPS', $hBg, $fw, 8);
        $row++;
        $hPS = ['#', 'No. PS', 'Fecha Documento', 'Fecha Despacho', 'Estatus', 'Invoice'];
        $wPS = [5, 16, 18, 18, 14, 14];
        $this->tableHeader($sheet, $row, $hPS, $sBg, $fw);
        $row++;
        foreach ($data['slips'] as $i => $slip) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $this->dataRow($sheet, $row, [
                $i + 1,
                $slip->ps_number,
                $slip->document_date?->format('d/m/Y') ?? 'N/A',
                $slip->shipped_at?->format('d/m/Y H:i') ?? '—',
                ucfirst($slip->status),
                $slip->invoice?->invoice_number ?? '—',
            ], $bg);
            $row++;
        }
        $this->applyWidths($sheet, count($hRec), $wRec);
        return $sp;
    }

    // =========================================================
    // Helpers de Excel compartidos
    // =========================================================

    private function colors(): array
    {
        return ['1E3A5F', '3B6EA5', 'EBF1F8', 'FFFFFF', 'FFFFFF'];
    }

    private function headerRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $title,
        array $data,
        int $cols,
        string $hBg,
        string $sBg,
        string $fw
    ): void {
        $last = chr(64 + $cols);

        $sheet->mergeCells("A1:{$last}1");
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $fw]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $periodLabel = ($data['start_date'] && $data['end_date'])
            ? 'Del ' . $data['start_date'] . ' al ' . $data['end_date']
            : 'Sin filtro de fecha';

        $sheet->mergeCells("A2:{$last}2");
        $sheet->setCellValue('A2', 'Período: ' . $periodLabel . '   |   Generado: ' . $data['generated_at']);
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 8, 'color' => ['rgb' => $fw]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
    }

    private function sectionTitle(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        string $title,
        string $hBg,
        string $fw,
        int $cols
    ): void {
        $last = chr(64 + $cols);
        $sheet->mergeCells("A{$row}:{$last}{$row}");
        $sheet->setCellValue("A{$row}", $title);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $fw]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    private function tableHeader(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        array $headers,
        string $sBg,
        string $fw
    ): void {
        foreach ($headers as $k => $h) {
            $col = chr(65 + $k);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $last = chr(64 + count($headers));
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $fw]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);
    }

    private function dataRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        array $cells,
        string $bg
    ): void {
        foreach ($cells as $k => $val) {
            $col = chr(65 + $k);
            $sheet->setCellValue("{$col}{$row}", $val);
        }
        $last = chr(64 + count($cells));
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        ]);
    }

    private function applyWidths(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $count,
        array $widths
    ): void {
        for ($i = 0; $i < $count; $i++) {
            $col = chr(65 + $i);
            $w   = $widths[$i] ?? 14;
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }

    private function addSheetFromData(Spreadsheet $target, Spreadsheet $source, string $title): void
    {
        $sheet = clone $source->getActiveSheet();
        $sheet->setTitle($title);
        $target->addSheet($sheet);
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
