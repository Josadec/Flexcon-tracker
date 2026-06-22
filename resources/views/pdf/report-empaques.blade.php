<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte Empaques</title>
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

        .period { background:#7C3AED; color:#fff; padding:5px 18px; font-size:7.5pt; }

        .sec { background:#1E3A5F; color:#fff; padding:4px 10px; font-size:8.5pt; font-weight:bold; margin-top:12px; }

        .stats { display:table; width:100%; margin-top:4px; }
        .sc { display:table-cell; vertical-align:top; width:20%; padding-right:4px; }
        .sc:last-child { padding-right:0; }
        .stat-box { background:#F5F3FF; border-left:3px solid #7C3AED; padding:6px 10px; }
        .stat-box .lbl { font-size:7pt; color:#64748b; }
        .stat-box .val { font-size:12pt; font-weight:bold; color:#1E3A5F; }
        .stat-box .sub { font-size:7pt; color:#94a3b8; }

        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:0; }
        table.data thead tr { background:#7C3AED; color:#fff; }
        table.data thead th { padding:5px 6px; text-align:left; }
        table.data tbody tr:nth-child(even) { background:#F5F3FF; }
        table.data tbody tr:nth-child(odd)  { background:#fff; }
        table.data tbody td { padding:4px 6px; border-bottom:1px solid #e2e8f0; }
        table.data .c { text-align:center; }
        table.data .r { text-align:right; }

        .badge-shipped   { color:#15803d; font-weight:bold; }
        .badge-draft     { color:#64748b; }
        .badge-pending   { color:#b45309; font-weight:bold; }
        .badge-cancelled { color:#b91c1c; font-weight:bold; }

        .footer { margin-top:16px; border-top:1px solid #cbd5e1; padding-top:5px; font-size:6.5pt; color:#94a3b8; text-align:center; }
        .no-data { text-align:center; padding:12px; color:#94a3b8; font-style:italic; }
        .page-break { page-break-before:always; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-logo">@if(file_exists($logoPath))<img src="{{ $logoPath }}" alt="Flexcon">@endif</div>
    <div class="h-text">
        <h1>REPORTE — EMPAQUES</h1>
        <p>Registros de empaque y Packing Slips</p>
    </div>
    <div class="h-date">Generado: {{ $generated_at }}</div>
</div>

<div class="period">
    Período:&nbsp;
    @if($start_date && $end_date)
        Del {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
    @else Sin filtro de fecha @endif
</div>

{{-- RESUMEN --}}
<div class="sec">RESUMEN</div>
<div class="stats">
    <div class="sc">
        <div class="stat-box">
            <div class="lbl">Registros empaque</div>
            <div class="val">{{ $stats['total_registros'] }}</div>
            <div class="sub">{{ $stats['lotes_procesados'] }} lotes</div>
        </div>
    </div>
    <div class="sc">
        <div class="stat-box">
            <div class="lbl">Disponibles</div>
            <div class="val">{{ number_format($stats['total_disponibles']) }}</div>
        </div>
    </div>
    <div class="sc">
        <div class="stat-box">
            <div class="lbl">Empacadas</div>
            <div class="val">{{ number_format($stats['total_empacadas']) }}</div>
        </div>
    </div>
    <div class="sc">
        <div class="stat-box">
            <div class="lbl">Sobrante</div>
            <div class="val">{{ number_format($stats['total_sobrante']) }}</div>
        </div>
    </div>
    <div class="sc">
        <div class="stat-box">
            <div class="lbl">Packing Slips</div>
            <div class="val">{{ $stats['total_packing_slips'] }}</div>
            <div class="sub">Despachados: {{ $stats['ps_despachados'] }}</div>
        </div>
    </div>
</div>

{{-- REGISTROS DE EMPAQUE --}}
<div class="sec" style="margin-top:14px">REGISTROS DE EMPAQUE</div>
@if($records->isNotEmpty())
<table class="data">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:14%">Fecha Empaque</th>
            <th style="width:13%">Viajero</th>
            <th style="width:13%">Work Order</th>
            <th style="width:10%">Disponibles</th>
            <th style="width:10%">Empacadas</th>
            <th style="width:10%">Sobrante</th>
            <th style="width:26%">Operador</th>
        </tr>
    </thead>
    <tbody>
        @foreach($records as $i => $rec)
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
@else
<div class="no-data">Sin registros de empaque para el período seleccionado.</div>
@endif

{{-- PACKING SLIPS --}}
<div class="page-break"></div>
<div class="sec">PACKING SLIPS</div>
<div class="stats" style="margin-top:4px">
    <div class="sc">
        <div class="stat-box"><div class="lbl">Borradores</div><div class="val">{{ $stats['ps_borradores'] }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-box"><div class="lbl">Pendientes</div><div class="val" style="color:#b45309">{{ $stats['ps_pendientes'] }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-box"><div class="lbl">Despachados</div><div class="val" style="color:#15803d">{{ $stats['ps_despachados'] }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-box"><div class="lbl">Cancelados</div><div class="val" style="color:#b91c1c">{{ $stats['ps_cancelados'] }}</div></div>
    </div>
    <div class="sc"></div>
</div>

@if($slips->isNotEmpty())
<table class="data" style="margin-top:6px">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:16%">No. PS</th>
            <th style="width:16%">Fecha Documento</th>
            <th style="width:16%">Fecha Despacho</th>
            <th style="width:12%">Estatus</th>
            <th style="width:16%">Invoice</th>
            <th style="width:20%">Notas</th>
        </tr>
    </thead>
    <tbody>
        @foreach($slips as $i => $slip)
        @php
            $badgeClass = match($slip->status) {
                'shipped'   => 'badge-shipped',
                'pending'   => 'badge-pending',
                'cancelled' => 'badge-cancelled',
                default     => 'badge-draft',
            };
        @endphp
        <tr>
            <td class="c">{{ $i+1 }}</td>
            <td>{{ $slip->ps_number }}</td>
            <td class="c">{{ $slip->document_date?->format('d/m/Y') ?? 'N/A' }}</td>
            <td class="c">{{ $slip->shipped_at?->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="c {{ $badgeClass }}">{{ ucfirst($slip->status) }}</td>
            <td class="c">{{ $slip->invoice?->invoice_number ?? '—' }}</td>
            <td>{{ $slip->notes ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<div class="no-data">Sin Packing Slips para el período seleccionado.</div>
@endif

<div class="footer">Flexcon Tracker — Reporte de Empaques — {{ $generated_at }} — Uso interno</div>
</body>
</html>
