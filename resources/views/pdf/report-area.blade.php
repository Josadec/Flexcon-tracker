<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte de Área — {{ $area->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #1a1a2e;
            background: #fff;
        }

        /* ── Header ────────────────────────────────── */
        .header {
            background: #1E3A5F;
            color: #fff;
            padding: 14px 20px;
            display: table;
            width: 100%;
        }
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 60px;
        }
        .header-logo img {
            width: 50px;
            height: auto;
        }
        .header-text {
            display: table-cell;
            vertical-align: middle;
            padding-left: 12px;
        }
        .header-text h1 {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-text p {
            font-size: 8pt;
            opacity: 0.85;
            margin-top: 3px;
        }
        .header-date {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 7.5pt;
            opacity: 0.8;
            white-space: nowrap;
        }

        /* ── Info del área ─────────────────────────── */
        .area-info {
            background: #3B6EA5;
            color: #fff;
            padding: 8px 20px;
            font-size: 8pt;
        }
        .area-info table { width: 100%; }
        .area-info td { padding: 1px 8px 1px 0; }
        .area-info .label { opacity: 0.75; font-size: 7pt; }
        .area-info .value { font-weight: bold; }

        /* ── Sección resumen ───────────────────────── */
        .section-title {
            background: #1E3A5F;
            color: #fff;
            padding: 5px 12px;
            font-size: 9pt;
            font-weight: bold;
            margin-top: 14px;
            margin-bottom: 0;
        }

        /* ── Tabla stats ───────────────────────────── */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .stats-table td {
            padding: 5px 12px;
            font-size: 8.5pt;
            border-bottom: 1px solid #e2e8f0;
        }
        .stats-table tr:nth-child(even) { background: #EBF1F8; }
        .stats-table .stat-label { font-weight: bold; width: 55%; }
        .stats-table .stat-value { text-align: right; color: #1E3A5F; font-weight: bold; }

        .stats-grid {
            display: table;
            width: 100%;
            margin-top: 0;
        }
        .stats-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .stats-col:first-child { padding-right: 6px; }
        .stats-col:last-child  { padding-left: 6px; }

        /* ── Tabla empleados ───────────────────────── */
        .employees-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            font-size: 8pt;
        }
        .employees-table thead tr {
            background: #3B6EA5;
            color: #fff;
        }
        .employees-table thead th {
            padding: 6px 8px;
            text-align: left;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .employees-table tbody tr:nth-child(even) { background: #EBF1F8; }
        .employees-table tbody tr:nth-child(odd)  { background: #fff; }
        .employees-table tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .employees-table .center { text-align: center; }
        .employees-table .right  { text-align: right; }

        .badge-active   { color: #15803d; font-weight: bold; }
        .badge-inactive { color: #b91c1c; font-weight: bold; }

        /* ── Footer ────────────────────────────────── */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-size: 7pt;
            color: #94a3b8;
            text-align: center;
        }

        .no-records {
            text-align: center;
            padding: 14px;
            color: #94a3b8;
            font-style: italic;
            font-size: 8pt;
        }

        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    {{-- ── HEADER ────────────────────────────────────── --}}
    <div class="header">
        <div class="header-logo">
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="Flexcon">
            @endif
        </div>
        <div class="header-text">
            <h1>REPORTE DE ÁREA</h1>
            <p>{{ strtoupper($area->name) }}</p>
        </div>
        <div class="header-date">
            Generado: {{ $generated_at }}
        </div>
    </div>

    {{-- ── INFO DEL ÁREA ──────────────────────────────── --}}
    <div class="area-info">
        <table>
            <tr>
                <td>
                    <div class="label">Departamento</div>
                    <div class="value">{{ $area->department?->name ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="label">Supervisor</div>
                    <div class="value">{{ $area->user?->full_name ?? 'Sin supervisor' }}</div>
                </td>
                <td>
                    <div class="label">Período</div>
                    <div class="value">
                        @if($start_date && $end_date)
                            {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }}
                            al
                            {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
                        @else
                            Sin filtro de fecha
                        @endif
                    </div>
                </td>
                <td>
                    <div class="label">Descripción</div>
                    <div class="value">{{ $area->description ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── RESUMEN ────────────────────────────────────── --}}
    <div class="section-title">RESUMEN DEL ÁREA</div>

    <div class="stats-grid">
        <div class="stats-col">
            <table class="stats-table">
                <tr>
                    <td class="stat-label">Empleados Totales</td>
                    <td class="stat-value">{{ $stats['total_employees'] }}</td>
                </tr>
                <tr>
                    <td class="stat-label">Empleados Activos</td>
                    <td class="stat-value" style="color:#15803d">{{ $stats['active_employees'] }}</td>
                </tr>
                <tr>
                    <td class="stat-label">Empleados Inactivos</td>
                    <td class="stat-value" style="color:#b91c1c">{{ $stats['inactive_employees'] }}</td>
                </tr>
                <tr>
                    <td class="stat-label">Empleados en el período</td>
                    <td class="stat-value">{{ $stats['filtered_employees'] }}</td>
                </tr>
            </table>
        </div>
        <div class="stats-col">
            <table class="stats-table">
                <tr>
                    <td class="stat-label">Máquinas (Total / Activas)</td>
                    <td class="stat-value">{{ $stats['total_machines'] }} / {{ $stats['active_machines'] }}</td>
                </tr>
                <tr>
                    <td class="stat-label">Mesas (Total / Activas)</td>
                    <td class="stat-value">{{ $stats['total_tables'] }} / {{ $stats['active_tables'] }}</td>
                </tr>
                <tr>
                    <td class="stat-label">Semi-Auto (Total / Activos)</td>
                    <td class="stat-value">{{ $stats['total_semi_automatics'] }} / {{ $stats['active_semi_automatics'] }}</td>
                </tr>
                <tr>
                    <td class="stat-label">Total Equipo</td>
                    <td class="stat-value">{{ $stats['total_equipment'] }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ── LISTADO DE EMPLEADOS ───────────────────────── --}}
    <div class="section-title" style="margin-top:16px">
        LISTADO DE EMPLEADOS
        @if($start_date && $end_date)
            — Filtrado por fecha de ingreso
        @endif
    </div>

    @if($employees->isNotEmpty())
        <table class="employees-table">
            <thead>
                <tr>
                    <th style="width:4%">#</th>
                    <th style="width:12%">No. Empleado</th>
                    <th style="width:26%">Nombre Completo</th>
                    <th style="width:20%">Puesto</th>
                    <th style="width:14%">Turno</th>
                    <th style="width:13%">Fecha Ingreso</th>
                    <th style="width:11%">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $i => $employee)
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $employee->employee_number ?? 'N/A' }}</td>
                        <td>{{ $employee->full_name }}</td>
                        <td>{{ $employee->position ?? 'N/A' }}</td>
                        <td>{{ $employee->shift?->name ?? 'N/A' }}</td>
                        <td class="center">{{ $employee->entry_date?->format('d/m/Y') ?? 'N/A' }}</td>
                        <td class="center {{ $employee->active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $employee->active ? 'Activo' : 'Inactivo' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-records">No hay empleados para el período seleccionado.</div>
    @endif

    {{-- ── FOOTER ─────────────────────────────────────── --}}
    <div class="footer">
        Flexcon Tracker &mdash; Reporte de Área: {{ $area->name }} &mdash; {{ $generated_at }}
        &mdash; Este documento es de uso interno.
    </div>

</body>
</html>
