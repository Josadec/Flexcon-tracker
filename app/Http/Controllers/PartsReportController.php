<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\Price;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartsReportController extends Controller
{
    public function pdf(Request $request)
    {
        $params = $this->validateParams($request);

        if ($params['report_type'] === 'parts') {
            $data = $this->buildPartsData($params);
            $data['logoPath'] = public_path('flexcon.png');

            return Pdf::loadView('pdf.report-parts-status', $data)
                ->setPaper('letter', 'landscape')
                ->download('reporte-partes-estado.pdf');
        }

        $data = $this->buildPricesData($params);
        $data['logoPath'] = public_path('flexcon.png');

        return Pdf::loadView('pdf.report-parts-prices', $data)
            ->setPaper('letter', 'landscape')
            ->download('reporte-partes-precios.pdf');
    }

    public function excel(Request $request): StreamedResponse
    {
        $params = $this->validateParams($request);

        if ($params['report_type'] === 'parts') {
            $sp = $this->buildPartsSheet($this->buildPartsData($params));
            $filename = 'reporte-partes-estado.xlsx';
        } else {
            $sp = $this->buildPricesSheet($this->buildPricesData($params));
            $filename = 'reporte-partes-precios.xlsx';
        }

        return $this->streamXlsx($sp, $filename);
    }

    private function validateParams(Request $request): array
    {
        return $request->validate([
            'report_type'    => ['required', 'in:prices,parts'],
            'price_status'   => ['nullable', 'in:all,active,expiring,expired,future'],
            'reference_date' => ['nullable', 'date'],
            'expiring_days'  => ['nullable', 'integer', 'min:1', 'max:365'],
            'parts_status'   => ['nullable', 'in:all,active,inactive'],
            'search'         => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function buildPricesData(array $params): array
    {
        $reference = Carbon::parse($params['reference_date'] ?? now());
        $expiringDays = (int) ($params['expiring_days'] ?? 30);
        $status = $params['price_status'] ?? 'all';
        $search = $params['search'] ?? '';

        $query = Price::query()->with('part')->whereHas('part');

        if ($status === 'active') {
            $query->where('active', true)->where('effective_date', '<=', $reference);
        } elseif ($status === 'expired') {
            $query->where('effective_date', '<', $reference)->where('active', false);
        } elseif ($status === 'expiring') {
            $query->where('active', true)
                  ->where('effective_date', '<=', $reference)
                  ->whereDate('effective_date', '>=', $reference->copy()->subDays($expiringDays));
        } elseif ($status === 'future') {
            $query->where('effective_date', '>', $reference);
        }

        if ($search !== '') {
            $query->whereHas('part', function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('item_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $prices = $query->orderBy('effective_date', 'desc')->get();

        $rows = $prices->map(function (Price $price) use ($reference, $expiringDays) {
            $eff = $price->effective_date instanceof Carbon
                ? $price->effective_date
                : Carbon::parse($price->effective_date);

            if (!$price->active && $eff->lt($reference)) {
                $estado = 'Vencido';
            } elseif ($price->active && $eff->lte($reference) && $eff->gte($reference->copy()->subDays($expiringDays))) {
                $estado = 'Por vencer';
            } elseif ($price->active && $eff->lte($reference)) {
                $estado = 'Vigente';
            } elseif ($eff->gt($reference)) {
                $estado = 'Futuro';
            } else {
                $estado = 'Inactivo';
            }

            return (object) [
                'number'           => $price->part->number ?? '—',
                'item_number'      => $price->part->item_number ?? '—',
                'description'      => $price->part->description ?? '—',
                'workstation'      => Price::WORKSTATION_TYPES[$price->workstation_type] ?? $price->workstation_type,
                'sample_price'     => (float) $price->sample_price,
                'effective_date'   => $eff->format('d/m/Y'),
                'estado'           => $estado,
            ];
        });

        return [
            'rows'           => $rows,
            'reference_date' => $reference->format('d/m/Y'),
            'expiring_days'  => $expiringDays,
            'price_status'   => $status,
            'search'         => $search,
            'total'          => $rows->count(),
            'stats'          => [
                'vigentes'    => $rows->where('estado', 'Vigente')->count(),
                'por_vencer'  => $rows->where('estado', 'Por vencer')->count(),
                'vencidos'    => $rows->where('estado', 'Vencido')->count(),
                'futuros'     => $rows->where('estado', 'Futuro')->count(),
            ],
            'generated_at'   => now()->format('d/m/Y H:i'),
        ];
    }

    private function buildPartsData(array $params): array
    {
        $status = $params['parts_status'] ?? 'all';
        $search = $params['search'] ?? '';

        $query = Part::query();

        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('item_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $parts = $query->orderBy('number')->get();

        return [
            'rows'         => $parts,
            'parts_status' => $status,
            'search'       => $search,
            'total'        => $parts->count(),
            'stats'        => [
                'activas'   => $parts->where('active', true)->count(),
                'inactivas' => $parts->where('active', false)->count(),
            ],
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    private function buildPricesSheet(array $data): Spreadsheet
    {
        $sp = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Partes - Precios');

        $hBg = '1E3A5F'; $sBg = '3B6EA5'; $odd = 'EBF1F8'; $white = 'FFFFFF'; $fw = 'FFFFFF';

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'REPORTE DE PARTES — PRECIOS POR FECHA EFECTIVA');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $fw]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', sprintf(
            'Estado: %s   |   Fecha referencia: %s   |   Días por vencer: %d   |   Generado: %s',
            ucfirst($data['price_status']),
            $data['reference_date'],
            $data['expiring_days'],
            $data['generated_at']
        ));
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => $fw]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sBg]],
        ]);

        $headers = ['#', 'N° Parte', 'N° Ítem', 'Descripción', 'Estación', 'Precio Muestra', 'Fecha Efectiva', 'Estado'];
        $row = 4;
        foreach ($headers as $k => $h) {
            $sheet->setCellValueByColumnAndRow($k + 1, $row, $h);
        }
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $fw]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);
        $row++;

        foreach ($data['rows'] as $i => $r) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $values = [$i + 1, $r->number, $r->item_number, $r->description, $r->workstation, '$' . number_format($r->sample_price, 4), $r->effective_date, $r->estado];
            foreach ($values as $k => $v) {
                $sheet->setCellValueByColumnAndRow($k + 1, $row, $v);
            }
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
            $row++;
        }

        $widths = [5, 18, 18, 45, 22, 16, 16, 14];
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
        }
        return $sp;
    }

    private function buildPartsSheet(array $data): Spreadsheet
    {
        $sp = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Partes - Estado');

        $hBg = '1E3A5F'; $sBg = '3B6EA5'; $odd = 'EBF1F8'; $white = 'FFFFFF'; $fw = 'FFFFFF';

        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'REPORTE DE PARTES — CATÁLOGO ACTIVAS / INACTIVAS');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $fw]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A2', sprintf(
            'Filtro: %s   |   Activas: %d   |   Inactivas: %d   |   Total: %d   |   Generado: %s',
            ucfirst($data['parts_status']),
            $data['stats']['activas'],
            $data['stats']['inactivas'],
            $data['total'],
            $data['generated_at']
        ));
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => $fw]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sBg]],
        ]);

        $headers = ['#', 'N° Parte', 'N° Ítem', 'Descripción', 'Unidad', 'Estado'];
        $row = 4;
        foreach ($headers as $k => $h) {
            $sheet->setCellValueByColumnAndRow($k + 1, $row, $h);
        }
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $fw]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);
        $row++;

        foreach ($data['rows'] as $i => $part) {
            $bg = ($i % 2 === 0) ? $odd : $white;
            $values = [
                $i + 1,
                $part->number,
                $part->item_number,
                $part->description,
                $part->unit_of_measure ?? '—',
                $part->active ? 'Activa' : 'Inactiva',
            ];
            foreach ($values as $k => $v) {
                $sheet->setCellValueByColumnAndRow($k + 1, $row, $v);
            }
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
            $row++;
        }

        $widths = [5, 18, 18, 50, 12, 14];
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(chr(65 + $i))->setWidth($w);
        }
        return $sp;
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
