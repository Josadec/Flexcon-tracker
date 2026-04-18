<?php

namespace App\Livewire\Admin\Reports;

use Livewire\Component;

class ReportDashboard extends Component
{
    public string $startDate   = '';
    public string $endDate     = '';
    public string $department  = 'general'; // general | produccion | materiales | calidad | empaques
    public string $format      = 'pdf';     // pdf | excel

    public array $departments = [
        'general'    => ['label' => 'General',    'icon' => 'chart-bar',           'color' => 'blue'],
        'produccion' => ['label' => 'Producción', 'icon' => 'cog-6-tooth',         'color' => 'indigo'],
        'materiales' => ['label' => 'Materiales', 'icon' => 'archive-box',         'color' => 'amber'],
        'calidad'    => ['label' => 'Calidad',    'icon' => 'shield-check',        'color' => 'green'],
        'empaques'   => ['label' => 'Empaques',   'icon' => 'cube',                'color' => 'purple'],
    ];

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
        return view('livewire.admin.reports.report-dashboard');
    }
}
