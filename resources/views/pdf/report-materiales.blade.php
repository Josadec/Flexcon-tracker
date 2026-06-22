<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte Materiales</title>
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

        .period { background:#D97706; color:#fff; padding:5px 18px; font-size:7.5pt; }

        .sec { background:#1E3A5F; color:#fff; padding:4px 10px; font-size:8.5pt; font-weight:bold; margin-top:12px; }

        .stats { display:table; width:100%; margin-top:4px; }
        .sc4 { display:table-cell; vertical-align:top; width:25%; padding-right:5px; }
        .sc4:last-child { padding-right:0; }
        .stat-box { border:1px solid #e2e8f0; padding:6px 10px; }
        .stat-box .lbl { font-size:7pt; color:#64748b; }
        .stat-box .val { font-size:12pt; font-weight:bold; color:#1E3A5F; }

        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:0; }
        table.data thead tr { background:#D97706; color:#fff; }
        table.data thead th { padding:5px 6px; text-align:left; }
        table.data tbody tr:nth-child(even) { background:#FEF3C7; }
        table.data tbody tr:nth-child(odd)  { background:#fff; }
        table.data tbody td { padding:4px 6px; border-bottom:1px solid #e2e8f0; }
        table.data .c { text-align:center; }
        table.data .r { text-align:right; }

        .badge-released  { color:#15803d; font-weight:bold; }
        .badge-pending   { color:#b45309; font-weight:bold; }
        .badge-rejected  { color:#b91c1c; font-weight:bold; }

        .footer { margin-top:16px; border-top:1px solid #cbd5e1; padding-top:5px; font-size:6.5pt; color:#94a3b8; text-align:center; }
        .no-data { text-align:center; padding:12px; color:#94a3b8; font-style:italic; }
        .page-break { page-break-before:always; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-logo">@if(file_exists($logoPath))<img src="{{ $logoPath }}" alt="Flexcon">@endif</div>
    <div class="h-text">
        <h1>REPORTE — MATERIALES</h1>
        <p>Viajeros y Lotes de CRIMP del período</p>
    </div>
    <div class="h-date">Generado: {{ $generated_at }}</div>
</div>

<div class="period">
    Período:&nbsp;
    @if($start_date && $end_date)
        Del {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
    @else Sin filtro de fecha @endif
    &nbsp;|&nbsp; Filtro aplicado sobre fecha de recepción (viajeros) y fecha de creación (lotes de CRIMP)
</div>

{{-- RESUMEN LOTES --}}
<div class="sec">RESUMEN — LOTES</div>
<div class="stats">
    <div class="sc4">
        <div class="stat-box"><div class="lbl">Total lotes</div><div class="val">{{ $stats['total_lotes'] }}</div></div>
    </div>
    <div class="sc4">
        <div class="stat-box"><div class="lbl">Pendientes</div><div class="val" style="color:#b45309">{{ $stats['lotes_pendientes'] }}</div></div>
    </div>
    <div class="sc4">
        <div class="stat-box"><div class="lbl">Liberados</div><div class="val" style="color:#15803d">{{ $stats['lotes_liberados'] }}</div></div>
    </div>
    <div class="sc4">
        <div class="stat-box"><div class="lbl">Rechazados</div><div class="val" style="color:#b91c1c">{{ $stats['lotes_rechazados'] }}</div></div>
    </div>
</div>

{{-- TABLA LOTES --}}
@if($lots->isNotEmpty())
<table class="data" style="margin-top:6px">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:12%">Lote</th>
            <th style="width:13%">Work Order</th>
            <th style="width:8%">Cantidad</th>
            <th style="width:18%">Proveedor</th>
            <th style="width:12%">Fecha Recepción</th>
            <th style="width:12%">Vencimiento</th>
            <th style="width:12%">Estatus</th>
            <th style="width:9%">Lotes Batch</th>
        </tr>
    </thead>
    <tbody>
        @foreach($lots as $i => $lot)
        <tr>
            <td class="c">{{ $i+1 }}</td>
            <td>{{ $lot->lot_number }}</td>
            <td>{{ $lot->workOrder?->wo_number ?? 'N/A' }}</td>
            <td class="r">{{ number_format($lot->quantity) }}</td>
            <td>{{ $lot->supplier_name ?? 'N/A' }}</td>
            <td class="c">{{ $lot->receipt_date?->format('d/m/Y') ?? 'N/A' }}</td>
            <td class="c">{{ $lot->expiration_date?->format('d/m/Y') ?? 'N/A' }}</td>
            <td class="c {{ match($lot->material_status) { 'released' => 'badge-released', 'rejected' => 'badge-rejected', default => 'badge-pending' } }}">
                {{ ucfirst($lot->material_status ?? 'N/A') }}
            </td>
            <td class="c">{{ is_array($lot->raw_material_batch_numbers) ? count($lot->raw_material_batch_numbers) : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<div class="no-data">Sin lotes para el período seleccionado.</div>
@endif

{{-- RESUMEN LOTES DE CRIMP --}}
<div class="page-break"></div>
<div class="sec">RESUMEN — LOTES DE CRIMP</div>
<div class="stats">
    <div class="sc4">
        <div class="stat-box"><div class="lbl">Total lotes de CRIMP</div><div class="val">{{ $stats['total_crimp_lots'] }}</div></div>
    </div>
    <div class="sc4">
        <div class="stat-box"><div class="lbl">Piezas en lotes de CRIMP</div><div class="val">{{ number_format($stats['total_crimp_piezas']) }}</div></div>
    </div>
</div>

{{-- TABLA LOTES DE CRIMP --}}
@if($crimp_lots->isNotEmpty())
<table class="data" style="margin-top:6px">
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:16%">Lote de CRIMP</th>
            <th style="width:16%">Viajero</th>
            <th style="width:16%">Work Order</th>
            <th style="width:20%">Lote de fabricante</th>
            <th style="width:12%">Cantidad</th>
            <th style="width:15%">Creado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($crimp_lots as $i => $cl)
        <tr>
            <td class="c">{{ $i+1 }}</td>
            <td>{{ $cl->crimp_lot_number }}</td>
            <td>{{ $cl->lot?->lot_number ?? 'N/A' }}</td>
            <td>{{ $cl->lot?->workOrder?->wo_number ?? 'N/A' }}</td>
            <td>{{ $cl->lote_fabricante ?? '—' }}</td>
            <td class="r">{{ number_format($cl->quantity) }}</td>
            <td class="c">{{ $cl->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<div class="no-data">Sin lotes de CRIMP para el período seleccionado.</div>
@endif

<div class="footer">Flexcon Tracker — Reporte de Materiales — {{ $generated_at }} — Uso interno</div>
</body>
</html>
