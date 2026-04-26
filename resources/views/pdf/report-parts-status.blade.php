<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Partes - Estado</title>
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
        .sec { background:#1E3A5F; color:#fff; padding:4px 10px; font-size:8.5pt; font-weight:bold; margin-top:12px; }
        .stats { display:table; width:100%; margin-top:0; }
        .sc { display:table-cell; vertical-align:top; width:33.33%; padding-right:4px; }
        .sc:last-child { padding-right:0; }
        .stat-card { background:#EBF1F8; border-left:3px solid #3B6EA5; padding:6px 10px; margin-top:4px; }
        .stat-card .lbl { font-size:7pt; color:#64748b; }
        .stat-card .val { font-size:13pt; font-weight:bold; color:#1E3A5F; }
        table.data { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:0; }
        table.data thead tr { background:#3B6EA5; color:#fff; }
        table.data thead th { padding:5px 6px; text-align:left; }
        table.data tbody tr:nth-child(even) { background:#EBF1F8; }
        table.data tbody tr:nth-child(odd)  { background:#fff; }
        table.data tbody td { padding:4px 6px; border-bottom:1px solid #e2e8f0; }
        table.data .c { text-align:center; }
        .active   { color:#15803d; font-weight:bold; }
        .inactive { color:#475569; font-weight:bold; }
        .footer { margin-top:16px; border-top:1px solid #cbd5e1; padding-top:5px; font-size:6.5pt; color:#94a3b8; text-align:center; }
        .no-data { text-align:center; padding:12px; color:#94a3b8; font-style:italic; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-logo">@if(file_exists($logoPath))<img src="{{ $logoPath }}" alt="Flexcon">@endif</div>
    <div class="h-text">
        <h1>REPORTE — PARTES ACTIVAS / INACTIVAS</h1>
        <p>Catálogo de partes registradas en el sistema</p>
    </div>
    <div class="h-date">Generado: {{ $generated_at }}</div>
</div>

<div class="period">
    Filtro: {{ ucfirst($parts_status) }}
    @if($search) | Búsqueda: "{{ $search }}" @endif
</div>

<div class="sec">RESUMEN</div>

<div class="stats">
    <div class="sc">
        <div class="stat-card"><div class="lbl">Total</div><div class="val">{{ $total }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-card"><div class="lbl">Activas</div><div class="val active">{{ $stats['activas'] }}</div></div>
    </div>
    <div class="sc">
        <div class="stat-card"><div class="lbl">Inactivas</div><div class="val inactive">{{ $stats['inactivas'] }}</div></div>
    </div>
</div>

<div class="sec">DETALLE</div>

@if($rows->isEmpty())
    <div class="no-data">Sin resultados para los filtros seleccionados</div>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:15%">N° Parte</th>
                <th style="width:15%">N° Ítem</th>
                <th style="width:48%">Descripción</th>
                <th class="c" style="width:8%">Unidad</th>
                <th class="c" style="width:9%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $part)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $part->number }}</td>
                    <td>{{ $part->item_number }}</td>
                    <td>{{ $part->description }}</td>
                    <td class="c">{{ $part->unit_of_measure ?? '—' }}</td>
                    <td class="c">
                        @if($part->active)
                            <span class="active">Activa</span>
                        @else
                            <span class="inactive">Inactiva</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">Flexcon Tracker — Reporte de Partes Activas/Inactivas</div>
</body>
</html>
