<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte Producción</title>
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

        .period { background:#4338CA; color:#fff; padding:5px 18px; font-size:7.5pt; }

        .sec { background:#1E3A5F; color:#fff; padding:4px 10px; font-size:8.5pt; font-weight:bold; margin-top:12px; }

        .stats { display:table; width:100%; margin-top:0; }
        .sc { display:table-cell; vertical-align:top; width:33.33%; padding-right:4px; }
        .sc:last-child { padding-right:0; }
        .stat-card { background:#EBF1F8; border-left:3px solid #4338CA; padding:6px 10px; margin-top:4px; }
        .stat-card .lbl { font-size:7pt; color:#64748b; }
        .stat-card .val { font-size:13pt; font-weight:bold; color:#1E3A5F; }
        .stat-card .sub { font-size:7pt; color:#94a3b8; }

        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:0; }
        table.data thead tr { background:#4338CA; color:#fff; }
        table.data thead th { padding:5px 6px; text-align:left; }
        table.data tbody tr:nth-child(even) { background:#EBF1F8; }
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
        <h1>REPORTE — PRODUCCIÓN</h1>
        <p>Pesadas y registros de producción</p>
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

<div class="stats">
    <div class="sc">
        <div class="stat-card">
            <div class="lbl">Registros de pesada</div>
            <div class="val">{{ $stats['total_registros'] }}</div>
        </div>
    </div>
    <div class="sc">
        <div class="stat-card">
            <div class="lbl">Total piezas</div>
            <div class="val">{{ number_format($stats['total_piezas']) }}</div>
            <div class="sub">Buenas: {{ number_format($stats['total_buenas']) }} · Malas: {{ number_format($stats['total_malas']) }}</div>
        </div>
    </div>
    <div class="sc">
        <div class="stat-card">
            <div class="lbl">Tasa de calidad</div>
            <div class="val">{{ $stats['tasa_calidad'] }}%</div>
            <div class="sub">Lotes afectados: {{ $stats['lotes_afectados'] }}</div>
        </div>
    </div>
</div>

<div class="sec" style="margin-top:14px">DETALLE DE PESADAS</div>

@if($weighings->isNotEmpty())
<table class="data">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:14%">Fecha</th>
            <th style="width:13%">Viajero</th>
            <th style="width:13%">Work Order</th>
            <th style="width:9%">Total</th>
            <th style="width:9%">Buenas</th>
            <th style="width:9%">Malas</th>
            <th style="width:29%">Operador</th>
        </tr>
    </thead>
    <tbody>
        @foreach($weighings as $i => $w)
        <tr>
            <td class="c">{{ $i+1 }}</td>
            <td>{{ $w->weighed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
            <td>{{ $w->lot?->lot_number ?? 'N/A' }}</td>
            <td>{{ $w->lot?->workOrder?->wo_number ?? 'N/A' }}</td>
            <td class="r">{{ number_format($w->quantity) }}</td>
            <td class="r ok">{{ number_format($w->good_pieces) }}</td>
            <td class="r bad">{{ number_format($w->bad_pieces) }}</td>
            <td>{{ $w->weighedBy?->full_name ?? 'N/A' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<div class="no-data">Sin registros de pesada para el período seleccionado.</div>
@endif

<div class="footer">Flexcon Tracker — Reporte de Producción — {{ $generated_at }} — Uso interno</div>
</body>
</html>
