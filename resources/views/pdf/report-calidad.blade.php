<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte Calidad</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8pt; color:#1a1a2e; }

        .header { background:#1E3A5F; color:#fff; padding:12px 18px; display:table; width:100%; }
        .h-logo { display:table-cell; vertical-align:middle; width:50px; }
        .h-logo img { width:42px; }
        .h-text { display:table-cell; vertical-align:middle; padding-left:10px; }
        .h-text h1 { font-size:13pt; font-weight:bold; }
        .h-text p { font-size:8pt; opacity:.85; margin-top:2px; }
        .h-date { display:table-cell; vertical-align:middle; text-align:right; font-size:7pt; opacity:.8; white-space:nowrap; }

        .period { background:#16A34A; color:#fff; padding:5px 18px; font-size:7.5pt; }

        .sec { background:#1E3A5F; color:#fff; padding:4px 10px; font-size:8.5pt; font-weight:bold; margin-top:12px; }

        .kpi-row { display:table; width:100%; margin-top:4px; }
        .kpi { display:table-cell; vertical-align:top; padding-right:5px; }
        .kpi:last-child { padding-right:0; }
        .kpi-box { background:#F0FDF4; border-left:3px solid #16A34A; padding:6px 10px; }
        .kpi-box .lbl { font-size:7pt; color:#64748b; }
        .kpi-box .val { font-size:12pt; font-weight:bold; color:#1E3A5F; }
        .kpi-box .sub { font-size:7pt; color:#94a3b8; }
        .kpi-box.warn { background:#FFF7ED; border-left-color:#D97706; }
        .kpi-box.danger { background:#FFF1F2; border-left-color:#b91c1c; }

        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:0; }
        table.data thead tr { background:#16A34A; color:#fff; }
        table.data thead th { padding:5px 6px; text-align:left; }
        table.data tbody tr:nth-child(even) { background:#F0FDF4; }
        table.data tbody tr:nth-child(odd)  { background:#fff; }
        table.data tbody td { padding:4px 6px; border-bottom:1px solid #e2e8f0; }
        table.data .c { text-align:center; }
        table.data .r { text-align:right; }
        .ok  { color:#15803d; font-weight:bold; }
        .bad { color:#b91c1c; font-weight:bold; }

        .footer { margin-top:16px; border-top:1px solid #cbd5e1; padding-top:5px; font-size:6.5pt; color:#94a3b8; text-align:center; }
        .no-data { text-align:center; padding:12px; color:#94a3b8; font-style:italic; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-logo">@if(file_exists($logoPath))<img src="{{ $logoPath }}" alt="Flexcon">@endif</div>
    <div class="h-text">
        <h1>REPORTE — CALIDAD</h1>
        <p>Inspecciones y control de calidad</p>
    </div>
    <div class="h-date">Generado: {{ $generated_at }}</div>
</div>

<div class="period">
    Período:&nbsp;
    @if($start_date && $end_date)
        Del {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
    @else Sin filtro de fecha @endif
</div>

<div class="sec">RESUMEN</div>

<div class="kpi-row">
    <div class="kpi" style="width:16%">
        <div class="kpi-box">
            <div class="lbl">Inspecciones</div>
            <div class="val">{{ $stats['total_registros'] }}</div>
        </div>
    </div>
    <div class="kpi" style="width:16%">
        <div class="kpi-box">
            <div class="lbl">Piezas inspeccionadas</div>
            <div class="val">{{ number_format($stats['total_inspeccionadas']) }}</div>
        </div>
    </div>
    <div class="kpi" style="width:16%">
        <div class="kpi-box">
            <div class="lbl">Aprobadas</div>
            <div class="val ok">{{ number_format($stats['total_buenas']) }}</div>
        </div>
    </div>
    <div class="kpi" style="width:16%">
        <div class="kpi-box danger">
            <div class="lbl">Rechazadas</div>
            <div class="val bad">{{ number_format($stats['total_malas']) }}</div>
            <div class="sub">Scrap: {{ $stats['para_scrap'] }} · Rework: {{ $stats['para_rework'] }}</div>
        </div>
    </div>
    <div class="kpi" style="width:18%">
        <div class="kpi-box {{ $stats['tasa_aprobacion'] >= 95 ? '' : ($stats['tasa_aprobacion'] >= 80 ? 'warn' : 'danger') }}">
            <div class="lbl">Tasa de aprobación</div>
            <div class="val">{{ $stats['tasa_aprobacion'] }}%</div>
        </div>
    </div>
    <div class="kpi" style="width:18%">
        <div class="kpi-box warn">
            <div class="lbl">Rework</div>
            <div class="val">{{ $stats['rework_pendiente'] + $stats['rework_en_proceso'] }}</div>
            <div class="sub">Pendiente: {{ $stats['rework_pendiente'] }} · En proceso: {{ $stats['rework_en_proceso'] }}</div>
        </div>
    </div>
</div>

<div class="sec" style="margin-top:14px">DETALLE DE INSPECCIONES</div>

@if($registros->isNotEmpty())
<table class="data">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:13%">Fecha</th>
            <th style="width:12%">Viajero</th>
            <th style="width:12%">Work Order</th>
            <th style="width:8%">Prod.</th>
            <th style="width:7%">Buenas</th>
            <th style="width:7%">Malas</th>
            <th style="width:10%">Disposición</th>
            <th style="width:14%">Rework Status</th>
            <th style="width:20%">Inspector</th>
        </tr>
    </thead>
    <tbody>
        @foreach($registros as $i => $r)
        <tr>
            <td class="c">{{ $i+1 }}</td>
            <td>{{ $r->weighed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
            <td>{{ $r->lot?->lot_number ?? 'N/A' }}</td>
            <td>{{ $r->lot?->workOrder?->wo_number ?? 'N/A' }}</td>
            <td class="r">{{ number_format($r->production_good_pieces) }}</td>
            <td class="r ok">{{ number_format($r->good_pieces) }}</td>
            <td class="r bad">{{ number_format($r->bad_pieces) }}</td>
            <td class="c">{{ $r->disposition ? ucfirst($r->disposition) : '—' }}</td>
            <td class="c">{{ $r->rework_status ? ucfirst(str_replace('_', ' ', $r->rework_status)) : '—' }}</td>
            <td>{{ $r->weighedBy?->full_name ?? 'N/A' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<div class="no-data">Sin inspecciones para el período seleccionado.</div>
@endif

<div class="footer">Flexcon Tracker — Reporte de Calidad — {{ $generated_at }} — Uso interno</div>
</body>
</html>
