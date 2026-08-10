<?php

namespace App\Livewire\Admin\Reports;

use Livewire\Component;

class ReportDashboard extends Component
{
    public string $startDate   = '';
    public string $endDate     = '';
    public string $department  = 'general'; // general | produccion | materiales | calidad | empaques
    public string $format      = 'pdf';     // pdf | excel

    /**
     * Catálogo de reportes: cómo se llama cada uno, por qué fecha filtra y qué
     * trae. Vivía repartido en la vista (cinco bloques de markup casi iguales);
     * aquí es una sola estructura que la vista recorre.
     */
    public function catalog(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'desc' => 'Resumen unificado de los cuatro departamentos en el mismo período.',
                'dateField' => 'la fecha propia de cada departamento',
                'excelHint' => '4 hojas',
                'includes' => [
                    ['Producción', 'Pesadas, piezas buenas y malas, tasa de calidad'],
                    ['Materiales', 'Viajeros recibidos por estatus y lotes de CRIMP'],
                    ['Calidad', 'Inspecciones, rechazos, rework y scrap'],
                    ['Empaques', 'Registros de empaque, packing slips y sobrante'],
                ],
            ],
            'produccion' => [
                'label' => 'Producción',
                'desc' => 'Lo que se pesó en el período y con qué calidad salió.',
                'dateField' => 'la fecha de pesada',
                'excelHint' => 'Hoja de datos',
                'includes' => [
                    ['Registros de pesada', 'Filtrados por weighed_at'],
                    ['Piezas por registro', 'Total, buenas y malas'],
                    ['Trazabilidad', 'Lote y work order asociados'],
                    ['Operador', 'Quién realizó cada pesada'],
                    ['Tasa de calidad', 'Del período completo'],
                ],
            ],
            'materiales' => [
                'label' => 'Materiales',
                'desc' => 'Qué material entró y en qué estatus quedó.',
                'dateField' => 'la fecha de recepción del viajero y de creación del lote de CRIMP',
                'excelHint' => 'Hoja de datos',
                'includes' => [
                    ['Viajeros recibidos', 'Filtrados por receipt_date'],
                    ['Lotes de CRIMP', 'Filtrados por fecha de creación'],
                    ['Desglose de viajeros', 'Pendientes, liberados y rechazados'],
                    ['Totales', 'Lotes de CRIMP y piezas'],
                ],
            ],
            'calidad' => [
                'label' => 'Calidad',
                'desc' => 'Qué se inspeccionó y qué pasó con lo rechazado.',
                'dateField' => 'la fecha de inspección',
                'excelHint' => 'Hoja de datos',
                'includes' => [
                    ['Inspecciones', 'Filtradas por weighed_at'],
                    ['Piezas', 'Inspeccionadas, buenas, malas y tasa'],
                    ['Disposición de rechazos', 'Scrap contra rework'],
                    ['Estatus de rework', 'Pendiente, en proceso y completado'],
                    ['Inspector', 'Asignado en cada registro'],
                ],
            ],
            'empaques' => [
                'label' => 'Empaques',
                'desc' => 'Qué se empacó y qué documentos salieron.',
                'dateField' => 'la fecha de empaque y la del documento PS',
                'excelHint' => 'Hoja de datos',
                'includes' => [
                    ['Registros de empaque', 'Filtrados por packed_at'],
                    ['Piezas', 'Disponibles, empacadas y sobrante'],
                    ['Packing slips', 'Filtrados por document_date'],
                    ['Estatus de PS', 'Borrador, pendiente, despachado y cancelado'],
                    ['Invoice', 'Vinculado a cada packing slip'],
                ],
            ],
        ];
    }

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate   = now()->format('Y-m-d');
    }

    public function clearDates(): void
    {
        $this->startDate = '';
        $this->endDate   = '';
    }

    /** Rangos de uso frecuente, para no capturar dos fechas a mano. */
    public function applyPreset(string $preset): void
    {
        [$start, $end] = match ($preset) {
            'this-month' => [now()->startOfMonth(), now()],
            'last-month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this-year'  => [now()->startOfYear(), now()],
            'last-30'    => [now()->subDays(30), now()],
            default      => [null, null],
        };

        $this->startDate = $start?->format('Y-m-d') ?? '';
        $this->endDate   = $end?->format('Y-m-d') ?? '';
    }

    public function getDownloadUrl(): string
    {
        $params = http_build_query(array_filter([
            'start_date' => $this->startDate ?: null,
            'end_date'   => $this->endDate   ?: null,
        ]));

        $routeMap = [
            'produccion' => ['pdf' => 'admin.reports.produccion.pdf',  'excel' => 'admin.reports.produccion.excel'],
            'materiales' => ['pdf' => 'admin.reports.materiales.pdf',  'excel' => 'admin.reports.materiales.excel'],
            'calidad'    => ['pdf' => 'admin.reports.calidad.pdf',     'excel' => 'admin.reports.calidad.excel'],
            'empaques'   => ['pdf' => 'admin.reports.empaques.pdf',    'excel' => 'admin.reports.empaques.excel'],
            'general'    => ['pdf' => 'admin.reports.general.pdf',     'excel' => 'admin.reports.general.excel'],
        ];

        $routeName = $routeMap[$this->department][$this->format];

        return route($routeName) . ($params ? '?' . $params : '');
    }

    public function render()
    {
        return view('livewire.admin.reports.report-dashboard', [
            'catalog' => $this->catalog(),
        ]);
    }
}
