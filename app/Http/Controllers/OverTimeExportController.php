<?php

namespace App\Http\Controllers;

use App\Models\OverTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

class OverTimeExportController extends Controller
{
    public function export(OverTime $overTime)
    {
        $overTime->load(['users', 'shift']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tiempo Extra');

        // --- Encabezado del overtime ---
        $sheet->setCellValue('A1', 'TIEMPO EXTRA: ' . strtoupper($overTime->name));
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // --- Info del overtime ---
        $info = [
            ['Fecha', $overTime->date->format('d/m/Y')],
            ['Turno', $overTime->shift->name ?? '—'],
            ['Horario', \Carbon\Carbon::parse($overTime->start_time)->format('H:i') . ' - ' . \Carbon\Carbon::parse($overTime->end_time)->format('H:i')],
            ['Descanso', $overTime->break_minutes . ' min'],
            ['Horas netas', $overTime->net_hours . ' hrs'],
            ['Total empleados', $overTime->users->count()],
            ['Horas totales', $overTime->total_hours . ' hrs'],
        ];

        $row = 2;
        foreach ($info as [$label, $value]) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, $value);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;
        }

        // --- Espacio antes de la tabla ---
        $row++;

        // --- Encabezados de la tabla de empleados ---
        $headers = ['#', 'NOMBRE', 'NO. EMPLEADO', 'POSICIÓN', 'HORAS NETAS', 'OBSERVACIONES'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ($headers as $i => $header) {
            $cell = $cols[$i] . $row;
            $sheet->setCellValue($cell, $header);
        }

        $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        $row++;
        $startDataRow = $row;

        // --- Filas de empleados ---
        foreach ($overTime->users as $i => $user) {
            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $user->full_name ?? $user->name);
            $sheet->setCellValue('C' . $row, $user->employee_number ?? '—');
            $sheet->setCellValue('D' . $row, $user->position ?? '—');
            $sheet->setCellValue('E' . $row, $overTime->net_hours . ' hrs');
            $sheet->setCellValue('F' . $row, '');

            $bgColor = ($i % 2 === 0) ? 'F0F4FF' : 'FFFFFF';
            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);

            $row++;
        }

        // --- Ajustar anchos de columna ---
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(25);

        // --- Nombre del archivo ---
        $filename = 'tiempo-extra-' . $overTime->date->format('Y-m-d') . '-' . str($overTime->name)->slug() . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
