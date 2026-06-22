<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte General</title>
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

        .period { background:#3B6EA5; color:#fff; padding:5px 18px; font-size:7.5pt; }

        .dept-header { color:#fff; padding:5px 12px; font-size:10pt; font-weight:bold; margin-top:16px; }
        .dept-prod  { background:#4338CA; }
        .dept-mat   { background:#D97706; }
        .dept-cal   { background:#16A34A; }
        .dept-emp   { background:#7C3AED; }

        .kpi-row { display:table; width:100%; margin-top:4px; margin-bottom:8px; }
        .kpi-cell { display:table-cell; vertical-align:top; padding-right:5px; }
        .kpi-cell:last-child { padding-right:0; }
        .kpi-box { padding:5px 10px; border:1px solid #e2e8f0; }
        .kpi-box .lbl { font-size:7pt; color:#64748b; }
        .kpi-box .val { font-size:11pt; font-weight:bold; color:#1E3A5F; }
        .kpi-box .sub { font-size:6.5pt; color:#94a3b8; }

        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:0; }
        table.data thead th { padding:4px 6px; text-align:left; color:#fff; }
        table.data tbody tr:nth-child(even) td { background:#F8FAFC; }
        table.data tbody tr:nth-child(odd) td  { background:#fff; }
        table.data tbody td { padding:3.5px 6px; border-bottom:1px solid #e2e8f0; }
        table.data .c { text-align:center; }
        table.data .r { text-align:right; }
        .ok  { color:#15803d; font-weight:bold; }
        .bad { color:#b91c1c; font-weight:bold; }

        .footer { margin-top:16px; border-top:1px solid #cbd5e1; padding-top:5px; font-size:6.5pt; color:#94a3b8; text-align:center; }
        .no-data { text-align:center; padding:10px; color:#94a3b8; font-style:italic; font-size:7.5pt; }
        .page-break { page-break-before:always; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-logo">@if(file_exists($logoPath))<img src="{{ $logoPath }}" alt="Flexcon">@endif</div>
    <div class="h-text">
        <h1>REPORTE GENERAL — TODOS LOS DEPARTAMENTOS</h1>
        <p>Producción · Materiales · Calidad · Empaques</p>
    </div>
    <div class="h-date">Generado: {{ $generated_at }}</div>
</div>
<div class="period">
    Período:&nbsp;
    @if($start_date && $end_date)
        Del {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
    @else Sin filtro de fecha @endif
</div>

{{-- ══ PRODUCCIÓN ════════════════════════════════════════════ --}}
<div class="dept-header dept-prod">PRODUCCIÓN</div>
<div class="kpi-row">
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Pesadas</div><div class="val">{{ $produccion['stats']['total_registros'] }}</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Total piezas</div><div class="val">{{ number_format($produccion['stats']['total_piezas']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Piezas buenas</div><div class="val ok">{{ number_format($produccion['stats']['total_buenas']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Piezas malas</div><div class="val bad">{{ number_format($produccion['stats']['total_malas']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Tasa calidad</div><div class="val">{{ $produccion['stats']['tasa_calidad'] }}%</div><div class="sub">Lotes: {{ $produccion['stats']['lotes_afectados'] }}</div></div>
    </div>
</div>
@if($produccion['weighings']->isNotEmpty())
<table class="data">
    <thead><tr style="background:#4338CA">
        <th style="width:4%">#</th><th style="width:14%">Fecha</th><th style="width:13%">Viajero</th>
        <th style="width:13%">Work Order</th>
        <th style="width:9%">Total</th><th style="width:9%">Buenas</th><th style="width:9%">Malas</th>
        <th style="width:29%">Operador</th>
    </tr></thead>
    <tbody>
        @foreach($produccion['weighings'] as $i => $w)
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
@else<div class="no-data">Sin registros de pesada para el período.</div>@endif

{{-- ══ MATERIALES ═════════════════════════════════════════════ --}}
<div class="page-break"></div>
<div class="dept-header dept-mat">MATERIALES</div>
<div class="kpi-row">
    <div class="kpi-cell" style="width:25%">
        <div class="kpi-box"><div class="lbl">Viajeros</div><div class="val">{{ $materiales['stats']['total_lotes'] }}</div><div class="sub">Lib: {{ $materiales['stats']['lotes_liberados'] }} · Rec: {{ $materiales['stats']['lotes_rechazados'] }}</div></div>
    </div>
    <div class="kpi-cell" style="width:25%">
        <div class="kpi-box"><div class="lbl">Lotes de CRIMP</div><div class="val">{{ $materiales['stats']['total_crimp_lots'] }}</div><div class="sub">Piezas: {{ number_format($materiales['stats']['total_crimp_piezas']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:25%">
        <div class="kpi-box"><div class="lbl">Viajeros liberados</div><div class="val" style="color:#15803d">{{ $materiales['stats']['lotes_liberados'] }}</div></div>
    </div>
    <div class="kpi-cell" style="width:25%">
        <div class="kpi-box"><div class="lbl">Viajeros pendientes</div><div class="val" style="color:#b45309">{{ $materiales['stats']['lotes_pendientes'] }}</div></div>
    </div>
</div>
@if($materiales['lots']->isNotEmpty())
<table class="data">
    <thead><tr style="background:#D97706">
        <th style="width:4%">#</th><th style="width:12%">Lote</th><th style="width:13%">Work Order</th>
        <th style="width:8%">Cantidad</th><th style="width:18%">Proveedor</th>
        <th style="width:13%">F. Recepción</th><th style="width:13%">Vencimiento</th><th style="width:19%">Estatus</th>
    </tr></thead>
    <tbody>
        @foreach($materiales['lots'] as $i => $lot)
        <tr>
            <td class="c">{{ $i+1 }}</td><td>{{ $lot->lot_number }}</td>
            <td>{{ $lot->workOrder?->wo_number ?? 'N/A' }}</td>
            <td class="r">{{ number_format($lot->quantity) }}</td>
            <td>{{ $lot->supplier_name ?? 'N/A' }}</td>
            <td class="c">{{ $lot->receipt_date?->format('d/m/Y') ?? 'N/A' }}</td>
            <td class="c">{{ $lot->expiration_date?->format('d/m/Y') ?? 'N/A' }}</td>
            <td class="c">{{ ucfirst($lot->material_status ?? 'N/A') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else<div class="no-data">Sin lotes para el período.</div>@endif

{{-- ══ CALIDAD ════════════════════════════════════════════════ --}}
<div class="page-break"></div>
<div class="dept-header dept-cal">CALIDAD</div>
<div class="kpi-row">
    <div class="kpi-cell" style="width:16%">
        <div class="kpi-box"><div class="lbl">Inspecciones</div><div class="val">{{ $calidad['stats']['total_registros'] }}</div></div>
    </div>
    <div class="kpi-cell" style="width:16%">
        <div class="kpi-box"><div class="lbl">Inspeccionadas</div><div class="val">{{ number_format($calidad['stats']['total_inspeccionadas']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:16%">
        <div class="kpi-box"><div class="lbl">Buenas</div><div class="val ok">{{ number_format($calidad['stats']['total_buenas']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:16%">
        <div class="kpi-box"><div class="lbl">Malas</div><div class="val bad">{{ number_format($calidad['stats']['total_malas']) }}</div><div class="sub">Scrap: {{ $calidad['stats']['para_scrap'] }} · Rework: {{ $calidad['stats']['para_rework'] }}</div></div>
    </div>
    <div class="kpi-cell" style="width:18%">
        <div class="kpi-box"><div class="lbl">Tasa aprobación</div><div class="val">{{ $calidad['stats']['tasa_aprobacion'] }}%</div></div>
    </div>
    <div class="kpi-cell" style="width:18%">
        <div class="kpi-box"><div class="lbl">Rework activo</div><div class="val">{{ $calidad['stats']['rework_pendiente'] + $calidad['stats']['rework_en_proceso'] }}</div></div>
    </div>
</div>
@if($calidad['registros']->isNotEmpty())
<table class="data">
    <thead><tr style="background:#16A34A">
        <th style="width:4%">#</th><th style="width:13%">Fecha</th><th style="width:12%">Viajero</th>
        <th style="width:12%">Work Order</th>
        <th style="width:8%">Buenas</th><th style="width:8%">Malas</th>
        <th style="width:10%">Disposición</th><th style="width:12%">Rework</th><th style="width:21%">Inspector</th>
    </tr></thead>
    <tbody>
        @foreach($calidad['registros'] as $i => $r)
        <tr>
            <td class="c">{{ $i+1 }}</td>
            <td>{{ $r->weighed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
            <td>{{ $r->lot?->lot_number ?? 'N/A' }}</td>
            <td>{{ $r->lot?->workOrder?->wo_number ?? 'N/A' }}</td>
            <td class="r ok">{{ number_format($r->good_pieces) }}</td>
            <td class="r bad">{{ number_format($r->bad_pieces) }}</td>
            <td class="c">{{ $r->disposition ? ucfirst($r->disposition) : '—' }}</td>
            <td class="c">{{ $r->rework_status ? ucfirst(str_replace('_',' ',$r->rework_status)) : '—' }}</td>
            <td>{{ $r->weighedBy?->full_name ?? 'N/A' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else<div class="no-data">Sin inspecciones para el período.</div>@endif

{{-- ══ EMPAQUES ═══════════════════════════════════════════════ --}}
<div class="page-break"></div>
<div class="dept-header dept-emp">EMPAQUES</div>
<div class="kpi-row">
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Registros empaque</div><div class="val">{{ $empaques['stats']['total_registros'] }}</div><div class="sub">{{ $empaques['stats']['lotes_procesados'] }} lotes</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Disponibles</div><div class="val">{{ number_format($empaques['stats']['total_disponibles']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Empacadas</div><div class="val">{{ number_format($empaques['stats']['total_empacadas']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">Sobrante</div><div class="val">{{ number_format($empaques['stats']['total_sobrante']) }}</div></div>
    </div>
    <div class="kpi-cell" style="width:20%">
        <div class="kpi-box"><div class="lbl">PS Despachados</div><div class="val ok">{{ $empaques['stats']['ps_despachados'] }}</div><div class="sub">Total PS: {{ $empaques['stats']['total_packing_slips'] }}</div></div>
    </div>
</div>
@if($empaques['records']->isNotEmpty())
<table class="data">
    <thead><tr style="background:#7C3AED">
        <th style="width:4%">#</th><th style="width:14%">Fecha</th><th style="width:13%">Viajero</th>
        <th style="width:13%">Work Order</th>
        <th style="width:11%">Disponibles</th><th style="width:11%">Empacadas</th><th style="width:10%">Sobrante</th>
        <th style="width:24%">Operador</th>
    </tr></thead>
    <tbody>
        @foreach($empaques['records'] as $i => $rec)
        <tr>
            <td class="c">{{ $i+1 }}</td>
            <td>{{ $rec->packed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
            <td>{{ $rec->lot?->lot_number ?? 'N/A' }}</td>
            <td>{{ $rec->lot?->workOrder?->wo_number ?? 'N/A' }}</td>
            <td class="r">{{ number_format($rec->available_pieces) }}</td>
            <td class="r">{{ number_format($rec->packed_pieces) }}</td>
            <td class="r">{{ number_format($rec->surplus_pieces) }}</td>
            <td>{{ $rec->packedBy?->full_name ?? 'N/A' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else<div class="no-data">Sin registros de empaque para el período.</div>@endif

<div class="footer">Flexcon Tracker — Reporte General — {{ $generated_at }} — Uso interno</div>
</body>
</html>
